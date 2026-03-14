<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateGatewayPriorityRequest;
use App\Models\Gateway;
use Illuminate\Http\JsonResponse;

class GatewayController extends Controller
{

    public function toggle(Gateway $gateway): JsonResponse
    {
        $gateway->update(['is_active' => !$gateway->is_active]);

        return response()->json([
            'message' => 'Status do gateway atualizado.',
            'gateway' => $gateway->fresh(),
        ]);
    }

    public function updatePriority(UpdateGatewayPriorityRequest $request, Gateway $gateway): JsonResponse
    {
        $gateway->update(['priority' => $request->validated('priority')]);

        return response()->json([
            'message' => 'Prioridade do gateway atualizada.',
            'gateway' => $gateway->fresh(),
        ]);
    }
}
