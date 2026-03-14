<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\GatewayController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

// ─── Rotas Públicas ───────────────────────────────────────────────────────────

// Autenticação
Route::post('/login', [AuthController::class, 'login']);

// Realizar uma compra (público: qualquer pessoa pode comprar)
Route::post('/transactions', [TransactionController::class, 'store']);

// ─── Rotas Privadas (requer token Sanctum) ────────────────────────────────────
Route::middleware('auth:sanctum')->group(function () {

    // Logout
    Route::post('/logout', [AuthController::class, 'logout']);

    // ── Gateways ─────────────────────────────────────────────────
    // Somente admin pode gerenciar gateways
    Route::middleware('role:admin')->group(function () {
        Route::patch('/gateways/{gateway}/toggle', [GatewayController::class, 'toggle']);
        Route::patch('/gateways/{gateway}/priority', [GatewayController::class, 'updatePriority']);
    });

    // ── Usuários ─────────────────────────────────────────────────
    // Admin gerencia tudo; manager pode listar e criar
    Route::middleware('role:admin,manager')->group(function () {
        Route::get('/users', [UserController::class, 'index']);
        Route::post('/users', [UserController::class, 'store']);
        Route::get('/users/{user}', [UserController::class, 'show']);
    });

    // Somente admin pode editar/deletar usuário
    Route::middleware('role:admin')->group(function () {
        Route::put('/users/{user}', [UserController::class, 'update']);
        Route::delete('/users/{user}', [UserController::class, 'destroy']);
    });

    // ── Produtos ─────────────────────────────────────────────────
    // Admin e manager gerenciam produtos; finance pode listar
    Route::middleware('role:admin,manager,finance')->group(function () {
        Route::get('/products', [ProductController::class, 'index']);
        Route::get('/products/{product}', [ProductController::class, 'show']);
    });

    Route::middleware('role:admin,manager')->group(function () {
        Route::post('/products', [ProductController::class, 'store']);
        Route::put('/products/{product}', [ProductController::class, 'update']);
        Route::delete('/products/{product}', [ProductController::class, 'destroy']);
    });

    // ── Clientes ─────────────────────────────────────────────────
    Route::middleware('role:admin,manager,finance')->group(function () {
        Route::get('/clients', [ClientController::class, 'index']);
        Route::get('/clients/{client}', [ClientController::class, 'show']);
    });

    // ── Transações ───────────────────────────────────────────────
    Route::middleware('role:admin,manager,finance')->group(function () {
        Route::get('/transactions', [TransactionController::class, 'index']);
        Route::get('/transactions/{transaction}', [TransactionController::class, 'show']);
    });

    // Reembolso: somente admin e finance
    Route::middleware('role:admin,finance')->group(function () {
        Route::post('/transactions/{transaction}/refund', [TransactionController::class, 'refund']);
    });
});
