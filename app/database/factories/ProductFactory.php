<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class ProductFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name'   => fake()->words(3, true),
            'amount' => fake()->numberBetween(100, 50000),
        ];
    }
}
