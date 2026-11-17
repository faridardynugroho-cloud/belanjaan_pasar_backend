<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class DriverProfile extends Model
{
    protected $primaryKey = 'driver_profile_id';

    protected $fillable = [
        'user_id',
        'domicile_address',
        'is_available',
        'approval_status',
        'rejection_reason',
        'reviewed_at',
        'reviewed_by',
    ];

    protected function casts(): array
    {
        return [
            'is_available' => 'boolean',
            'reviewed_at' => 'datetime'
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    public function scopeAvailable($query)
    {
        return $query->where('is_available', true);
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

    public function deliveries(): HasMany
    {
        return $this->hasMany(Delivery::class, 'driver_profile_id', 'driver_profile_id');
    }
}
