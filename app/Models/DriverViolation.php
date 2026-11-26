<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DriverViolation extends Model
{
    public const SOURCE_CUSTOMER = 'customer';

    public const SOURCE_ADMIN = 'admin';

    public const STATUS_REPORTED = 'reported';

    public const STATUS_VALIDATED = 'validated';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_REVOKED = 'revoked';

    protected $primaryKey = 'driver_violation_id';

    protected $fillable = [
        'driver_profile_id',
        'delivery_order_id',
        'violation_type_id',
        'source',
        'reported_by',
        'description',
        'evidence_photo',
        'status',
        'points',
        'handled_by',
        'handled_at',
        'handle_note',
        'points_expires_at',
        'revoked_by',
        'revoked_at',
        'revoke_note',
    ];

    protected function casts(): array
    {
        return [
            'points' => 'integer',
            'handled_at' => 'datetime',
            'points_expires_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function driverProfile(): BelongsTo
    {
        return $this->belongsTo(DriverProfile::class, 'driver_profile_id', 'driver_profile_id');
    }

    public function deliveryOrder(): BelongsTo
    {
        return $this->belongsTo(DeliveryOrder::class, 'delivery_order_id', 'delivery_order_id');
    }

    public function violationType(): BelongsTo
    {
        return $this->belongsTo(ViolationType::class, 'violation_type_id', 'violation_type_id');
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by', 'user_id');
    }

    public function handler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by', 'user_id');
    }

    public function revoker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revoked_by', 'user_id');
    }

    /** Sanksi otomatis yang dipicu laporan ini. */
    public function sanctions(): HasMany
    {
        return $this->hasMany(DriverSanction::class, 'driver_violation_id', 'driver_violation_id');
    }

    public function scopeValidated(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_VALIDATED);
    }

    /** Poin yang masih dihitung: sudah divalidasi, belum dicabut, belum kedaluwarsa. */
    public function scopeWithActivePoints(Builder $query): Builder
    {
        return $query->validated()->where('points_expires_at', '>', now());
    }

    public function isActivePoints(): bool
    {
        return $this->status === self::STATUS_VALIDATED
            && $this->points_expires_at?->isFuture() === true;
    }
}
