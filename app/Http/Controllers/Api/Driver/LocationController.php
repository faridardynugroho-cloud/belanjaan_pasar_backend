<?php

namespace App\Http\Controllers\Api\Driver;

use App\Events\DriverLocationUpdated;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Driver\UpdateLocationRequest;
use App\Models\DriverLocation;
use Illuminate\Http\JsonResponse;

class LocationController extends Controller
{
    public function update(UpdateLocationRequest $request): JsonResponse
    {
        $user = $request->user();
        $profile = $user->driverProfile;

        $latitude = (float) $request->input('latitude');
        $longitude = (float) $request->input('longitude');
        $recordedAt = now();

        $previous = DriverLocation::where('user_id', $user->user_id)->first();

        DriverLocation::upsert(
            [
                [
                    'user_id' => $user->user_id,
                    'latitude' => $latitude,
                    'longitude' => $longitude,
                    'recorded_at' => $recordedAt,
                ],
            ],
            ['user_id'],
            ['latitude', 'longitude', 'recorded_at'],
        );

        $location = DriverLocation::where('user_id', $user->user_id)->firstOrFail();

        $minMeters = (float) config('delivery.location_min_displacement_meters', 0);

        if ($previous === null || $minMeters <= 0 || $this->distanceMeters(
            (float) $previous->latitude,
            (float) $previous->longitude,
            $latitude,
            $longitude,
        ) >= $minMeters) {
            DriverLocationUpdated::dispatch(
                (int) $user->user_id,
                (int) $profile->driver_profile_id,
                (float) $location->latitude,
                (float) $location->longitude,
                $location->recorded_at->toIso8601String(),
            );
        }

        return response()->json([
            'message' => 'Location updated',
            'location' => [
                'driver_location_id' => $location->driver_location_id,
                'user_id' => $location->user_id,
                'latitude' => (float) $location->latitude,
                'longitude' => (float) $location->longitude,
                'recorded_at' => $location->recorded_at->toIso8601String(),
            ],
        ]);
    }

    private function distanceMeters(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earthRadiusMeters = 6371000;
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;

        return $earthRadiusMeters * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}
