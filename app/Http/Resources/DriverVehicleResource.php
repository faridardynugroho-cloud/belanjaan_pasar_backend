<?php

namespace App\Http\Resources;

use App\Models\DriverVehicle;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/** @mixin DriverVehicle */
class DriverVehicleResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var FilesystemAdapter $disk */
        $disk = Storage::disk('public');

        return [
            'driver_vehicle_id' => $this->driver_vehicle_id,
            'vehicle_type' => $this->vehicle_type,
            'vehicle_brand' => $this->vehicle_brand,
            'vehicle_model' => $this->vehicle_model,
            'vehicle_plate' => $this->vehicle_plate,
            'vehicle_color' => $this->vehicle_color,
            'production_year' => $this->production_year,
            'vehicle_photo_url' => $this->vehicle_photo ? $disk->url($this->vehicle_photo) : null,
            'stnk_photo_url' => $this->stnk_photo ? $disk->url($this->stnk_photo) : null,
        ];
    }
}
