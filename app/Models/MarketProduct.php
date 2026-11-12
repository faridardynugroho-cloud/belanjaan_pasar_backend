<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MarketProduct extends Model
{
    protected $primaryKey = 'market_product_id';

    protected $fillable = [
        'market_id',
        'product_id',
        'price',
        'stock',
        'is_available',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'stock' => 'decimal:2',
            'is_available' => 'boolean',
        ];
    }

    public function market(): BelongsTo
    {
        return $this->belongsTo(Market::class, 'market_id', 'market_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id', 'product_id');
    }

    public function cartItems(): HasMany
    {
        return $this->hasMany(CartItem::class, 'market_product_id', 'market_product_id');
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class, 'market_product_id', 'market_product_id');
    }

    public function scopeAvailable($query)
    {
        return $query->where('is_available', true)->where('stock', '>', 0);
    }
}
