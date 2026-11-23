<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductDiscount extends Model
{
    public const TYPE_PERCENT = 'percent';
    public const TYPE_FIXED = 'fixed';

    protected $primaryKey = 'product_discount_id';

    protected $fillable = [
        'market_product_id',
        'type',
        'value',
        'starts_at',
        'ends_at',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'decimal:2',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    public function marketProduct(): BelongsTo
    {
        return $this->belongsTo(MarketProduct::class, 'market_product_id', 'market_product_id');
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class, 'product_discount_id', 'product_discount_id');
    }

    /** Diskon yang aktif dan periodenya mencakup waktu sekarang. */
    public function scopeBerlaku(Builder $query): Builder
    {
        return $query->where('is_active', true)
            ->where('starts_at', '<=', now())
            ->where('ends_at', '>=', now());
    }

    /** Potongan per unit, dibulatkan ke rupiah penuh dan tidak pernah melebihi harga. */
    public function hitungPotongan(float $harga): float
    {
        $potongan = $this->type === self::TYPE_PERCENT
            ? round($harga * (float) $this->value / 100)
            : (float) $this->value;

        return max(0, min($potongan, $harga));
    }
}