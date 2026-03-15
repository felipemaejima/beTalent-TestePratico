<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductTest extends TestCase
{
    use RefreshDatabase;


    public function test_admin_can_list_products(): void
    {
        $admin = User::factory()->admin()->create();
        Product::factory()->count(5)->create();

        $this->actingAs($admin)
            ->getJson('/api/products')
            ->assertStatus(200)
            ->assertJsonCount(5, 'data');
    }

    public function test_finance_can_list_products(): void
    {
        $finance = User::factory()->finance()->create();
        Product::factory()->count(2)->create();

        $this->actingAs($finance)
            ->getJson('/api/products')
            ->assertStatus(200);
    }


    public function test_admin_can_create_product(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->postJson('/api/products', [
                'name' => 'Produto Teste',
                'amount' => 9900,
            ])
            ->assertStatus(201)
            ->assertJsonPath('name', 'Produto Teste')
            ->assertJsonPath('amount', 9900);
    }

    public function test_manager_can_create_product(): void
    {
        $manager = User::factory()->manager()->create();

        $this->actingAs($manager)
            ->postJson('/api/products', ['name' => 'Produto Manager', 'amount' => 1500])
            ->assertStatus(201);
    }

    public function test_finance_cannot_create_product(): void
    {
        $finance = User::factory()->finance()->create();

        $this->actingAs($finance)
            ->postJson('/api/products', ['name' => 'Bloqueado', 'amount' => 1000])
            ->assertStatus(403);
    }

    public function test_product_amount_must_be_positive_integer(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->postJson('/api/products', ['name' => 'Inválido', 'amount' => -100])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['amount']);
    }


    public function test_admin_can_update_product(): void
    {
        $admin = User::factory()->admin()->create();
        $product = Product::factory()->create(['name' => 'Antigo', 'amount' => 1000]);

        $this->actingAs($admin)
            ->putJson("/api/products/{$product->id}", ['name' => 'Novo', 'amount' => 2000])
            ->assertStatus(200)
            ->assertJsonPath('name', 'Novo')
            ->assertJsonPath('amount', 2000);
    }

    public function test_finance_cannot_update_product(): void
    {
        $finance = User::factory()->finance()->create();
        $product = Product::factory()->create();

        $this->actingAs($finance)
            ->putJson("/api/products/{$product->id}", ['name' => 'Bloqueado'])
            ->assertStatus(403);
    }


    public function test_admin_can_soft_delete_product(): void
    {
        $admin = User::factory()->admin()->create();
        $product = Product::factory()->create();

        $this->actingAs($admin)
            ->deleteJson("/api/products/{$product->id}")
            ->assertStatus(200)
            ->assertJson(['message' => 'Produto removido com sucesso.']);

        $this->assertSoftDeleted('products', ['id' => $product->id]);
    }

    public function test_deleted_product_does_not_appear_in_listing(): void
    {
        $admin = User::factory()->admin()->create();
        $product = Product::factory()->create();
        $product->delete();

        $this->actingAs($admin)
            ->getJson('/api/products')
            ->assertStatus(200)
            ->assertJsonCount(0, 'data');
    }
}
