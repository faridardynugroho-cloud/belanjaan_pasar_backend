<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Delivery extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_ON_THE_WAY = 'on_the_way';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';

    /** Batas satu perjalanan. */
    public const MAX_ORDERS = 3;
    public const MAX_WEIGHT_GRAMS = 50000;

    protected $primaryKey = 'delivery_id';

    protected $fillable = [
        'driver_id',
        'delivery_number',
        'status',
        'started_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    /** User dengan role driver. */
    public function driver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'driver_id', 'user_id');
    }

    public function deliveryOrders(): HasMany
    {
        return $this->hasMany(DeliveryOrder::class, 'delivery_id', 'delivery_id');
    }

    public function orders(): BelongsToMany
    {
        return $this->belongsToMany(Order::class, 'delivery_orders', 'delivery_id', 'order_id')
            ->withPivot(['delivery_order_id', 'assigned_at'])
            ->withTimestamps();
    }

    public function scopeActive($query)
    {
        return $query->whereIn('status', [self::STATUS_PENDING, self::STATUS_ON_THE_WAY]);
    }

    public function totalWeightGrams(): int
    {
        return (int) $this->orders()->sum('total_weight_grams');
    }

    /** Apakah pesanan ini masih muat di perjalanan ini (maks 3 pesanan, 50 kg)? */
    public function canAccept(Order $order): bool
    {
        return $this->orders()->count() < self::MAX_ORDERS
            && ($this->totalWeightGrams() + $order->total_weight_grams) <= self::MAX_WEIGHT_GRAMS;
    }
}
