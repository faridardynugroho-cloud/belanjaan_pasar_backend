<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Address extends Model
{
    protected $primaryKey = 'address_id';

    protected $fillable = [
        'user_id',
        'label',
        'recipient_name',
        'recipient_phone',
        'full_address',
        'latitude',
        'longitude',
        'is_default',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'is_default' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }
    /** Market aktif terdekat dari koordinat alamat ini. */
    public function nearestMarket(): ?Market
    {
        return Market::active()
            ->nearestTo((float) $this->latitude, (float) $this->longitude)
            ->first();
    }
}
