<?php

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        $products = [
            ['name' => 'Panela de pressao', 'amount' => 5000],
            ['name' => 'Geladeira grandona', 'amount' => 12000],
            ['name' => 'Carro BYD', 'amount' => 29900],
        ];

        foreach ($products as $product) {
            Product::firstOrCreate(
                ['name' => $product['name']],
                $product
            );
        }
    }
}
