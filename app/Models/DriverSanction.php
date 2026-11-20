<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DriverSanction extends Model
{
    public const TYPE_TEMPORARY = 'temporary';
    public const TYPE_PERMANENT = 'permanent';
    public const TYPE_TERMINATION = 'termination';

    public const LIFT_MANUAL = 'manual';
    public const LIFT_REPLACED = 'replaced';

    // Status turunan (tidak disimpan di database), dihitung dari timestamp.
    public const STATUS_LIFTED = 'lifted';         // dicabut admin
    public const STATUS_REPLACED = 'replaced';     // diganti sanksi baru
    public const STATUS_WAITING = 'waiting';       // menunggu pesanan driver selesai
    public const STATUS_ACTIVE = 'active';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_TERMINATED = 'terminated'; // putus mitra

    protected $primaryKey = 'driver_sanction_id';

    protected $fillable = [
        'driver_profile_id',
        'type',
        'duration_minutes',
        'reason',
        'notes',
        'imposed_by',
        'driver_violation_id',
        'points_at_imposition',
        'started_at',
        'expires_at',
        'lifted_at',
        'lift_reason',
        'lifted_by',
        'lift_note',
        'replaced_by',
    ];

    protected function casts(): array
    {
        return [
            'duration_minutes' => 'integer',
            'points_at_imposition' => 'integer',
            'started_at' => 'datetime',
            'expires_at' => 'datetime',
            'lifted_at' => 'datetime',
        ];
    }

    public function driverProfile(): BelongsTo
    {
        return $this->belongsTo(DriverProfile::class, 'driver_profile_id', 'driver_profile_id');
    }

    /** Admin penjatuh sanksi. Null berarti dijatuhkan oleh sistem. */
    public function imposer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'imposed_by', 'user_id');
    }

    public function lifter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'lifted_by', 'user_id');
    }

    /** Laporan pemicu, hanya untuk sanksi otomatis dari poin. */
    public function violation(): BelongsTo
    {
        return $this->belongsTo(DriverViolation::class, 'driver_violation_id', 'driver_violation_id');
    }

    /** Sanksi baru yang menggantikan sanksi ini. */
    public function replacement(): BelongsTo
    {
        return $this->belongsTo(self::class, 'replaced_by', 'driver_sanction_id');
    }

    /** Sanksi-sanksi lama yang digantikan oleh sanksi ini. */
    public function replacedSanctions(): HasMany
    {
        return $this->hasMany(self::class, 'replaced_by', 'driver_sanction_id');
    }

    /** Sanksi yang sedang berlaku: belum dicabut, sudah mulai, belum berakhir (termasuk putus mitra). */
    public function scopeInEffect(Builder $query): Builder
    {
        return $query->whereNull('lifted_at')
            ->whereNotNull('started_at')
            ->where(fn (Builder $q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()));
    }

    /** Sanksi yang sudah dijatuhkan tapi menunggu pesanan driver selesai. */
    public function scopeWaiting(Builder $query): Builder
    {
        return $query->whereNull('lifted_at')->whereNull('started_at');
    }

    /** Sanksi yang masih "memegang" driver: berlaku atau sedang menunggu mulai. */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->where(fn (Builder $q) => $q->inEffect()->orWhere(fn (Builder $w) => $w->waiting()));
    }

    public function getStatusAttribute(): string
    {
        if ($this->lifted_at !== null) {
            return $this->lift_reason === self::LIFT_REPLACED
                ? self::STATUS_REPLACED
                : self::STATUS_LIFTED;
        }

        if ($this->started_at === null) {
            return self::STATUS_WAITING;
        }

        if ($this->type === self::TYPE_TERMINATION) {
            return self::STATUS_TERMINATED;
        }

        if ($this->expires_at === null || $this->expires_at->isFuture()) {
            return self::STATUS_ACTIVE;
        }

        return self::STATUS_EXPIRED;
    }

    public function isInEffect(): bool
    {
        return in_array($this->status, [self::STATUS_ACTIVE, self::STATUS_TERMINATED], true);
    }

    public function isImposedBySystem(): bool
    {
        return $this->imposed_by === null;
   }

}