<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DriverDocument extends Model
{
    use HasFactory;

    protected $primaryKey = 'driver_document_id';

    protected $fillable = [
        'driver_profile_id',
        'nik',
        'ktp_photo',
        'selfie_photo',
        'sim_number',
        'sim_photo',
        'sim_expired_at',
    ];

    protected $hidden = [
        'nik',
        'ktp_photo',
        'selfie_photo',
        'sim_number',
        'sim_photo',
    ];

    protected function casts(): array
    {
        return [
            'sim_expired_at' => 'date',
        ];
    }

    public function driverProfile(): BelongsTo
    {
        return $this->belongsTo(DriverProfile::class, 'driver_profile_id', 'driver_profile_id');
    }
}
