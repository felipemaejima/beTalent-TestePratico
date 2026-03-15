<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained('clients');
            $table->foreignId('gateway_id')->constrained('gateways');
            $table->string('external_id')->nullable();
            $table->enum('status', ['pending', 'paid', 'refunded', 'failed'])->default('pending');
            $table->unsignedInteger('amount');
            $table->string('card_last_numbers', 4);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
