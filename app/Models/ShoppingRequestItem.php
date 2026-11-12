<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ShoppingRequestItem extends Model
{
    protected $primaryKey = 'shopping_request_item_id';

    protected $fillable = [
        'shopping_request_id',
        'name',
        'quantity',
        'unit',
        'unit_price',
        'subtotal',
        'weight_grams',
        'total_weight_grams',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:2',
            'unit_price' => 'decimal:2',
            'subtotal' => 'decimal:2',
            'weight_grams' => 'integer',
            'total_weight_grams' => 'integer',
        ];
    }

    public function shoppingRequest(): BelongsTo
    {
        return $this->belongsTo(ShoppingRequest::class, 'shopping_request_id', 'shopping_request_id');
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class, 'shopping_request_item_id', 'shopping_request_item_id');
    }
}
