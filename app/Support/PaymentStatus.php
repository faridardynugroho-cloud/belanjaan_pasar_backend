<?php

namespace App\Support;

class PaymentStatus
{
    public const LABELS = [
        'pending' => 'Menunggu',
        'paid' => 'Berhasil',
        'failed' => 'Gagal',
        'expired' => 'Kedaluwarsa',
        'cancelled' => 'Dibatalkan',
    ];

    public static function label(?string $status): string
    {
        return self::LABELS[$status] ?? (string) $status;
    }

    public static function color(?string $status): string
    {
        return match ($status) {
            'pending' => 'warning',
            'paid' => 'success',
            'failed' => 'danger',
            'expired', 'cancelled' => 'gray',
            default => 'gray',
        };
    }
}
