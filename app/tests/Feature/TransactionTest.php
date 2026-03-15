<?php

namespace Tests\Feature;

use App\Contracts\GatewayInterface;
use App\DTOs\GatewayResponseDTO;
use App\DTOs\PaymentDTO;
use App\Models\Gateway;
use App\Models\Product;
use App\Models\Transaction;
use App\Models\User;
use App\Services\GatewayManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransactionTest extends TestCase
{
    use RefreshDatabase;

    private array $validPayload;

    protected function setUp(): void
    {
        parent::setUp();

        $product = Product::factory()->create(['amount' => 5000]); // R$ 50,00

        Gateway::factory()->create(['name' => 'Gateway1', 'priority' => 1, 'is_active' => true]);
        Gateway::factory()->create(['name' => 'Gateway2', 'priority' => 2, 'is_active' => true]);

        $this->validPayload = [
            'product_id'     => $product->id,
            'quantity'       => 2,
            'customer_name'  => 'João Silva',
            'customer_email' => 'joao@example.com',
            'card_number'    => '5569000000006063',
            'cvv'            => '010',
        ];
    }

    // ─── Compra ──────────────────────────────────────────────────

    public function test_purchase_is_processed_successfully(): void
    {
        $this->mockGatewayManager(success: true);

        $response = $this->postJson('/api/transactions', $this->validPayload);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'id', 'status', 'amount', 'card_last_numbers',
                'client'   => ['id', 'name', 'email'],
                'gateway'  => ['id', 'name'],
                'products' => [['id', 'name', 'pivot' => ['quantity', 'unit_amount']]],
            ])
            ->assertJsonPath('status', 'paid')
            // Valor calculado pelo back-end: 5000 × 2 = 10000 centavos
            ->assertJsonPath('amount', 10000);

        $this->assertDatabaseHas('transactions', [
            'amount' => 10000,
            'status' => 'paid',
        ]);

        $this->assertDatabaseHas('transaction_products', [
            'quantity'    => 2,
            'unit_amount' => 5000,
        ]);
    }

    public function test_amount_is_always_calculated_by_backend(): void
    {
        $this->mockGatewayManager(success: true);

        // Mesmo que o cliente tente manipular o valor, o back-end calcula
        $payload = array_merge($this->validPayload, ['amount' => 1]); // tentativa de fraude

        $this->postJson('/api/transactions', $payload)
            ->assertStatus(201)
            ->assertJsonPath('amount', 10000); // 5000 × 2, ignorando o campo 'amount' do payload
    }

    public function test_purchase_creates_client_if_not_exists(): void
    {
        $this->mockGatewayManager(success: true);

        $this->postJson('/api/transactions', $this->validPayload)
            ->assertStatus(201);

        $this->assertDatabaseHas('clients', [
            'email' => 'joao@example.com',
            'name'  => 'João Silva',
        ]);
    }

    public function test_purchase_reuses_existing_client(): void
    {
        $this->mockGatewayManager(success: true);

        $this->postJson('/api/transactions', $this->validPayload)->assertStatus(201);
        $this->postJson('/api/transactions', $this->validPayload)->assertStatus(201);

        $this->assertDatabaseCount('clients', 1);
        $this->assertDatabaseCount('transactions', 2);
    }

    public function test_purchase_falls_back_to_second_gateway_when_first_fails(): void
    {
        $gateway2 = Gateway::where('name', 'Gateway2')->first();

        // Gateway1 falha, Gateway2 responde com sucesso
        $manager = $this->createMock(GatewayManager::class);
        $manager->expects($this->once())
            ->method('charge')
            ->willReturn([
                'response' => GatewayResponseDTO::success('ext-from-gateway2'),
                'gateway'  => $gateway2,
            ]);

        $this->app->instance(GatewayManager::class, $manager);

        $this->postJson('/api/transactions', $this->validPayload)
            ->assertStatus(201)
            ->assertJsonPath('gateway.name', 'Gateway2');
    }

    public function test_purchase_fails_when_all_gateways_fail(): void
    {
        $manager = $this->createMock(GatewayManager::class);
        $manager->method('charge')
            ->willThrowException(new \RuntimeException('Todos os gateways falharam.'));

        $this->app->instance(GatewayManager::class, $manager);

        $this->postJson('/api/transactions', $this->validPayload)
            ->assertStatus(422)
            ->assertJsonPath('message', 'Todos os gateways falharam.');
    }

    public function test_purchase_validates_required_fields(): void
    {
        $this->postJson('/api/transactions', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors([
                'product_id', 'quantity', 'customer_name',
                'customer_email', 'card_number', 'cvv',
            ]);
    }

    public function test_purchase_validates_card_number_length(): void
    {
        $this->postJson('/api/transactions', array_merge($this->validPayload, [
            'card_number' => '1234', // menos de 16 dígitos
        ]))->assertStatus(422)
           ->assertJsonValidationErrors(['card_number']);
    }

    public function test_purchase_validates_product_exists(): void
    {
        $this->postJson('/api/transactions', array_merge($this->validPayload, [
            'product_id' => 9999,
        ]))->assertStatus(422)
           ->assertJsonValidationErrors(['product_id']);
    }

    // ─── Reembolso ───────────────────────────────────────────────

    public function test_admin_can_refund_a_paid_transaction(): void
    {
        $admin       = User::factory()->admin()->create();
        $transaction = Transaction::factory()->create(['status' => 'paid']);

        $manager = $this->createMock(GatewayManager::class);
        $manager->method('refund')
            ->willReturn(GatewayResponseDTO::success($transaction->external_id));

        $this->app->instance(GatewayManager::class, $manager);

        $this->actingAs($admin)
            ->postJson("/api/transactions/{$transaction->id}/refund")
            ->assertStatus(200)
            ->assertJsonPath('status', 'refunded');

        $this->assertDatabaseHas('transactions', [
            'id'     => $transaction->id,
            'status' => 'refunded',
        ]);
    }

    public function test_finance_can_refund_a_transaction(): void
    {
        $finance     = User::factory()->finance()->create();
        $transaction = Transaction::factory()->create(['status' => 'paid']);

        $manager = $this->createMock(GatewayManager::class);
        $manager->method('refund')
            ->willReturn(GatewayResponseDTO::success($transaction->external_id));

        $this->app->instance(GatewayManager::class, $manager);

        $this->actingAs($finance)
            ->postJson("/api/transactions/{$transaction->id}/refund")
            ->assertStatus(200);
    }

    public function test_manager_cannot_refund_a_transaction(): void
    {
        $manager     = User::factory()->manager()->create();
        $transaction = Transaction::factory()->create(['status' => 'paid']);

        $this->actingAs($manager)
            ->postJson("/api/transactions/{$transaction->id}/refund")
            ->assertStatus(403);
    }

    public function test_cannot_refund_an_already_refunded_transaction(): void
    {
        $admin       = User::factory()->admin()->create();
        $transaction = Transaction::factory()->refunded()->create();

        $this->actingAs($admin)
            ->postJson("/api/transactions/{$transaction->id}/refund")
            ->assertStatus(422)
            ->assertJsonPath('message', "Apenas transações pagas podem ser reembolsadas.");
    }

    // ─── Listagem ────────────────────────────────────────────────

    public function test_admin_can_list_all_transactions(): void
    {
        $admin = User::factory()->admin()->create();
        Transaction::factory()->count(3)->create();

        $this->actingAs($admin)
            ->getJson('/api/transactions')
            ->assertStatus(200)
            ->assertJsonCount(3, 'data');
    }

    public function test_unauthenticated_user_cannot_list_transactions(): void
    {
        $this->getJson('/api/transactions')->assertStatus(401);
    }

    // ─── Helpers ─────────────────────────────────────────────────

    private function mockGatewayManager(bool $success): void
    {
        $gateway = Gateway::where('name', 'Gateway1')->first();

        $manager = $this->createMock(GatewayManager::class);
        $manager->method('charge')->willReturn([
            'response' => GatewayResponseDTO::success('ext-123'),
            'gateway'  => $gateway,
        ]);

        $this->app->instance(GatewayManager::class, $manager);
    }
}
