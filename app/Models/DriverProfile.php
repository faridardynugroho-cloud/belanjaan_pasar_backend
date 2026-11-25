<?php

namespace App\Models;

use App\Enums\ApprovalStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;

class DriverProfile extends Model
{
    use HasFactory;

    // Status pendaftaran: pending (menunggu diperiksa), revision (diminta perbaiki dokumen), approved, rejected
    public const APPROVAL_PENDING = 'pending';

    public const APPROVAL_REVISION = 'revision';

    public const APPROVAL_APPROVED = 'approved';

    public const APPROVAL_REJECTED = 'rejected';

    protected $primaryKey = 'driver_profile_id';

    protected $fillable = [
        'user_id',
        'domicile_address',
        'is_available',
        'rating_avg',
        'rating_count',
        'approval_status',
        'rejection_reason',
        'reviewed_at',
        'reviewed_by',
    ];

    protected function casts(): array
    {
        return [
            'is_available' => 'boolean',
            'reviewed_at' => 'datetime',
            'rating_avg' => 'decimal:2',
            'rating_count' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    public function isApproved(): bool
    {
        return $this->approval_status === ApprovalStatus::Approved->value;
    }

    public function location(): HasOneThrough
    {
        return $this->hasOneThrough(
            DriverLocation::class,
            User::class,
            'user_id',
            'user_id',
            'user_id',
            'user_id',
        );
    }

    public function scopeAvailable($query)
    {
        return $query->where('is_available', true);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(DriverReview::class, 'driver_profile_id', 'driver_profile_id');
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by', 'user_id');
    }

    public function vehicle(): HasOne
    {
        return $this->hasOne(DriverVehicle::class, 'driver_profile_id', 'driver_profile_id');
    }

    public function document(): HasOne
    {
        return $this->hasOne(DriverDocument::class, 'driver_profile_id', 'driver_profile_id');
    }

    public function documentChecks(): HasMany
    {
        return $this->hasMany(DriverDocumentCheck::class, 'driver_profile_id', 'driver_profile_id');
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(Delivery::class, 'driver_profile_id', 'driver_profile_id');
    }

    public function violations(): HasMany
    {
        return $this->hasMany(DriverViolation::class, 'driver_profile_id', 'driver_profile_id');
    }

    public function sanctions(): HasMany
    {
        return $this->hasMany(DriverSanction::class, 'driver_profile_id', 'driver_profile_id');
    }

    /** Sanksi yang sedang memegang driver (berlaku atau menunggu mulai). Maksimal satu, dijaga DriverSanctionService. */
    public function currentSanction(): HasOne
    {
        return $this->hasOne(DriverSanction::class, 'driver_profile_id', 'driver_profile_id')
            ->ofMany('driver_sanction_id', 'max', fn ($query) => $query->open());
    }

    /** Total poin aktif: pelanggaran divalidasi yang belum kedaluwarsa. Tidak disimpan, selalu dihitung. */
    public function activePoints(): int
    {
        return (int) $this->violations()->withActivePoints()->sum('points');
    }
}
