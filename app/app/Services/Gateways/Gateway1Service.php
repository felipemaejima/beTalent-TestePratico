<?php

namespace App\Services\Gateways;

use App\Contracts\GatewayInterface;
use App\DTOs\GatewayResponseDTO;
use App\DTOs\PaymentDTO;
use Illuminate\Support\Facades\Cache;
use Throwable;

class Gateway1Service extends AbstractGatewayService implements GatewayInterface
{
    private const GATEWAY_NAME = 'Gateway1';
    private const TOKEN_CACHE_KEY = 'gateway1_token';
    private const TOKEN_TTL_SECONDS = 30;

    protected function baseUrl(): string
    {
        return config('gateways.gateway1.url');
    }

    public function charge(PaymentDTO $payment): GatewayResponseDTO
    {
        try {
            $token = $this->getToken();

            $response = $this->http
                ->withToken($token)
                ->post('/transactions', [
                    'amount' => $payment->amount,
                    'name' => $payment->customerName,
                    'email' => $payment->customerEmail,
                    'cardNumber' => $payment->cardNumber,
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
            $token = $this->getToken();

            $response = $this->http
                ->withToken($token)
                ->post("/transactions/{$externalId}/charge_back");

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
     * Obtém o Bearer token do Gateway1.
     * Armazena em cache para evitar logins desnecessários.
     */
    private function getToken(): string
    {
        return Cache::remember(self::TOKEN_CACHE_KEY, self::TOKEN_TTL_SECONDS, function () {
            $response = $this->http->post('/login', [
                'email' => config('gateways.gateway1.email'),
                'token' => config('gateways.gateway1.token'),
            ]);

            if (!$response->successful()) {
                throw new \RuntimeException('Falha ao autenticar no Gateway1.');
            }

            return $response->json('token');
        });
    }
}
