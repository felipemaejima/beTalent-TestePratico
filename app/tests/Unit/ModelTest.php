<?php

namespace Tests\Unit;

use App\Models\Client;
use App\Models\Gateway;
use App\Models\Product;
use App\Models\Transaction;
use App\Models\TransactionProduct;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ModelTest extends TestCase
{
    use RefreshDatabase;


    public function test_user_is_admin(): void
    {
        $user = User::factory()->admin()->make();

        $this->assertTrue($user->isAdmin());
        $this->assertFalse($user->isManager());
        $this->assertFalse($user->isFinance());
    }

    public function test_user_is_manager(): void
    {
        $user = User::factory()->manager()->make();

        $this->assertFalse($user->isAdmin());
        $this->assertTrue($user->isManager());
        $this->assertFalse($user->isFinance());
    }

    public function test_user_is_finance(): void
    {
        $user = User::factory()->finance()->make();

        $this->assertFalse($user->isAdmin());
        $this->assertFalse($user->isManager());
        $this->assertTrue($user->isFinance());
    }

    public function test_user_role_user_is_none_of_the_above(): void
    {
        $user = User::factory()->make(['role' => 'user']);

        $this->assertFalse($user->isAdmin());
        $this->assertFalse($user->isManager());
        $this->assertFalse($user->isFinance());
    }

    public function test_user_password_is_hidden_in_serialization(): void
    {
        $user = User::factory()->make();

        $this->assertArrayNotHasKey('password', $user->toArray());
    }


    public function test_transaction_is_paid(): void
    {
        $transaction = Transaction::factory()->make(['status' => 'paid']);

        $this->assertTrue($transaction->isPaid());
        $this->assertFalse($transaction->isRefunded());
    }

    public function test_transaction_is_refunded(): void
    {
        $transaction = Transaction::factory()->refunded()->make();

        $this->assertFalse($transaction->isPaid());
        $this->assertTrue($transaction->isRefunded());
    }

    public function test_transaction_paid_scope_returns_only_paid(): void
    {
        Transaction::factory()->create(['status' => 'paid']);
        Transaction::factory()->create(['status' => 'paid']);
        Transaction::factory()->refunded()->create();
        Transaction::factory()->failed()->create();

        $paid = Transaction::paid()->get();

        $this->assertCount(2, $paid);
        $paid->each(fn($t) => $this->assertEquals('paid', $t->status));
    }


    public function test_transaction_product_subtotal_is_calculated_correctly(): void
    {
        $transaction = Transaction::factory()->create();
        $product = Product::factory()->create(['amount' => 3000]);

        $transaction->products()->attach($product->id, [
            'quantity' => 4,
            'unit_amount' => 3000,
        ]);

        $pivot = TransactionProduct::where([
            'transaction_id' => $transaction->id,
            'product_id' => $product->id,
        ])->first();

        $this->assertEquals(12000, $pivot->subtotal());
    }

    public function test_transaction_product_stores_price_snapshot(): void
    {
        $transaction = Transaction::factory()->create();
        $product = Product::factory()->create(['amount' => 5000]);

        $transaction->products()->attach($product->id, [
            'quantity' => 1,
            'unit_amount' => $product->amount,
        ]);

        $product->update(['amount' => 9999]);

        $pivot = TransactionProduct::where('transaction_id', $transaction->id)->first();

        $this->assertEquals(5000, $pivot->unit_amount);
        $this->assertNotEquals($product->fresh()->amount, $pivot->unit_amount);
    }


    public function test_gateway_active_scope_returns_only_active_gateways(): void
    {
        Gateway::factory()->create(['is_active' => true, 'priority' => 2]);
        Gateway::factory()->create(['is_active' => true, 'priority' => 1]);
        Gateway::factory()->inactive()->create(['priority' => 3]);

        $active = Gateway::active()->get();

        $this->assertCount(2, $active);
        $active->each(fn($g) => $this->assertTrue($g->is_active));
    }

    public function test_gateway_active_scope_returns_gateways_ordered_by_priority(): void
    {
        Gateway::factory()->create(['is_active' => true, 'priority' => 3, 'name' => 'C']);
        Gateway::factory()->create(['is_active' => true, 'priority' => 1, 'name' => 'A']);
        Gateway::factory()->create(['is_active' => true, 'priority' => 2, 'name' => 'B']);

        $ordered = Gateway::active()->pluck('name');

        $this->assertEquals(['A', 'B', 'C'], $ordered->toArray());
    }


    public function test_product_has_transactions_relationship(): void
    {
        $product = Product::factory()->create(['amount' => 2000]);
        $transaction = Transaction::factory()->create();

        $transaction->products()->attach($product->id, [
            'quantity' => 1,
            'unit_amount' => 2000,
        ]);

        $this->assertCount(1, $product->transactions);
        $this->assertEquals($transaction->id, $product->transactions->first()->id);
    }

    public function test_soft_deleted_product_is_not_found_by_default(): void
    {
        $product = Product::factory()->create();
        $product->delete();

        $this->assertNull(Product::find($product->id));
        $this->assertNotNull(Product::withTrashed()->find($product->id));
    }


    public function test_client_has_transactions_relationship(): void
    {
        $client = Client::factory()->create();
        Transaction::factory()->count(3)->create(['client_id' => $client->id]);

        $this->assertCount(3, $client->transactions);
    }
}
