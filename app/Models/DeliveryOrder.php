<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class DeliveryOrder extends Model
{
    public const STATUS_PENDING = 'pending';       // ditawarkan, belum dijawab driver
    public const STATUS_ACCEPTED = 'accepted';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_ON_THE_WAY = 'on_the_way';
    public const STATUS_COMPLETED = 'completed';

    /** Status yang masih "memegang" pesanan: pesanan ini tidak boleh ditawarkan ke perjalanan lain. */
    public const ACTIVE_STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_ACCEPTED,
        self::STATUS_ON_THE_WAY,
    ];

    protected $primaryKey = 'delivery_order_id';

    protected $fillable = [
        'delivery_id',
        'order_id',
        'status',
        'rejection_reason',
        'assigned_at',
        'accepted_at',
        'rejected_at',
        'started_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'assigned_at' => 'datetime',
            'accepted_at' => 'datetime',
            'rejected_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function delivery(): BelongsTo
    {
        return $this->belongsTo(Delivery::class, 'delivery_id', 'delivery_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id', 'order_id');
    }

    public function review(): HasOne
    {
        return $this->hasOne(DriverReview::class, 'delivery_order_id', 'delivery_order_id');
    }

    public function violations(): HasMany
    {
        return $this->hasMany(DriverViolation::class, 'delivery_order_id', 'delivery_order_id');
    }
}
