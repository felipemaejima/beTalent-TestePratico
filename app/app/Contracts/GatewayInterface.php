<?php

namespace App\Contracts;

use App\DTOs\PaymentDTO;
use App\DTOs\GatewayResponseDTO;

interface GatewayInterface
{
    /**
     * Processa um pagamento no gateway externo.
     */
    public function charge(PaymentDTO $payment): GatewayResponseDTO;

    /**
     * Realiza o reembolso de uma transação no gateway externo.
     */
    public function refund(string $externalId): GatewayResponseDTO;
}
