<?php

namespace Tests\Integration;

use App\Models\Gateway;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

class PurchaseFlowIntegrationTest extends IntegrationTestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $finance;
    private User $manager;
    private Product $product;
    private array $purchasePayload;

    protected function setUp(): void
    {
        parent::setUp();

        Gateway::create(['name' => 'Gateway1', 'is_active' => true, 'priority' => 1]);
        Gateway::create(['name' => 'Gateway2', 'is_active' => true, 'priority' => 2]);

        $this->admin = User::factory()->admin()->create();
        $this->finance = User::factory()->finance()->create();
        $this->manager = User::factory()->manager()->create();

        $this->product = Product::factory()->create([
            'name' => 'Produto de Integração',
            'amount' => 5000,
        ]);

        $this->purchasePayload = [
            'product_id' => $this->product->id,
            'quantity' => 2,
            'customer_name' => 'Tester BeTalent',
            'customer_email' => 'tester@betalent.tech',
            'card_number' => '5569000000006063',
            'cvv' => '010',
        ];
    }

    public function test_admin_can_login_and_receive_token(): void
    {
        $user = User::factory()->admin()->create([
            'email' => 'admin-integration@betalent.tech',
            'password' => bcrypt('Senha123'),
        ]);

        $this->postJson('/api/login', [
            'email' => 'admin-integration@betalent.tech',
            'password' => 'Senha123',
        ])
            ->assertStatus(200)
            ->assertJsonStructure(['token', 'user' => ['id', 'name', 'email', 'role']]);
    }

    public function test_login_fails_with_wrong_credentials(): void
    {
        $this->postJson('/api/login', [
            'email' => 'naoexiste@betalent.tech',
            'password' => 'errada',
        ])->assertStatus(401);
    }


    public function test_purchase_is_approved_by_gateway1_and_persisted(): void
    {
        $response = $this->postJson('/api/transactions', $this->purchasePayload);

        $response->assertStatus(201)
            ->assertJsonPath('status', 'paid')
            ->assertJsonPath('amount', 10000)
            ->assertJsonPath('gateway.name', 'Gateway1')
            ->assertJsonStructure([
                'id',
                'status',
                'amount',
                'card_last_numbers',
                'external_id',
                'client' => ['id', 'name', 'email'],
                'gateway' => ['id', 'name'],
                'products' => [['id', 'name', 'pivot' => ['quantity', 'unit_amount']]],
            ]);

        $this->assertDatabaseHas('transactions', [
            'amount' => 10000,
            'status' => 'paid',
        ]);

        $this->assertDatabaseHas('clients', [
            'email' => 'tester@betalent.tech',
            'name' => 'Tester BeTalent',
        ]);

        $this->assertDatabaseHas('transaction_products', [
            'quantity' => 2,
            'unit_amount' => 5000,
        ]);
    }

    public function test_purchase_amount_is_always_calculated_by_backend(): void
    {
        $payload = array_merge($this->purchasePayload, ['amount' => 1]);

        $this->postJson('/api/transactions', $payload)
            ->assertStatus(201)
            ->assertJsonPath('amount', 10000);
    }

    public function test_purchase_stores_only_last_four_card_digits(): void
    {
        $response = $this->postJson('/api/transactions', $this->purchasePayload);

        $response->assertStatus(201)
            ->assertJsonPath('card_last_numbers', '6063');

        $this->assertDatabaseHas('transactions', ['card_last_numbers' => '6063']);
        $this->assertDatabaseMissing('transactions', ['card_last_numbers' => '5569000000006063']);
    }

    public function test_purchase_creates_client_on_first_buy(): void
    {
        $this->assertDatabaseMissing('clients', ['email' => 'tester@betalent.tech']);

        $this->postJson('/api/transactions', $this->purchasePayload)->assertStatus(201);

        $this->assertDatabaseHas('clients', ['email' => 'tester@betalent.tech']);
        $this->assertDatabaseCount('clients', 1);
    }

    public function test_purchase_reuses_existing_client_on_subsequent_buys(): void
    {
        $this->postJson('/api/transactions', $this->purchasePayload)->assertStatus(201);
        $this->postJson('/api/transactions', $this->purchasePayload)->assertStatus(201);

        $this->assertDatabaseCount('clients', 1);
        $this->assertDatabaseCount('transactions', 2);
    }

    public function test_purchase_falls_back_to_gateway2_when_gateway1_is_disabled(): void
    {
        Gateway::where('name', 'Gateway1')->update(['is_active' => false]);

        $this->postJson('/api/transactions', $this->purchasePayload)
            ->assertStatus(201)
            ->assertJsonPath('status', 'paid')
            ->assertJsonPath('gateway.name', 'Gateway2');
    }

    public function test_purchase_fails_when_all_gateways_are_disabled(): void
    {
        Gateway::query()->update(['is_active' => false]);

        $this->postJson('/api/transactions', $this->purchasePayload)
            ->assertStatus(422)
            ->assertJsonStructure(['message']);
    }

    public function test_purchase_with_invalid_cvv_on_both_gateways_returns_error(): void
    {
        $payload = array_merge($this->purchasePayload, ['cvv' => '200']);

        $this->postJson('/api/transactions', $payload)
            ->assertStatus(422)
            ->assertJsonStructure(['message']);
    }


    public function test_admin_can_refund_a_paid_transaction_via_gateway1(): void
    {
        $purchase = $this->postJson('/api/transactions', $this->purchasePayload);
        $purchase->assertStatus(201);

        $transactionId = $purchase->json('id');

        $this->actingAs($this->admin)
            ->postJson("/api/transactions/{$transactionId}/refund")
            ->assertStatus(200)
            ->assertJsonPath('status', 'refunded')
            ->assertJsonPath('id', $transactionId);

        $this->assertDatabaseHas('transactions', [
            'id' => $transactionId,
            'status' => 'refunded',
        ]);
    }

    public function test_finance_can_refund_a_paid_transaction(): void
    {
        $purchase = $this->postJson('/api/transactions', $this->purchasePayload);
        $transactionId = $purchase->json('id');

        $this->actingAs($this->finance)
            ->postJson("/api/transactions/{$transactionId}/refund")
            ->assertStatus(200)
            ->assertJsonPath('status', 'refunded');
    }

    public function test_manager_cannot_refund_a_transaction(): void
    {
        $purchase = $this->postJson('/api/transactions', $this->purchasePayload);
        $transactionId = $purchase->json('id');

        $this->actingAs($this->manager)
            ->postJson("/api/transactions/{$transactionId}/refund")
            ->assertStatus(403);
    }

    public function test_cannot_refund_the_same_transaction_twice(): void
    {
        $purchase = $this->postJson('/api/transactions', $this->purchasePayload);
        $transactionId = $purchase->json('id');

        $this->actingAs($this->admin)
            ->postJson("/api/transactions/{$transactionId}/refund")
            ->assertStatus(200);

        $this->actingAs($this->admin)
            ->postJson("/api/transactions/{$transactionId}/refund")
            ->assertStatus(422)
            ->assertJsonPath('message', "Apenas transações pagas podem ser reembolsadas.");
    }

    public function test_admin_can_refund_a_transaction_processed_by_gateway2(): void
    {
        Gateway::where('name', 'Gateway1')->update(['is_active' => false]);

        $purchase = $this->postJson('/api/transactions', $this->purchasePayload);
        $purchase->assertStatus(201)
            ->assertJsonPath('gateway.name', 'Gateway2');

        $transactionId = $purchase->json('id');

        $this->actingAs($this->admin)
            ->postJson("/api/transactions/{$transactionId}/refund")
            ->assertStatus(200)
            ->assertJsonPath('status', 'refunded');
    }

    public function test_admin_can_list_all_transactions_after_purchases(): void
    {
        $this->postJson('/api/transactions', $this->purchasePayload)->assertStatus(201);
        $this->postJson('/api/transactions', $this->purchasePayload)->assertStatus(201);

        $this->actingAs($this->admin)
            ->getJson('/api/transactions')
            ->assertStatus(200)
            ->assertJsonCount(2, 'data');
    }

    public function test_admin_can_view_full_transaction_detail(): void
    {
        $purchase = $this->postJson('/api/transactions', $this->purchasePayload);
        $transactionId = $purchase->json('id');

        $this->actingAs($this->admin)
            ->getJson("/api/transactions/{$transactionId}")
            ->assertStatus(200)
            ->assertJsonPath('id', $transactionId)
            ->assertJsonPath('status', 'paid')
            ->assertJsonPath('amount', 10000)
            ->assertJsonStructure([
                'client' => ['name', 'email'],
                'gateway' => ['name'],
                'products',
            ]);
    }

    public function test_admin_can_view_client_with_purchase_history(): void
    {
        $this->postJson('/api/transactions', $this->purchasePayload)->assertStatus(201);
        $this->postJson('/api/transactions', $this->purchasePayload)->assertStatus(201);

        $clientId = \App\Models\Client::where('email', 'tester@betalent.tech')->first()->id;

        $this->actingAs($this->admin)
            ->getJson("/api/clients/{$clientId}")
            ->assertStatus(200)
            ->assertJsonPath('email', 'tester@betalent.tech')
            ->assertJsonCount(2, 'transactions');
    }


    public function test_admin_can_toggle_gateway_and_affect_purchase_routing(): void
    {
        $gateway1 = Gateway::where('name', 'Gateway1')->first();

        $this->actingAs($this->admin)
            ->patchJson("/api/gateways/{$gateway1->id}/toggle")
            ->assertStatus(200)
            ->assertJsonPath('gateway.is_active', false);

        $this->postJson('/api/transactions', $this->purchasePayload)
            ->assertStatus(201)
            ->assertJsonPath('gateway.name', 'Gateway2');

        $this->actingAs($this->admin)
            ->patchJson("/api/gateways/{$gateway1->id}/toggle")
            ->assertStatus(200)
            ->assertJsonPath('gateway.is_active', true);

        $this->postJson('/api/transactions', $this->purchasePayload)
            ->assertStatus(201)
            ->assertJsonPath('gateway.name', 'Gateway1');
    }

    public function test_admin_can_swap_gateway_priority_and_affect_purchase_routing(): void
    {
        $gateway1 = Gateway::where('name', 'Gateway1')->first();
        $gateway2 = Gateway::where('name', 'Gateway2')->first();

        $this->actingAs($this->admin)
            ->patchJson("/api/gateways/{$gateway1->id}/priority", ['priority' => 2])
            ->assertStatus(200);

        $this->actingAs($this->admin)
            ->patchJson("/api/gateways/{$gateway2->id}/priority", ['priority' => 1])
            ->assertStatus(200);

        $this->postJson('/api/transactions', $this->purchasePayload)
            ->assertStatus(201)
            ->assertJsonPath('gateway.name', 'Gateway2');
    }
}
