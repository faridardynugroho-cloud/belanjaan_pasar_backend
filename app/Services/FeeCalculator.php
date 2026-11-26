<?php

namespace App\Services;

use App\Exceptions\DeliveryRuleException;

class FeeCalculator
{
    /**
     * Hitung ongkos kirim dan potongan aplikasi (nominal tetap Rupiah, tanpa persentase).
     *
     * @return array{
     *     distance_km: float,
     *     billed_distance_km: int,
     *     distance_fee: int,
     *     weight_grams: int,
     *     weight_excess_grams: int,
     *     weight_blocks: int,
     *     weight_fee: int,
     *     total_fee: int,
     *     distance_app_fee: int,
     *     weight_app_fee: int,
     *     total_app_fee: int,
     *     driver_earning: int
     * }
     */
    public function calculate(float $distanceKm, int $weightGrams): array
    {
        $maxDistanceKm = (float) config('delivery.max_distance_km', 7);

        if ($distanceKm < 0) {
            throw new DeliveryRuleException(
                DeliveryRuleException::CODE_DISTANCE_EXCEEDED,
                'Jarak pengantaran tidak boleh negatif.',
            );
        }

        if ($distanceKm > $maxDistanceKm) {
            throw new DeliveryRuleException(
                DeliveryRuleException::CODE_DISTANCE_EXCEEDED,
                "Jarak pengantaran melebihi batas maksimal {$maxDistanceKm} km.",
            );
        }

        if ($weightGrams < 0) {
            throw new DeliveryRuleException(
                DeliveryRuleException::CODE_WEIGHT_EXCEEDED,
                'Berat pesanan tidak boleh negatif.',
            );
        }

        // TODO(confirm): Pembulatan km memakai ceil(jarak); ambang potongan Rp 2.000 memakai jarak asli (> 5.0 km) [ASUMSI]
        $billedKm = (int) ceil($distanceKm);
        $baseDistanceKm = (int) config('delivery.base_distance_km', 3);
        $extraKm = max(0, $billedKm - $baseDistanceKm);

        $distanceFee = (int) config('delivery.base_fee', 10000)
            + ($extraKm * (int) config('delivery.per_km_fee', 2500));

        $thresholdKm = (float) config('delivery.long_distance_threshold_km', 5.0);
        $distanceAppFee = $distanceKm > $thresholdKm
            ? (int) config('delivery.long_distance_app_fee', 2000)
            : (int) config('delivery.base_app_fee', 1000);

        $normalWeightGrams = (int) config('delivery.normal_weight_grams', 20000);
        $blockGrams = (int) config('delivery.weight_block_grams', 10000);
        $excessGrams = max(0, $weightGrams - $normalWeightGrams);
        $weightBlocks = $excessGrams > 0 ? intdiv($excessGrams + $blockGrams - 1, $blockGrams) : 0;

        $weightFee = $weightBlocks * (int) config('delivery.weight_block_fee', 5000);
        $weightAppFee = $weightBlocks * (int) config('delivery.weight_block_app_fee', 1000);

        $totalFee = $distanceFee + $weightFee;
        $totalAppFee = $distanceAppFee + $weightAppFee;

        return [
            'distance_km' => round($distanceKm, 2),
            'billed_distance_km' => $billedKm,
            'distance_fee' => $distanceFee,
            'weight_grams' => $weightGrams,
            'weight_excess_grams' => $excessGrams,
            'weight_blocks' => $weightBlocks,
            'weight_fee' => $weightFee,
            'total_fee' => $totalFee,
            'distance_app_fee' => $distanceAppFee,
            'weight_app_fee' => $weightAppFee,
            'total_app_fee' => $totalAppFee,
            'driver_earning' => $totalFee - $totalAppFee,
        ];
    }

    public function distanceKmBetween(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earthRadiusKm = 6371.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;

        return $earthRadiusKm * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}
