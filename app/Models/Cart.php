<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Cart extends Model
{
    public const STATUS_ACTIVE = 'active';
    public const STATUS_CHECKED_OUT = 'checked_out';
    public const STATUS_ABANDONED = 'abandoned';

    protected $primaryKey = 'cart_id';

    protected $fillable = [
        'user_id',
        'market_id',
        'status',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    public function market(): BelongsTo
    {
        return $this->belongsTo(Market::class, 'market_id', 'market_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(CartItem::class, 'cart_id', 'cart_id');
    }

    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    /** Total harga isi keranjang (butuh relasi items.marketProduct). */
    public function getSubtotalAttribute(): string
    {
        $this->loadMissing('items.marketProduct');

        return (string) $this->items->sum(
            fn (CartItem $item) => (float) $item->quantity * (float) $item->marketProduct->price
        );
    }
}
