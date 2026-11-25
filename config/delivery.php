<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Kapasitas & Berat Pengiriman (R-02, R-03)
    |--------------------------------------------------------------------------
    */
    'max_orders' => 3,
    'max_weight_grams' => 50000,
    'normal_weight_grams' => 20000,
    'weight_block_grams' => 10000,
    'weight_block_fee' => 5000,
    'weight_block_app_fee' => 1000,

    /*
    |--------------------------------------------------------------------------
    | Jarak & Tarif Pengiriman (R-04) — Nominal Tetap, Tanpa Persentase
    |--------------------------------------------------------------------------
    */
    'max_distance_km' => 7,
    'base_distance_km' => 3,
    'base_fee' => 10000,
    'per_km_fee' => 2500,
    'base_app_fee' => 1000,
    'long_distance_threshold_km' => 5.0,
    'long_distance_app_fee' => 2000,

    /*
    |--------------------------------------------------------------------------
    | Dispatch Driver (R-01)
    |--------------------------------------------------------------------------
    */
    // TODO(confirm): Nilai radius maksimum kandidat driver dari pasar [ASUMSI]
    'dispatch_radius_km' => 10.0,
    'dispatch_stage_two_min_orders' => 2,
    // TODO(confirm): Delay dan maksimal retry job dispatch [ASUMSI]
    'dispatch_retry_delay_seconds' => 30,
    'dispatch_max_tries' => 10,

    /*
    |--------------------------------------------------------------------------
    | Lokasi Realtime Driver (R-08)
    |--------------------------------------------------------------------------
    */
    // TODO(confirm): Interval kirim & filter perpindahan minimum [ASUMSI]
    'location_interval_seconds' => 5,
    'location_min_displacement_meters' => 0,
];
