<?php

namespace App\Services\Gateways;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

abstract class AbstractGatewayService
{
    protected PendingRequest $http;

    public function __construct()
    {
        $this->http = Http::baseUrl($this->baseUrl())
            ->timeout(15)
            ->retry(2, 500);
    }

    abstract protected function baseUrl(): string;

    protected function logError(string $gateway, string $action, \Throwable $e): void
    {
        Log::error("[{$gateway}] Falha ao executar '{$action}'", [
            'message' => $e->getMessage(),
        ]);
    }
}
