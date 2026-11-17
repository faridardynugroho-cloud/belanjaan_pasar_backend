<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DriverVehicle extends Model
{
    protected $primaryKey = 'driver_vehicle_id';

    protected $fillable = [
        'driver_profile_id',
        'vehicle_type',
        'vehicle_brand',
        'vehicle_model',
        'vehicle_plate',
        'vehicle_color',
        'production_year',
        'vehicle_photo',
        'stnk_photo',
    ];

    protected function casts(): array
    {
        return [
            'production_year' => 'integer',
        ];
    }

    public function driverProfile(): BelongsTo
    {
        return $this->belongsTo(DriverProfile::class, 'driver_profile_id', 'driver_profile_id');
    }
}