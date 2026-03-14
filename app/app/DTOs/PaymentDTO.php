<?php

namespace App\DTOs;

class PaymentDTO
{
    public function __construct(
        public readonly int    $amount,        // em centavos
        public readonly string $customerName,
        public readonly string $customerEmail,
        public readonly string $cardNumber,
        public readonly string $cvv,
    ) {}

    /**
     * Cria o DTO a partir dos dados validados da request e do total calculado.
     */
    public static function fromArray(array $data, int $amount): self
    {
        return new self(
            amount:        $amount,
            customerName:  $data['customer_name'],
            customerEmail: $data['customer_email'],
            cardNumber:    $data['card_number'],
            cvv:           $data['cvv'],
        );
    }

    /**
     * Retorna os 4 últimos dígitos do cartão para armazenar na transação.
     */
    public function cardLastNumbers(): string
    {
        return substr($this->cardNumber, -4);
    }
}
