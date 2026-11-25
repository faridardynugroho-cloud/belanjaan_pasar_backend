<?php

namespace App\Http\Controllers\Api\Driver;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Driver\UpdateAvailabilityRequest;
use App\Http\Resources\DriverProfileResource;
use Illuminate\Http\JsonResponse;

class AvailabilityController extends Controller
{
    public function update(UpdateAvailabilityRequest $request): JsonResponse
    {
        $profile = $request->user()->driverProfile;
        $profile->update([
            'is_available' => $request->boolean('is_available'),
        ]);

        return response()->json([
            'message' => 'Availability updated',
            'driver' => DriverProfileResource::make($profile->fresh()),
        ]);
    }
}
