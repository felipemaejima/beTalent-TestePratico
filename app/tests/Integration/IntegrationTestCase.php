<?php

namespace Tests\Integration;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

abstract class IntegrationTestCase extends TestCase
{
    /**
     * Verifica se os mocks dos gateways estão no ar antes de cada teste.
     * Se não estiverem, o teste é pulado com uma mensagem clara.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->ensureGateway1IsRunning();
        $this->ensureGateway2IsRunning();
    }

    private function ensureGateway1IsRunning(): void
    {
        try {
            $response = Http::timeout(3)->post(config('gateways.gateway1.url') . '/login', [
                'email' => config('gateways.gateway1.email'),
                'token' => config('gateways.gateway1.token'),
            ]);

            if (!$response->successful()) {
                $this->markTestSkipped('Gateway1 está no ar mas retornou erro no login. Verifique as credenciais.');
            }
        } catch (\Exception $e) {
            $this->markTestSkipped(
                'Gateway1 não está acessível em ' . config('gateways.gateway1.url') . '. ' .
                'Inicie o mock com: docker run -p 3001:3001 -p 3002:3002 matheusprotzen/gateways-mock'
            );
        }
    }

    private function ensureGateway2IsRunning(): void
    {
        try {
            $response = Http::timeout(3)
                ->withHeaders([
                    'Gateway-Auth-Token' => config('gateways.gateway2.auth_token'),
                    'Gateway-Auth-Secret' => config('gateways.gateway2.auth_secret'),
                ])
                ->get(config('gateways.gateway2.url') . '/transacoes');

            if ($response->status() === 0) {
                throw new \Exception('Sem resposta');
            }
        } catch (\Exception $e) {
            $this->markTestSkipped(
                'Gateway2 não está acessível em ' . config('gateways.gateway2.url') . '. ' .
                'Inicie o mock com: docker run -p 3001:3001 -p 3002:3002 matheusprotzen/gateways-mock'
            );
        }
    }
}
