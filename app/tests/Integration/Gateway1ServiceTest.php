<?php

namespace Tests\Unit;

use App\DTOs\PaymentDTO;
use App\Services\Gateways\Gateway1Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class Gateway1ServiceTest extends TestCase
{
    use RefreshDatabase;

    private Gateway1Service $service;
    private PaymentDTO $payment;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = $this->app->make(Gateway1Service::class);

        $this->payment = new PaymentDTO(
            amount:        10000,
            customerName:  'João Silva',
            customerEmail: 'joao@example.com',
            cardNumber:    '5569000000006063',
            cvv:           '010',
        );
    }

    // ─── Auth ────────────────────────────────────────────────────

    public function test_authenticates_before_charging(): void
    {
        Http::fake([
            '*/login'        => Http::response(['token' => 'fake-bearer-token'], 200),
            '*/transactions' => Http::response(['id' => 'ext-001'], 201),
        ]);

        Cache::forget('gateway1_token');

        $this->service->charge($this->payment);

        Http::assertSent(fn ($request) => str_contains($request->url(), '/login'));
    }

    public function test_reuses_cached_token_without_new_login(): void
    {
        Cache::put('gateway1_token', 'cached-token', 3600);

        Http::fake([
            '*/transactions' => Http::response(['id' => 'ext-002'], 201),
        ]);

        $this->service->charge($this->payment);

        // Login não deve ter sido chamado
        Http::assertNotSent(fn ($request) => str_contains($request->url(), '/login'));
    }

    public function test_throws_exception_when_login_fails(): void
    {
        Http::fake([
            '*/login' => Http::response(['message' => 'Unauthorized'], 401),
        ]);

        Cache::forget('gateway1_token');

        $response = $this->service->charge($this->payment);

        print_r($response);

        $this->assertFalse($response->success);
        $this->assertStringContainsString('Falha ao autenticar no Gateway1', $response->errorMessage);
    }

    // ─── Charge ──────────────────────────────────────────────────

    public function test_charge_returns_success_with_external_id(): void
    {
        $this->fakeSuccessfulAuth();

        Http::fake([
            '*/transactions' => Http::response(['id' => 'ext-abc-123'], 201),
        ]);

        $response = $this->service->charge($this->payment);

        $this->assertTrue($response->success);
        $this->assertEquals('ext-abc-123', $response->externalId);
    }

    public function test_charge_sends_correct_field_names_to_gateway1(): void
    {
        $this->fakeSuccessfulAuth();

        Http::fake([
            '*/transactions' => Http::response(['id' => 'ext-001'], 201),
        ]);

        $this->service->charge($this->payment);

        // Gateway1 usa campos em inglês: name, email, cardNumber, cvv, amount
        Http::assertSent(function ($request) {
            $body = $request->data();
            return isset($body['amount'])
                && isset($body['name'])
                && isset($body['email'])
                && isset($body['cardNumber'])
                && isset($body['cvv'])
                && $body['amount'] === 10000
                && $body['cardNumber'] === '5569000000006063';
        });
    }

    public function test_charge_sends_bearer_token_in_header(): void
    {
        Cache::put('gateway1_token', 'my-secret-token', 3600);

        Http::fake([
            '*/transactions' => Http::response(['id' => 'ext-001'], 201),
        ]);

        $this->service->charge($this->payment);

        Http::assertSent(fn ($request) =>
            $request->hasHeader('Authorization', 'Bearer my-secret-token')
        );
    }

    public function test_charge_returns_failure_on_gateway_error(): void
    {
        $this->fakeSuccessfulAuth();

        Http::fake([
            '*/transactions' => Http::response(['message' => 'CVV inválido.'], 422),
        ]);

        $response = $this->service->charge($this->payment);

        $this->assertFalse($response->success);
        $this->assertEquals('CVV inválido.', $response->errorMessage);
    }

    public function test_charge_returns_failure_on_connection_error(): void
    {
        $this->fakeSuccessfulAuth();

        Http::fake([
            '*/transactions' => Http::throw(new \Exception('Connection refused')),
        ]);

        $response = $this->service->charge($this->payment);

        $this->assertFalse($response->success);
    }

    // ─── Refund ──────────────────────────────────────────────────

    public function test_refund_calls_correct_endpoint(): void
    {
        $this->fakeSuccessfulAuth();

        Http::fake([
            '*/transactions/ext-001/charge_back' => Http::response([], 200),
        ]);

        $response = $this->service->refund('ext-001');

        $this->assertTrue($response->success);
        $this->assertEquals('ext-001', $response->externalId);

        Http::assertSent(fn ($request) =>
            str_contains($request->url(), '/transactions/ext-001/charge_back')
            && $request->method() === 'POST'
        );
    }

    public function test_refund_returns_failure_when_gateway_rejects(): void
    {
        $this->fakeSuccessfulAuth();

        Http::fake([
            '*/transactions/*/charge_back' => Http::response(['message' => 'Transação não encontrada.'], 404),
        ]);

        $response = $this->service->refund('ext-999');

        $this->assertFalse($response->success);
        $this->assertEquals('Transação não encontrada.', $response->errorMessage);
    }

    // ─── Helper ──────────────────────────────────────────────────

    private function fakeSuccessfulAuth(): void
    {
        Cache::put('gateway1_token', 'fake-token', 3600);
    }
}
