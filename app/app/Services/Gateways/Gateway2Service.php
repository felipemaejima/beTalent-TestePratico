<?php

namespace App\Services\Gateways;

use App\Contracts\GatewayInterface;
use App\DTOs\GatewayResponseDTO;
use App\DTOs\PaymentDTO;
use Throwable;

class Gateway2Service extends AbstractGatewayService implements GatewayInterface
{
    private const GATEWAY_NAME = 'Gateway2';

    protected function baseUrl(): string
    {
        return config('gateways.gateway2.url');
    }

    public function charge(PaymentDTO $payment): GatewayResponseDTO
    {
        try {
            $response = $this->authenticatedRequest()
                ->post('/transacoes', [
                    'valor' => $payment->amount,
                    'nome' => $payment->customerName,
                    'email' => $payment->customerEmail,
                    'numeroCartao' => $payment->cardNumber,
                    'cvv' => $payment->cvv,
                ]);

            if ($response->successful()) {
                return GatewayResponseDTO::success($response->json('id'));
            }

            return GatewayResponseDTO::failure($response->json('message') ?? 'Erro desconhecido.');

        } catch (Throwable $e) {
            $this->logError(self::GATEWAY_NAME, 'charge', $e);
            return GatewayResponseDTO::failure($e->getMessage());
        }
    }

    public function refund(string $externalId): GatewayResponseDTO
    {
        try {
            $response = $this->authenticatedRequest()
                ->post('/transacoes/reembolso', [
                    'id' => $externalId,
                ]);

            if ($response->successful()) {
                return GatewayResponseDTO::success($externalId);
            }

            return GatewayResponseDTO::failure($response->json('message') ?? 'Erro ao reembolsar.');

        } catch (Throwable $e) {
            $this->logError(self::GATEWAY_NAME, 'refund', $e);
            return GatewayResponseDTO::failure($e->getMessage());
        }
    }

    /**
     * Gateway2 usa headers estáticos para autenticação.
     */
    private function authenticatedRequest()
    {
        return $this->http->withHeaders([
            'Gateway-Auth-Token' => config('gateways.gateway2.auth_token'),
            'Gateway-Auth-Secret' => config('gateways.gateway2.auth_secret'),
        ]);
    }
}
