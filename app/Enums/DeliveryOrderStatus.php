<?php

namespace App\Enums;

enum DeliveryOrderStatus: string
{
    case Pending = 'pending';
    case Accepted = 'accepted';
    case Rejected = 'rejected';
    case OnTheWay = 'on_the_way';
    case Completed = 'completed';

    /** @return list<string> */
    public static function activeValues(): array
    {
        return [
            self::Pending->value,
            self::Accepted->value,
            self::OnTheWay->value,
        ];
    }

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Menunggu Konfirmasi',
            self::Accepted => 'Diterima',
            self::Rejected => 'Ditolak',
            self::OnTheWay => 'Dalam Pengantaran',
            self::Completed => 'Selesai',
        };
    }
}
