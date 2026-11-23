<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Casts\Attribute;

class Voucher extends Model
{
    public const APPLIES_SUBTOTAL = 'subtotal';
    public const APPLIES_DELIVERY_FEE = 'delivery_fee';

    public const TYPE_PERCENT = 'percent';
    public const TYPE_FIXED = 'fixed';

    protected $primaryKey = 'voucher_id';

    protected $fillable = [
        'code',
        'name',
        'description',
        'applies_to',
        'type',
        'value',
        'max_discount',
        'min_purchase',
        'quota',
        'per_user_limit',
        'starts_at',
        'ends_at',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'decimal:2',
            'max_discount' => 'decimal:2',
            'min_purchase' => 'decimal:2',
            'quota' => 'integer',
            'per_user_limit' => 'integer',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    public function usages(): HasMany
    {
        return $this->hasMany(VoucherUsage::class, 'voucher_id', 'voucher_id');
    }

    /** Kode disimpan huruf besar tanpa spasi, jadi "hemat10" dan "HEMAT10" dianggap sama. */
    protected function code(): Attribute
    {
        return Attribute::make(set: fn(string $value) => strtoupper(trim($value)));
    }

    public function scopeBerlaku(Builder $query): Builder
    {
        return $query->where('is_active', true)
            ->where('starts_at', '<=', now())
            ->where('ends_at', '>=', now());
    }

    /** Potongan untuk basis tertentu (subtotal atau ongkir), tidak pernah melebihi basis. */
    public function hitungPotongan(float $basis): float
    {
        if ($this->type === self::TYPE_PERCENT) {
            $potongan = round($basis * (float) $this->value / 100);

            if ($this->max_discount !== null) {
                $potongan = min($potongan, (float) $this->max_discount);
            }
        } else {
            $potongan = (float) $this->value;
        }

        return max(0, min($potongan, $basis));
    }
}
