<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class OrderItem extends Model
{
    protected $primaryKey = 'order_item_id';

    protected $fillable = [
        'order_id',
        'market_product_id',
        'shopping_request_item_id',
        'product_name',
        'unit',
        'quantity',
        'unit_price',
        'original_price',
        'discount_amount',
        'product_discount_id',
        'subtotal',
        'weight_grams',
        'total_weight_grams',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:2',
            'unit_price' => 'decimal:2',
            'original_price' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'subtotal' => 'decimal:2',
            'weight_grams' => 'integer',
            'total_weight_grams' => 'integer',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id', 'order_id');
    }

    public function review(): HasOne
    {
        return $this->hasOne(ProductReview::class, 'order_item_id', 'order_item_id');
    }

    /** Terisi jika barang berasal dari katalog marketplace. */
    public function marketProduct(): BelongsTo
    {
        return $this->belongsTo(MarketProduct::class, 'market_product_id', 'market_product_id');
    }

    public function productDiscount(): BelongsTo
    {
        return $this->belongsTo(ProductDiscount::class, 'product_discount_id', 'product_discount_id');
    }

    /** Terisi jika barang berasal dari hasil belanja Ngebelanjain. */
    public function shoppingRequestItem(): BelongsTo
    {
        return $this->belongsTo(ShoppingRequestItem::class, 'shopping_request_item_id', 'shopping_request_item_id');
    }
}
