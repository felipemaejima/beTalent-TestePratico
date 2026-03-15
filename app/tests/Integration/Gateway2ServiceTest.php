<?php

namespace Tests\Unit;

use App\DTOs\PaymentDTO;
use App\Services\Gateways\Gateway2Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class Gateway2ServiceTest extends TestCase
{
    use RefreshDatabase;

    private Gateway2Service $service;
    private PaymentDTO $payment;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = $this->app->make(Gateway2Service::class);

        $this->payment = new PaymentDTO(
            amount:        10000,
            customerName:  'João Silva',
            customerEmail: 'joao@example.com',
            cardNumber:    '5569000000006063',
            cvv:           '010',
        );
    }

    // ─── Auth ────────────────────────────────────────────────────

    public function test_sends_static_auth_headers_on_charge(): void
    {
        Http::fake([
            '*/transacoes' => Http::response(['id' => 'ext-gw2-001'], 201),
        ]);

        $this->service->charge($this->payment);

        Http::assertSent(function ($request) {
            return $request->hasHeader('Gateway-Auth-Token', config('gateways.gateway2.auth_token'))
                && $request->hasHeader('Gateway-Auth-Secret', config('gateways.gateway2.auth_secret'));
        });
    }

    public function test_does_not_call_login_endpoint(): void
    {
        Http::fake([
            '*/transacoes' => Http::response(['id' => 'ext-001'], 201),
        ]);

        $this->service->charge($this->payment);

        Http::assertNotSent(fn ($request) => str_contains($request->url(), '/login'));
    }

    // ─── Charge ──────────────────────────────────────────────────

    public function test_charge_returns_success_with_external_id(): void
    {
        Http::fake([
            '*/transacoes' => Http::response(['id' => 'ext-gw2-abc'], 201),
        ]);

        $response = $this->service->charge($this->payment);

        $this->assertTrue($response->success);
        $this->assertEquals('ext-gw2-abc', $response->externalId);
    }

    public function test_charge_sends_correct_portuguese_field_names(): void
    {
        Http::fake([
            '*/transacoes' => Http::response(['id' => 'ext-001'], 201),
        ]);

        $this->service->charge($this->payment);

        // Gateway2 usa campos em português: valor, nome, email, numeroCartao, cvv
        Http::assertSent(function ($request) {
            $body = $request->data();
            return isset($body['valor'])
                && isset($body['nome'])
                && isset($body['email'])
                && isset($body['numeroCartao'])
                && isset($body['cvv'])
                && $body['valor'] === 10000
                && $body['numeroCartao'] === '5569000000006063';
        });
    }

    public function test_charge_returns_failure_on_gateway_error(): void
    {
        Http::fake([
            '*/transacoes' => Http::response(['message' => 'Cartão expirado.'], 422),
        ]);

        $response = $this->service->charge($this->payment);

        $this->assertFalse($response->success);
        $this->assertEquals('Cartão expirado.', $response->errorMessage);
    }

    public function test_charge_returns_failure_on_connection_error(): void
    {
        Http::fake([
            '*/transacoes' => Http::throw(new \Exception('Connection refused')),
        ]);

        $response = $this->service->charge($this->payment);

        $this->assertFalse($response->success);
    }

    // ─── Refund ──────────────────────────────────────────────────

    public function test_refund_sends_id_in_body(): void
    {
        Http::fake([
            '*/transacoes/reembolso' => Http::response([], 200),
        ]);

        $this->service->refund('ext-gw2-999');

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/transacoes/reembolso')
                && $request->method() === 'POST'
                && $request->data()['id'] === 'ext-gw2-999';
        });
    }

    public function test_refund_returns_success(): void
    {
        Http::fake([
            '*/transacoes/reembolso' => Http::response([], 200),
        ]);

        $response = $this->service->refund('ext-gw2-999');

        $this->assertTrue($response->success);
        $this->assertEquals('ext-gw2-999', $response->externalId);
    }

    public function test_refund_sends_auth_headers(): void
    {
        Http::fake([
            '*/transacoes/reembolso' => Http::response([], 200),
        ]);

        $this->service->refund('ext-001');

        Http::assertSent(function ($request) {
            return $request->hasHeader('Gateway-Auth-Token', config('gateways.gateway2.auth_token'))
                && $request->hasHeader('Gateway-Auth-Secret', config('gateways.gateway2.auth_secret'));
        });
    }

    public function test_refund_returns_failure_when_gateway_rejects(): void
    {
        Http::fake([
            '*/transacoes/reembolso' => Http::response(['message' => 'ID não encontrado.'], 404),
        ]);

        $response = $this->service->refund('ext-inexistente');

        $this->assertFalse($response->success);
        $this->assertEquals('ID não encontrado.', $response->errorMessage);
    }
}
