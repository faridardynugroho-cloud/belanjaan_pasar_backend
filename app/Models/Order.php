<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Order extends Model
{
    public const STATUS_PENDING_PAYMENT = 'pending_payment';
    public const STATUS_PAID = 'paid';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_READY_FOR_DELIVERY = 'ready_for_delivery';
    public const STATUS_ON_DELIVERY = 'on_delivery';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';

    protected $primaryKey = 'order_id';

    protected $fillable = [
        'user_id',
        'market_id',
        'shopping_request_id',
        'order_number',
        'recipient_name',
        'recipient_phone',
        'shipping_address',
        'shipping_latitude',
        'shipping_longitude',
        'subtotal',
        'delivery_fee',
        'grand_total',
        'total_weight_grams',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'shipping_latitude' => 'decimal:7',
            'shipping_longitude' => 'decimal:7',
            'subtotal' => 'decimal:2',
            'delivery_fee' => 'decimal:2',
            'grand_total' => 'decimal:2',
            'total_weight_grams' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    public function market(): BelongsTo
    {
        return $this->belongsTo(Market::class, 'market_id', 'market_id');
    }

    /** Null untuk pesanan marketplace biasa. */
    public function shoppingRequest(): BelongsTo
    {
        return $this->belongsTo(ShoppingRequest::class, 'shopping_request_id', 'shopping_request_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class, 'order_id', 'order_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class, 'order_id', 'order_id');
    }

    public function latestPayment(): HasOne
    {
        return $this->hasOne(Payment::class, 'order_id', 'order_id')->latestOfMany('payment_id');
    }

    public function deliveryOrders(): HasMany
    {
        return $this->hasMany(DeliveryOrder::class, 'order_id', 'order_id');
    }

    /** Semua pengiriman yang pernah membawa pesanan ini. */
    public function deliveries(): BelongsToMany
    {
        return $this->belongsToMany(Delivery::class, 'delivery_orders', 'order_id', 'delivery_id')
            ->withPivot(['delivery_order_id', 'status', 'assigned_at'])
            ->withTimestamps();
    }

    /** Pengiriman yang masih aktif (pending / on_the_way). */
    public function activeDelivery(): HasOne
    {
        return $this->hasOne(DeliveryOrder::class, 'order_id', 'order_id')
            ->whereIn('status', DeliveryOrder::ACTIVE_STATUSES)
            ->latestOfMany('delivery_order_id');
    }

    public function isNgebelanjain(): bool
    {
        return $this->shopping_request_id !== null;
    }

    public function isPaid(): bool
    {
        return $this->payments()->where('status', Payment::STATUS_PAID)->exists();
    }
}
