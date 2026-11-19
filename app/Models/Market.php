<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Market extends Model
{
    protected $primaryKey = 'market_id';

    protected $fillable = [
        'name',
        'address',
        'latitude',
        'longitude',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'is_active' => 'boolean',
        ];
    }

    public function marketProducts(): HasMany
    {
        return $this->hasMany(MarketProduct::class, 'market_id', 'market_id');
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'market_products', 'market_id', 'product_id')
            ->withPivot(['market_product_id', 'price', 'stock', 'is_available'])
            ->withTimestamps();
    }

    public function carts(): HasMany
    {
        return $this->hasMany(Cart::class, 'market_id', 'market_id');
    }

    public function shoppingRequests(): HasMany
    {
        return $this->hasMany(ShoppingRequest::class, 'market_id', 'market_id');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'market_id', 'market_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeNearestTo(Builder $query, float $latitude, float $longitude): Builder
    {
        $distance = '(6371 * ACOS(LEAST(1, GREATEST(-1,
            COS(RADIANS(?)) * COS(RADIANS(latitude)) * COS(RADIANS(longitude) - RADIANS(?))
            + SIN(RADIANS(?)) * SIN(RADIANS(latitude))
        ))))';

        return $query
            ->select($this->getTable() . '.*')
            ->selectRaw("$distance AS distance_km", [$latitude, $longitude, $latitude])
            ->orderBy('distance_km');
    }
}
