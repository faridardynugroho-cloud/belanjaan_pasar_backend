<?php

namespace App\Http\Resources;

use App\Models\DriverProfile;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin DriverProfile */
class DriverProfileResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'driver_profile_id' => $this->driver_profile_id,
            'user_id' => $this->user_id,
            'domicile_address' => $this->domicile_address,
            'is_available' => $this->is_available,
            'approval_status' => $this->approval_status,
            'rejection_reason' => $this->rejection_reason,
            'reviewed_at' => $this->reviewed_at?->toIso8601String(),
            'rating_avg' => (float) $this->rating_avg,
            'rating_count' => $this->rating_count,
            'user' => $this->whenLoaded('user', fn () => [
                'user_id' => $this->user->user_id,
                'name' => $this->user->name,
                'email' => $this->user->email,
                'phone' => $this->user->phone,
                'gender' => $this->user->gender,
                'birth_date' => $this->user->birth_date?->toDateString(),
                'role' => $this->user->role,
            ]),
            'document' => DriverDocumentResource::make($this->whenLoaded('document')),
            'vehicle' => DriverVehicleResource::make($this->whenLoaded('vehicle')),
            'document_checks' => $this->whenLoaded('documentChecks', fn () => $this->documentChecks->map(fn ($check) => [
                'item' => $check->item,
                'status' => $check->status,
                'note' => $check->note,
                'checked_at' => $check->checked_at?->toIso8601String(),
            ])),
        ];
    }
}
