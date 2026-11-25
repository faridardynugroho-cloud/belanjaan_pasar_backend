<?php

namespace App\Http\Resources;

use App\Models\DriverDocument;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/** @mixin DriverDocument */
class DriverDocumentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var FilesystemAdapter $disk */
        $disk = Storage::disk('public');

        return [
            'driver_document_id' => $this->driver_document_id,
            'nik' => $this->nik,
            'ktp_photo_url' => $this->ktp_photo ? $disk->url($this->ktp_photo) : null,
            'selfie_photo_url' => $this->selfie_photo ? $disk->url($this->selfie_photo) : null,
            'sim_number' => $this->sim_number,
            'sim_photo_url' => $this->sim_photo ? $disk->url($this->sim_photo) : null,
            'sim_expired_at' => $this->sim_expired_at?->toDateString(),
        ];
    }
}
