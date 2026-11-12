<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DriverProfile extends Model
{
    protected $primaryKey = 'driver_profile_id';

    protected $fillable = [
        'user_id',
        'vehicle_type',
        'vehicle_plate',
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
}
