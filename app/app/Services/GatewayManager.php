<?php

namespace App\Services;

use App\Contracts\GatewayInterface;
use App\DTOs\GatewayResponseDTO;
use App\DTOs\PaymentDTO;
use App\Models\Gateway;
use App\Services\Gateways\Gateway1Service;
use App\Services\Gateways\Gateway2Service;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class GatewayManager
{
    /**
     * Mapa entre o nome cadastrado no banco e a classe de serviço correspondente.
     * Para adicionar um novo gateway: cadastre no banco e adicione aqui.
     */
    private const GATEWAY_MAP = [
        'Gateway1' => Gateway1Service::class,
        'Gateway2' => Gateway2Service::class,
    ];

    public function charge(PaymentDTO $payment): array
    {
        $gateways = Gateway::active()->get();

        if ($gateways->isEmpty()) {
            throw new RuntimeException('Nenhum gateway de pagamento ativo disponível.');
        }

        $lastError = null;

        foreach ($gateways as $gateway) {
            $service = $this->resolve($gateway->name);

            if (!$service) {
                Log::warning("Gateway '{$gateway->name}' não possui implementação registrada.");
                continue;
            }

            $response = $service->charge($payment);

            if ($response->success) {
                return [
                    'response' => $response,
                    'gateway' => $gateway,
                ];
            }

            $lastError = $response->errorMessage;
            Log::warning("Gateway '{$gateway->name}' falhou. Tentando próximo...", [
                'error' => $lastError,
            ]);
        }

        throw new RuntimeException('Todos os gateways falharam: ' . $lastError);
    }

    public function refund(string $gatewayName, string $externalId): GatewayResponseDTO
    {
        $service = $this->resolve($gatewayName);

        if (!$service) {
            throw new RuntimeException("Gateway '{$gatewayName}' não possui implementação registrada.");
        }

        return $service->refund($externalId);
    }

    private function resolve(string $gatewayName): ?GatewayInterface
    {
        $class = self::GATEWAY_MAP[$gatewayName] ?? null;

        if (!$class) {
            return null;
        }

        return app($class);
    }
}
