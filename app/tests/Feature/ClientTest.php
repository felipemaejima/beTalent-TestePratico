<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_list_all_clients(): void
    {
        $admin = User::factory()->admin()->create();
        Client::factory()->count(4)->create();

        $this->actingAs($admin)
            ->getJson('/api/clients')
            ->assertStatus(200)
            ->assertJsonCount(4, 'data');
    }

    public function test_finance_can_list_clients(): void
    {
        $finance = User::factory()->finance()->create();
        Client::factory()->count(2)->create();

        $this->actingAs($finance)
            ->getJson('/api/clients')
            ->assertStatus(200);
    }

    public function test_user_role_cannot_list_clients(): void
    {
        $user = User::factory()->create(['role' => 'user']);

        $this->actingAs($user)
            ->getJson('/api/clients')
            ->assertStatus(403);
    }

    public function test_admin_can_view_client_with_all_purchases(): void
    {
        $admin  = User::factory()->admin()->create();
        $client = Client::factory()->create();

        Transaction::factory()->count(3)->create(['client_id' => $client->id]);

        $this->actingAs($admin)
            ->getJson("/api/clients/{$client->id}")
            ->assertStatus(200)
            ->assertJsonStructure([
                'id', 'name', 'email',
                'transactions' => [
                    ['id', 'status', 'amount', 'card_last_numbers', 'gateway', 'products'],
                ],
            ])
            ->assertJsonCount(3, 'transactions');
    }

    public function test_client_detail_shows_empty_transactions_when_no_purchases(): void
    {
        $admin  = User::factory()->admin()->create();
        $client = Client::factory()->create();

        $this->actingAs($admin)
            ->getJson("/api/clients/{$client->id}")
            ->assertStatus(200)
            ->assertJsonCount(0, 'transactions');
    }

    public function test_returns_404_for_nonexistent_client(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->getJson('/api/clients/9999')
            ->assertStatus(404);
    }
}
