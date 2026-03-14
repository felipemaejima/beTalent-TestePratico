<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTransactionRequest;
use App\Models\Transaction;
use App\Services\TransactionService;
use Illuminate\Http\JsonResponse;
use RuntimeException;

class TransactionController extends Controller
{
    public function __construct(
        private readonly TransactionService $transactionService,
    ) {
    }

    public function index(): JsonResponse
    {
        $transactions = Transaction::with(['client', 'gateway', 'products'])
            ->latest()
            ->paginate(20);

        return response()->json($transactions);
    }

    public function show(Transaction $transaction): JsonResponse
    {
        return response()->json(
            $transaction->load(['client', 'gateway', 'products'])
        );
    }

    public function store(StoreTransactionRequest $request): JsonResponse
    {
        try {
            $transaction = $this->transactionService->purchase($request->validated());

            return response()->json($transaction, 201);

        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function refund(Transaction $transaction): JsonResponse
    {
        try {
            $refunded = $this->transactionService->refund($transaction);

            return response()->json($refunded);

        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }
}
