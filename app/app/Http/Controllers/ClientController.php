<?php

namespace App\Http\Controllers;

use App\Models\Client;
use Illuminate\Http\JsonResponse;

class ClientController extends Controller
{
    public function index(): JsonResponse
    {
        $clients = Client::latest()->paginate(20);

        return response()->json($clients);
    }

    public function show(Client $client): JsonResponse
    {
        $client->load([
            'transactions' => fn($q) => $q->with(['gateway', 'products'])->latest(),
        ]);

        return response()->json($client);
    }
}
