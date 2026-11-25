<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

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

    public function discounts(): HasMany
    {
        return $this->hasMany(ProductDiscount::class, 'market_product_id', 'market_product_id');
    }

    /** Diskon yang sedang berlaku (kalau ada dua yang tumpang tindih, ambil yang mulainya paling baru). */
    public function activeDiscount(): HasOne
    {
        return $this->hasOne(ProductDiscount::class, 'market_product_id', 'market_product_id')
            ->berlaku()
            ->latest('starts_at');
    }

    /** Angka untuk kartu katalog: harga akhir, harga coret, hemat, dan persen badge. */
    public function infoHarga(): array
    {
        $harga = (float) $this->price;
        $potongan = $this->activeDiscount?->hitungPotongan($harga) ?? 0;

        return [
            'harga_akhir' => $harga - $potongan,
            'harga_coret' => $potongan > 0 ? $harga : null,
            'hemat' => $potongan,
            'persen' => $potongan > 0 && $harga > 0 ? (int) round($potongan / $harga * 100) : null,
        ];
    }

    public function scopeAvailable($query)
    {
        return $query->where('is_available', true)->where('stock', '>', 0);
    }
}
