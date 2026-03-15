<?php

namespace Database\Factories;

use App\Models\Client;
use App\Models\Gateway;
use Illuminate\Database\Eloquent\Factories\Factory;

class TransactionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'client_id' => Client::factory(),
            'gateway_id' => Gateway::factory(),
            'external_id' => fake()->uuid(),
            'status' => 'paid',
            'amount' => fake()->numberBetween(1000, 100000),
            'card_last_numbers' => fake()->numerify('####'),
        ];
    }

    public function refunded(): static
    {
        return $this->state(['status' => 'refunded']);
    }

    public function failed(): static
    {
        return $this->state(['status' => 'failed']);
    }
}
