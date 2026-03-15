<?php

namespace Database\Seeders;

use App\Models\Gateway;
use Illuminate\Database\Seeder;

class GatewaySeeder extends Seeder
{
    public function run(): void
    {
        $gateways = [
            [
                'name' => 'Gateway1',
                'is_active' => true,
                'priority' => 1,
            ],
            [
                'name' => 'Gateway2',
                'is_active' => true,
                'priority' => 2,
            ],
        ];

        foreach ($gateways as $gateway) {
            Gateway::firstOrCreate(
                ['name' => $gateway['name']],
                $gateway
            );
        }
    }
}
