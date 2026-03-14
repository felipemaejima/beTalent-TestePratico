<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class GatewayFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name'      => fake()->company(),
            'is_active' => true,
            'priority'  => fake()->numberBetween(1, 10),
        ];
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}
