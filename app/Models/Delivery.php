<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Delivery extends Model
{
    // Status turunan dari started_at / completed_at / cancelled_at (tidak ada kolom status)
    public const STATUS_PENDING = 'pending';
    public const STATUS_ON_THE_WAY = 'on_the_way';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';

    /** Batas satu perjalanan. */
    public const MAX_ORDERS = 3;
    public const MAX_WEIGHT_GRAMS = 50000;

    protected $primaryKey = 'delivery_id';

    protected $fillable = [
        'driver_profile_id',
        'delivery_number',
        'started_at',
        'completed_at',
        'cancelled_at',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    protected function status(): Attribute
    {
        return Attribute::get(fn () => match (true) {
            $this->cancelled_at !== null => self::STATUS_CANCELLED,
            $this->completed_at !== null => self::STATUS_COMPLETED,
            $this->started_at !== null => self::STATUS_ON_THE_WAY,
            default => self::STATUS_PENDING,
        });
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }


    public function driver(): BelongsTo
    {
        return $this->belongsTo(DriverProfile::class, 'driver_profile_id', 'driver_profile_id');
    }

    public function deliveryOrders(): HasMany
    {
        return $this->hasMany(DeliveryOrder::class, 'delivery_id', 'delivery_id');
    }

    /** Semua pesanan yang pernah masuk perjalanan ini (termasuk yang ditolak). */
    public function orders(): BelongsToMany
    {
        return $this->belongsToMany(Order::class, 'delivery_orders', 'delivery_id', 'order_id')
            ->withPivot(['delivery_order_id', 'status', 'assigned_at'])
            ->withTimestamps();
    }

    /** Pesanan yang masih dihitung di perjalanan ini (yang ditolak tidak dihitung). */
    public function activeOrders(): BelongsToMany
    {
        return $this->orders()->wherePivotNotIn('status', [DeliveryOrder::STATUS_REJECTED]);
    }

    /** Perjalanan yang belum selesai dan belum dibatalkan (pending atau on_the_way). */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('completed_at')->whereNull('cancelled_at');
    }

    public function scopeWithStatus(Builder $query, string $status): Builder
    {
        return match ($status) {
            self::STATUS_PENDING => $query->active()->whereNull('started_at'),
            self::STATUS_ON_THE_WAY => $query->active()->whereNotNull('started_at'),
            self::STATUS_COMPLETED => $query->whereNotNull('completed_at'),
            self::STATUS_CANCELLED => $query->whereNotNull('cancelled_at'),
            default => $query,
        };
    }

    /** @return array{orders: int, grams: int} */
    public function muatan(): array
    {
        $orders = $this->activeOrders;

        return [
            'orders' => $orders->count(),
            'grams' => (int) $orders->sum('total_weight_grams'),
        ];
    }

    public function totalWeightGrams(): int
    {
        return $this->muatan()['grams'];
    }

    /** Apakah pesanan ini masih bisa ditambahkan ke perjalanan ini? */
    public function canAccept(Order $order): bool
    {
        $muatan = $this->muatan();

        return $this->isPending()
            && $muatan['orders'] < self::MAX_ORDERS
            && ($muatan['grams'] + $order->total_weight_grams) <= self::MAX_WEIGHT_GRAMS;
    }
}