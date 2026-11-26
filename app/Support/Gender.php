<?php

namespace App\Support;

class Gender
{
    public const LABELS = [
        'male' => 'Laki-laki',
        'female' => 'Perempuan',
    ];

    public static function label(?string $gender): string
    {
        return self::LABELS[$gender] ?? '-';
    }
}
