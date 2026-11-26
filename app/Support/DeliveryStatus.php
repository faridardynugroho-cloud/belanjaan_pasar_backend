<?php

namespace App\Support;

class DeliveryStatus
{
    public const LABELS = [
        'pending' => 'Menunggu Berangkat',
        'on_the_way' => 'Dalam Perjalanan',
        'completed' => 'Selesai',
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
            'on_the_way' => 'primary',
            'completed' => 'success',
            'cancelled' => 'danger',
            default => 'gray',
        };
    }
}
