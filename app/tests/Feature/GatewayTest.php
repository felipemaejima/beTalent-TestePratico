<?php

namespace Tests\Feature;

use App\Models\Gateway;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GatewayTest extends TestCase
{
    use RefreshDatabase;


    public function test_admin_can_deactivate_a_gateway(): void
    {
        $admin = User::factory()->admin()->create();
        $gateway = Gateway::factory()->create(['is_active' => true]);

        $this->actingAs($admin)
            ->patchJson("/api/gateways/{$gateway->id}/toggle")
            ->assertStatus(200)
            ->assertJsonPath('gateway.is_active', false);

        $this->assertDatabaseHas('gateways', [
            'id' => $gateway->id,
            'is_active' => false,
        ]);
    }

    public function test_admin_can_reactivate_a_gateway(): void
    {
        $admin = User::factory()->admin()->create();
        $gateway = Gateway::factory()->inactive()->create();

        $this->actingAs($admin)
            ->patchJson("/api/gateways/{$gateway->id}/toggle")
            ->assertStatus(200)
            ->assertJsonPath('gateway.is_active', true);
    }

    public function test_manager_cannot_toggle_gateway(): void
    {
        $manager = User::factory()->manager()->create();
        $gateway = Gateway::factory()->create();

        $this->actingAs($manager)
            ->patchJson("/api/gateways/{$gateway->id}/toggle")
            ->assertStatus(403);
    }


    public function test_admin_can_update_gateway_priority(): void
    {
        $admin = User::factory()->admin()->create();
        $gateway = Gateway::factory()->create(['priority' => 1]);

        $this->actingAs($admin)
            ->patchJson("/api/gateways/{$gateway->id}/priority", ['priority' => 3])
            ->assertStatus(200)
            ->assertJsonPath('gateway.priority', 3);

        $this->assertDatabaseHas('gateways', [
            'id' => $gateway->id,
            'priority' => 3,
        ]);
    }

    public function test_priority_must_be_a_positive_integer(): void
    {
        $admin = User::factory()->admin()->create();
        $gateway = Gateway::factory()->create();

        $this->actingAs($admin)
            ->patchJson("/api/gateways/{$gateway->id}/priority", ['priority' => 0])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['priority']);

        $this->actingAs($admin)
            ->patchJson("/api/gateways/{$gateway->id}/priority", ['priority' => 'alto'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['priority']);
    }

    public function test_finance_cannot_update_gateway_priority(): void
    {
        $finance = User::factory()->finance()->create();
        $gateway = Gateway::factory()->create();

        $this->actingAs($finance)
            ->patchJson("/api/gateways/{$gateway->id}/priority", ['priority' => 2])
            ->assertStatus(403);
    }
}
