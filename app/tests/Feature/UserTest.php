<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserTest extends TestCase
{
    use RefreshDatabase;


    public function test_admin_can_list_users(): void
    {
        $admin = User::factory()->admin()->create();
        User::factory()->count(3)->create();

        $this->actingAs($admin)
            ->getJson('/api/users')
            ->assertStatus(200)
            ->assertJsonCount(4, 'data');
    }

    public function test_manager_can_list_users(): void
    {
        $manager = User::factory()->manager()->create();

        $this->actingAs($manager)
            ->getJson('/api/users')
            ->assertStatus(200);
    }

    public function test_finance_cannot_list_users(): void
    {
        $finance = User::factory()->finance()->create();

        $this->actingAs($finance)
            ->getJson('/api/users')
            ->assertStatus(403);
    }


    public function test_admin_can_create_user(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->postJson('/api/users', [
                'name' => 'Novo Usuário',
                'email' => 'novo@betalent.tech',
                'password' => 'Senha123',
                'role' => 'finance',
            ])
            ->assertStatus(201)
            ->assertJsonStructure(['id', 'name', 'email', 'role'])
            ->assertJsonPath('role', 'finance');
    }

    public function test_password_is_not_returned_on_create(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->postJson('/api/users', [
                'name' => 'Seguro',
                'email' => 'seguro@betalent.tech',
                'password' => 'Senha123',
                'role' => 'user',
            ])
            ->assertStatus(201)
            ->assertJsonMissing(['password']);
    }

    public function test_cannot_create_user_with_duplicate_email(): void
    {
        $admin = User::factory()->admin()->create();
        User::factory()->create(['email' => 'duplicado@betalent.tech']);

        $this->actingAs($admin)
            ->postJson('/api/users', [
                'name' => 'Outro',
                'email' => 'duplicado@betalent.tech',
                'password' => 'Senha123',
                'role' => 'user',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_cannot_create_user_with_invalid_role(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->postJson('/api/users', [
                'name' => 'Inválido',
                'email' => 'inv@betalent.tech',
                'password' => 'Senha123',
                'role' => 'superadmin',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['role']);
    }


    public function test_admin_can_update_user(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create(['name' => 'Antigo Nome']);

        $this->actingAs($admin)
            ->putJson("/api/users/{$user->id}", ['name' => 'Novo Nome'])
            ->assertStatus(200)
            ->assertJsonPath('name', 'Novo Nome');
    }

    public function test_manager_cannot_update_user(): void
    {
        $manager = User::factory()->manager()->create();
        $user = User::factory()->create();

        $this->actingAs($manager)
            ->putJson("/api/users/{$user->id}", ['name' => 'Bloqueado'])
            ->assertStatus(403);
    }

    public function test_update_email_ignores_own_email_uniqueness(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create(['email' => 'mesmo@betalent.tech']);

        $this->actingAs($admin)
            ->putJson("/api/users/{$user->id}", ['email' => 'mesmo@betalent.tech'])
            ->assertStatus(200);
    }


    public function test_admin_can_delete_user(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();

        $this->actingAs($admin)
            ->deleteJson("/api/users/{$user->id}")
            ->assertStatus(200)
            ->assertJson(['message' => 'Usuário removido com sucesso.']);

        $this->assertSoftDeleted('users', ['id' => $user->id]);
    }

    public function test_manager_cannot_delete_user(): void
    {
        $manager = User::factory()->manager()->create();
        $user = User::factory()->create();

        $this->actingAs($manager)
            ->deleteJson("/api/users/{$user->id}")
            ->assertStatus(403);
    }
}
