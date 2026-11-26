<?php

namespace App\Support;

class ShoppingRequestStatus
{
    public const LABELS = [
        'pending' => 'Baru',
        'assigned' => 'Ditugaskan',
        'shopping' => 'Sedang Belanja',
        'price_sent' => 'Harga Terkirim',
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
            'assigned', 'shopping' => 'info',
            'price_sent' => 'primary',
            'completed' => 'success',
            'cancelled' => 'danger',
            default => 'gray',
        };
    }
}
