<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_login_with_valid_credentials(): void
    {
        $user = User::factory()->create([
            'email' => 'admin@betalent.tech',
            'password' => bcrypt('password123'),
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'admin@betalent.tech',
            'password' => 'password123',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'user' => ['id', 'name', 'email', 'role'],
                'token',
            ]);
    }

    public function test_login_fails_with_invalid_credentials(): void
    {
        User::factory()->create(['email' => 'user@betalent.tech']);

        $this->postJson('/api/login', [
            'email' => 'user@betalent.tech',
            'password' => 'wrong-password',
        ])->assertStatus(401)
            ->assertJson(['message' => 'Credenciais inválidas.']);
    }

    public function test_login_requires_email_and_password(): void
    {
        $this->postJson('/api/login', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['email', 'password']);
    }

    public function test_authenticated_user_can_logout(): void
    {
        $user = User::factory()->create();

        $token = $user->createToken('api-token')->plainTextToken;

        $this->withToken($token)
            ->postJson('/api/logout')
            ->assertStatus(200)
            ->assertJson(['message' => 'Logout realizado com sucesso.']);

        // $this->withToken($token)
        //     ->postJson('/api/logout')
        //     ->assertStatus(401);
    }

    public function test_unauthenticated_user_cannot_access_private_routes(): void
    {
        $this->getJson('/api/users')->assertStatus(401);
        $this->getJson('/api/products')->assertStatus(401);
        $this->getJson('/api/transactions')->assertStatus(401);
    }
}
