<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

class TransactionProduct extends Pivot
{
    protected $table = 'transaction_products';

    protected $fillable = [
        'transaction_id',
        'product_id',
        'quantity',
        'unit_amount',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_amount' => 'integer',
        ];
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function subtotal(): int
    {
        return $this->unit_amount * $this->quantity;
    }
}
