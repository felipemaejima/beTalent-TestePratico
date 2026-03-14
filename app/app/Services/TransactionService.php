<?php

namespace App\Services;

use App\DTOs\PaymentDTO;
use App\Models\Client;
use App\Models\Product;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class TransactionService
{
    public function __construct(
        private readonly GatewayManager $gatewayManager,
    ) {
    }

    public function purchase(array $data): Transaction
    {
        $product = Product::findOrFail($data['product_id']);

        // Nível 2: valor calculado pelo back-end (quantidade × preço do produto)
        $amount = $product->amount * $data['quantity'];

        $payment = PaymentDTO::fromArray($data, $amount);

        // Tenta os gateways — lança RuntimeException se todos falharem
        ['response' => $response, 'gateway' => $gateway] = $this->gatewayManager->charge($payment);

        return DB::transaction(function () use ($data, $payment, $response, $gateway, $product, $amount) {
            $client = $this->firstOrCreateClient(
                $data['customer_email'],
                $data['customer_name'],
            );

            $transaction = Transaction::create([
                'client_id' => $client->id,
                'gateway_id' => $gateway->id,
                'external_id' => $response->externalId,
                'status' => 'paid',
                'amount' => $amount,
                'card_last_numbers' => $payment->cardLastNumbers(),
            ]);

            // Associa o produto com snapshot do preço atual
            $transaction->products()->attach($product->id, [
                'quantity' => $data['quantity'],
                'unit_amount' => $product->amount,
            ]);

            return $transaction->load(['client', 'gateway', 'products']);
        });
    }

    public function refund(Transaction $transaction): Transaction
    {
        if (!$transaction->isPaid()) {
            throw new RuntimeException(
                "Apenas transações pagas podem ser reembolsadas."
            );
        }

        $response = $this->gatewayManager->refund(
            $transaction->gateway->name,
            $transaction->external_id,
        );

        if (!$response->success) {
            throw new RuntimeException(
                'Falha ao reembolsar junto ao gateway: ' . $response->errorMessage
            );
        }

        $transaction->update(['status' => 'refunded']);

        return $transaction->fresh(['client', 'gateway', 'products']);
    }

    // ─── Helpers ─────────────────────────────────────────────────

    /**
     * Recupera ou cria o cliente pelo e-mail.
     * Garante que o mesmo cliente não gere registros duplicados.
     */
    private function firstOrCreateClient(string $email, string $name): Client
    {
        return Client::firstOrCreate(
            ['email' => $email],
            ['name' => $name],
        );
    }
}
