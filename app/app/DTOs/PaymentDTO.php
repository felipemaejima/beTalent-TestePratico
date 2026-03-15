<?php

namespace App\DTOs;

class PaymentDTO
{
    public function __construct(
        public readonly int $amount,
        public readonly string $customerName,
        public readonly string $customerEmail,
        public readonly string $cardNumber,
        public readonly string $cvv,
    ) {
    }

    public static function fromArray(array $data, int $amount): self
    {
        return new self(
            amount: $amount,
            customerName: $data['customer_name'],
            customerEmail: $data['customer_email'],
            cardNumber: $data['card_number'],
            cvv: $data['cvv'],
        );
    }

    public function cardLastNumbers(): string
    {
        return substr($this->cardNumber, -4);
    }
}
