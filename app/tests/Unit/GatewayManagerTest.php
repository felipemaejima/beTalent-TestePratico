<?php

namespace Tests\Unit;

use App\Contracts\GatewayInterface;
use App\DTOs\GatewayResponseDTO;
use App\DTOs\PaymentDTO;
use App\Models\Gateway;
use App\Services\GatewayManager;
use App\Services\Gateways\Gateway1Service;
use App\Services\Gateways\Gateway2Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class GatewayManagerTest extends TestCase
{
    use RefreshDatabase;

    private PaymentDTO $payment;

    protected function setUp(): void
    {
        parent::setUp();

        $this->payment = new PaymentDTO(
            amount:        10000,
            customerName:  'Teste',
            customerEmail: 'teste@example.com',
            cardNumber:    '5569000000006063',
            cvv:           '010',
        );
    }

    public function test_charge_succeeds_on_first_gateway(): void
    {
        $gateway = Gateway::factory()->create(['name' => 'Gateway1', 'priority' => 1, 'is_active' => true]);

        $mockService = $this->createMock(GatewayInterface::class);
        $mockService->method('charge')
            ->willReturn(GatewayResponseDTO::success('ext-001'));

        $this->app->bind(Gateway1Service::class, fn () => $mockService);

        $manager = $this->app->make(GatewayManager::class);
        $result  = $manager->charge($this->payment);

        $this->assertTrue($result['response']->success);
        $this->assertEquals('ext-001', $result['response']->externalId);
        $this->assertEquals($gateway->id, $result['gateway']->id);
    }

    public function test_charge_falls_back_to_second_gateway_when_first_fails(): void
    {
        Gateway::factory()->create(['name' => 'Gateway1', 'priority' => 1, 'is_active' => true]);
        $gateway2 = Gateway::factory()->create(['name' => 'Gateway2', 'priority' => 2, 'is_active' => true]);

        $failingService = $this->createMock(GatewayInterface::class);
        $failingService->method('charge')
            ->willReturn(GatewayResponseDTO::failure('Cartão recusado.'));

        $successService = $this->createMock(GatewayInterface::class);
        $successService->method('charge')
            ->willReturn(GatewayResponseDTO::success('ext-from-gw2'));

        $this->app->bind(Gateway1Service::class, fn () => $failingService);
        $this->app->bind(Gateway2Service::class, fn () => $successService);

        $manager = $this->app->make(GatewayManager::class);
        $result  = $manager->charge($this->payment);

        $this->assertTrue($result['response']->success);
        $this->assertEquals('ext-from-gw2', $result['response']->externalId);
        $this->assertEquals($gateway2->id, $result['gateway']->id);
    }

    public function test_charge_throws_exception_when_all_gateways_fail(): void
    {
        Gateway::factory()->create(['name' => 'Gateway1', 'priority' => 1, 'is_active' => true]);
        Gateway::factory()->create(['name' => 'Gateway2', 'priority' => 2, 'is_active' => true]);

        $failingService = $this->createMock(GatewayInterface::class);
        $failingService->method('charge')
            ->willReturn(GatewayResponseDTO::failure('Sem fundos.'));

        $this->app->bind(Gateway1Service::class, fn () => $failingService);
        $this->app->bind(Gateway2Service::class, fn () => $failingService);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/Todos os gateways falharam/');

        $this->app->make(GatewayManager::class)->charge($this->payment);
    }

    public function test_charge_throws_exception_when_no_active_gateways(): void
    {
        Gateway::factory()->inactive()->create(['name' => 'Gateway1']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Nenhum gateway de pagamento ativo disponível.');

        $this->app->make(GatewayManager::class)->charge($this->payment);
    }

    public function test_charge_skips_inactive_gateways(): void
    {
        Gateway::factory()->inactive()->create(['name' => 'Gateway1', 'priority' => 1]);
        $gateway2 = Gateway::factory()->create(['name' => 'Gateway2', 'priority' => 2, 'is_active' => true]);

        $successService = $this->createMock(GatewayInterface::class);
        $successService->method('charge')
            ->willReturn(GatewayResponseDTO::success('ext-gw2-only'));

        $this->app->bind(Gateway2Service::class, fn () => $successService);

        $manager = $this->app->make(GatewayManager::class);
        $result  = $manager->charge($this->payment);

        $this->assertTrue($result['response']->success);
        $this->assertEquals($gateway2->id, $result['gateway']->id);
    }

    public function test_refund_calls_correct_gateway(): void
    {
        $mockService = $this->createMock(GatewayInterface::class);
        $mockService->expects($this->once())
            ->method('refund')
            ->with('ext-999')
            ->willReturn(GatewayResponseDTO::success('ext-999'));

        $this->app->bind(Gateway1Service::class, fn () => $mockService);

        $manager  = $this->app->make(GatewayManager::class);
        $response = $manager->refund('Gateway1', 'ext-999');

        $this->assertTrue($response->success);
    }

    public function test_payment_dto_extracts_card_last_numbers(): void
    {
        $this->assertEquals('6063', $this->payment->cardLastNumbers());
    }
}
