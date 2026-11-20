<?php

return [
    // Poin pelanggaran yang divalidasi berlaku selama N bulan.
    'points_valid_months' => 1,

    // Batas suspend sementara (menit). 60 hari. Lebih dari itu pakai permanent.
    'max_temporary_minutes' => 60 * 24 * 60,

    // Tier berdasarkan total poin aktif. Diambil tier tertinggi yang batas bawahnya terlewati.
    'tiers' => [
        ['min_points' => 10,  'type' => 'temporary',   'duration_minutes' => 1 * 60],        // 1 jam
        ['min_points' => 30,  'type' => 'temporary',   'duration_minutes' => 6 * 60],        // 6 jam
        ['min_points' => 60,  'type' => 'temporary',   'duration_minutes' => 24 * 60],       // 1 hari
        ['min_points' => 100, 'type' => 'temporary',   'duration_minutes' => 7 * 24 * 60],   // 7 hari
        ['min_points' => 150, 'type' => 'temporary',   'duration_minutes' => 30 * 24 * 60],  // 30 hari
        ['min_points' => 200, 'type' => 'termination', 'duration_minutes' => null],          // putus mitra
    ],
];