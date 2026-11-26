<?php

use App\Exceptions\DeliveryRuleException;
use App\Services\FeeCalculator;
use Tests\TestCase;

uses(TestCase::class);

it('menghitung biaya dan potongan aplikasi sesuai semua contoh PRD dan batas tepi', function (
    float $distanceKm,
    int $weightGrams,
    int $expectedCustomerFee,
    int $expectedAppFee,
) {
    $calculator = new FeeCalculator;
    $result = $calculator->calculate($distanceKm, $weightGrams);

    expect($result['total_fee'])->toBe($expectedCustomerFee)
        ->and($result['total_app_fee'])->toBe($expectedAppFee)
        ->and($result['driver_earning'])->toBe($expectedCustomerFee - $expectedAppFee);
})->with([
    '0 km, 10 kg' => [0.0, 10000, 10000, 1000],
    '3 km, 20 kg (batas normal)' => [3.0, 20000, 10000, 1000],
    '4 km, 25 kg' => [4.0, 25000, 17500, 2000],
    '5 km, 20 kg (batas potongan 1000)' => [5.0, 20000, 15000, 1000],
    '5.1 km, 20 kg (lewat 5 km potongan jadi 2000)' => [5.1, 20000, 17500, 2000],
    '6 km, 45 kg (3 blok kelebihan)' => [6.0, 45000, 32500, 5000],
    '7 km, 20.001 kg (kelebihan 1 gram = 1 blok)' => [7.0, 20001, 25000, 3000],
]);

it('menolak jarak melebihi batas maksimal 7 km', function () {
    $calculator = new FeeCalculator;

    expect(fn () => $calculator->calculate(7.01, 10000))
        ->toThrow(DeliveryRuleException::class);
});
