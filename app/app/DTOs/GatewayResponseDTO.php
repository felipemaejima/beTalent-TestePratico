<?php

namespace App\DTOs;

class GatewayResponseDTO
{
    public function __construct(
        public readonly bool    $success,
        public readonly ?string $externalId = null,
        public readonly ?string $errorMessage = null,
    ) {}

    public static function success(string $externalId): self
    {
        return new self(success: true, externalId: $externalId);
    }

    public static function failure(string $errorMessage): self
    {
        return new self(success: false, errorMessage: $errorMessage);
    }
}
