<?php

namespace App\Services;

use App\Models\DriverProfile;
use App\Models\Market;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class DriverRanker
{
    public function __construct(
        private readonly FeeCalculator $feeCalculator,
    ) {}

    /**
     * Urutkan kandidat driver di dalam radius maksimum.
     *
     * @param  Collection<int, DriverProfile>  $candidates
     * @return Collection<int, DriverProfile>
     */
    public function rank(Collection $candidates, Market $market): Collection
    {
        $maxRadiusKm = (float) config('delivery.dispatch_radius_km', 10.0);

        // TODO(confirm): Filter radius maksimum, lalu urutkan prioritas belum pernah dapat order -> paling lama menunggu -> jarak terdekat ke pasar [ASUMSI]
        return $candidates
            ->filter(function (DriverProfile $driver) use ($market, $maxRadiusKm): bool {
                $location = $driver->location;

                if (! $location) {
                    return false;
                }

                $distanceKm = $this->feeCalculator->distanceKmBetween(
                    (float) $market->latitude,
                    (float) $market->longitude,
                    (float) $location->latitude,
                    (float) $location->longitude,
                );

                $driver->setAttribute('distance_to_market_km', $distanceKm);

                return $distanceKm <= $maxRadiusKm;
            })
            ->sort(function (DriverProfile $a, DriverProfile $b): int {
                $aCompletedAt = $a->last_completed_at ? Carbon::parse($a->last_completed_at) : null;
                $bCompletedAt = $b->last_completed_at ? Carbon::parse($b->last_completed_at) : null;

                // 1. Driver yang belum pernah menyelesaikan delivery didahulukan
                $aHasCompleted = $aCompletedAt !== null ? 1 : 0;
                $bHasCompleted = $bCompletedAt !== null ? 1 : 0;

                if ($aHasCompleted !== $bHasCompleted) {
                    return $aHasCompleted <=> $bHasCompleted;
                }

                // 2. Paling lama menunggu (completed_at terbaru atau recorded_at)
                $aWait = ($aCompletedAt ?? $a->location->recorded_at)->getTimestamp();
                $bWait = ($bCompletedAt ?? $b->location->recorded_at)->getTimestamp();

                if ($aWait !== $bWait) {
                    return $aWait <=> $bWait;
                }

                // 3. Jarak terdekat ke pasar sebagai tie-break
                $distanceComparison = ((float) $a->getAttribute('distance_to_market_km'))
                    <=> ((float) $b->getAttribute('distance_to_market_km'));

                if ($distanceComparison !== 0) {
                    return $distanceComparison;
                }

                return $a->driver_profile_id <=> $b->driver_profile_id;
            })
            ->values();
    }
}
