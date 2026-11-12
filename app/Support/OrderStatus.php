<?php

namespace App\Support;

class OrderStatus
{
    public const LABELS = [
        'pending_payment' => 'Menunggu Bayar',
        'paid' => 'Sudah Dibayar',
        'processing' => 'Diproses',
        'ready_for_delivery' => 'Siap Dikirim',
        'on_delivery' => 'Dalam Pengiriman',
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
            'pending_payment' => 'warning',
            'paid', 'processing' => 'info',
            'ready_for_delivery', 'on_delivery' => 'primary',
            'completed' => 'success',
            'cancelled' => 'danger',
            default => 'gray',
        };
    }
}