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
            amount: 10000,
            customerName: 'Teste',
            customerEmail: 'teste@example.com',
            cardNumber: '5569000000006063',
            cvv: '010',
        );
    }

    public function test_charge_succeeds_on_first_gateway(): void
    {
        $gateway = Gateway::factory()->create(['name' => 'Gateway1', 'priority' => 1, 'is_active' => true]);

        $mockService = $this->createMock(GatewayInterface::class);
        $mockService->method('charge')->willReturn(GatewayResponseDTO::success('ext-001'));

        $this->app->bind(Gateway1Service::class, fn() => $mockService);

        $result = $this->app->make(GatewayManager::class)->charge($this->payment);

        $this->assertTrue($result['response']->success);
        $this->assertEquals('ext-001', $result['response']->externalId);
        $this->assertEquals($gateway->id, $result['gateway']->id);
    }

    public function test_charge_falls_back_to_second_gateway_when_first_fails(): void
    {
        Gateway::factory()->create(['name' => 'Gateway1', 'priority' => 1, 'is_active' => true]);
        $gateway2 = Gateway::factory()->create(['name' => 'Gateway2', 'priority' => 2, 'is_active' => true]);

        $failing = $this->createMock(GatewayInterface::class);
        $failing->method('charge')->willReturn(GatewayResponseDTO::failure('Cartão recusado.'));

        $success = $this->createMock(GatewayInterface::class);
        $success->method('charge')->willReturn(GatewayResponseDTO::success('ext-from-gw2'));

        $this->app->bind(Gateway1Service::class, fn() => $failing);
        $this->app->bind(Gateway2Service::class, fn() => $success);

        $result = $this->app->make(GatewayManager::class)->charge($this->payment);

        $this->assertTrue($result['response']->success);
        $this->assertEquals('ext-from-gw2', $result['response']->externalId);
        $this->assertEquals($gateway2->id, $result['gateway']->id);
    }

    public function test_charge_throws_when_all_gateways_fail(): void
    {
        Gateway::factory()->create(['name' => 'Gateway1', 'priority' => 1, 'is_active' => true]);
        Gateway::factory()->create(['name' => 'Gateway2', 'priority' => 2, 'is_active' => true]);

        $failing = $this->createMock(GatewayInterface::class);
        $failing->method('charge')->willReturn(GatewayResponseDTO::failure('Sem fundos.'));

        $this->app->bind(Gateway1Service::class, fn() => $failing);
        $this->app->bind(Gateway2Service::class, fn() => $failing);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/Todos os gateways falharam/');

        $this->app->make(GatewayManager::class)->charge($this->payment);
    }

    public function test_charge_throws_when_no_active_gateways_exist(): void
    {
        Gateway::factory()->inactive()->create(['name' => 'Gateway1']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Nenhum gateway de pagamento ativo disponível.');

        $this->app->make(GatewayManager::class)->charge($this->payment);
    }

    public function test_charge_skips_inactive_gateways_and_uses_next(): void
    {
        Gateway::factory()->inactive()->create(['name' => 'Gateway1', 'priority' => 1]);
        $gateway2 = Gateway::factory()->create(['name' => 'Gateway2', 'priority' => 2, 'is_active' => true]);

        $success = $this->createMock(GatewayInterface::class);
        $success->method('charge')->willReturn(GatewayResponseDTO::success('ext-gw2-only'));

        $this->app->bind(Gateway2Service::class, fn() => $success);

        $result = $this->app->make(GatewayManager::class)->charge($this->payment);

        $this->assertTrue($result['response']->success);
        $this->assertEquals($gateway2->id, $result['gateway']->id);
    }

    public function test_charge_skips_gateway_without_registered_implementation(): void
    {
        // Gateway com nome não mapeado no GATEWAY_MAP
        Gateway::factory()->create(['name' => 'GatewayDesconhecido', 'priority' => 1, 'is_active' => true]);
        $gateway2 = Gateway::factory()->create(['name' => 'Gateway2', 'priority' => 2, 'is_active' => true]);

        $success = $this->createMock(GatewayInterface::class);
        $success->method('charge')->willReturn(GatewayResponseDTO::success('ext-fallback'));

        $this->app->bind(Gateway2Service::class, fn() => $success);

        $result = $this->app->make(GatewayManager::class)->charge($this->payment);

        $this->assertTrue($result['response']->success);
        $this->assertEquals($gateway2->id, $result['gateway']->id);
    }


    public function test_refund_calls_correct_gateway_service(): void
    {
        $mockService = $this->createMock(GatewayInterface::class);
        $mockService->expects($this->once())
            ->method('refund')
            ->with('ext-999')
            ->willReturn(GatewayResponseDTO::success('ext-999'));

        $this->app->bind(Gateway1Service::class, fn() => $mockService);

        $response = $this->app->make(GatewayManager::class)->refund('Gateway1', 'ext-999');

        $this->assertTrue($response->success);
        $this->assertEquals('ext-999', $response->externalId);
    }

    public function test_refund_throws_when_gateway_has_no_implementation(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/não possui implementação registrada/');

        $this->app->make(GatewayManager::class)->refund('GatewayInexistente', 'ext-000');
    }


    public function test_payment_dto_extracts_last_four_card_digits(): void
    {
        $this->assertEquals('6063', $this->payment->cardLastNumbers());
    }

    public function test_payment_dto_from_array_maps_fields_correctly(): void
    {
        $data = [
            'customer_name' => 'Ana Lima',
            'customer_email' => 'ana@example.com',
            'card_number' => '4111111111111111',
            'cvv' => '123',
        ];

        $dto = PaymentDTO::fromArray($data, 7500);

        $this->assertEquals(7500, $dto->amount);
        $this->assertEquals('Ana Lima', $dto->customerName);
        $this->assertEquals('ana@example.com', $dto->customerEmail);
        $this->assertEquals('4111111111111111', $dto->cardNumber);
        $this->assertEquals('123', $dto->cvv);
        $this->assertEquals('1111', $dto->cardLastNumbers());
    }
}
