<?php

namespace App\Support;

class Rating
{
    public static function bintang(?int $rating): string
    {
        return $rating ? str_repeat('★', $rating).str_repeat('☆', 5 - $rating) : '-';
    }
}
