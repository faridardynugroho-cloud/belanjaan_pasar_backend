<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ShoppingRequest extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_ASSIGNED = 'assigned';
    public const STATUS_SHOPPING = 'shopping';
    public const STATUS_PRICE_SENT = 'price_sent';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';

    protected $primaryKey = 'shopping_request_id';

    protected $fillable = [
        'user_id',
        'market_id',
        'request_number',
        'original_list',
        'notes',
        'recipient_name',
        'recipient_phone',
        'shipping_address',
        'shipping_latitude',
        'shipping_longitude',
        'service_fee',
        'total',
        'total_weight_grams',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'shipping_latitude' => 'decimal:7',
            'shipping_longitude' => 'decimal:7',
            'service_fee' => 'decimal:2',
            'total' => 'decimal:2',
            'total_weight_grams' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    public function market(): BelongsTo
    {
        return $this->belongsTo(Market::class, 'market_id', 'market_id');
    }

    /** Rincian barang hasil belanja. Boleh kosong jika admin hanya input total. */
    public function items(): HasMany
    {
        return $this->hasMany(ShoppingRequestItem::class, 'shopping_request_id', 'shopping_request_id');
    }

    /** Riwayat penugasan helper. */
    public function assignments(): HasMany
    {
        return $this->hasMany(ShoppingRequestAssignment::class, 'shopping_request_id', 'shopping_request_id');
    }

    /** Penugasan terbaru. */
    public function currentAssignment(): HasOne
    {
        return $this->hasOne(ShoppingRequestAssignment::class, 'shopping_request_id', 'shopping_request_id')
            ->latestOfMany('assigned_at');
    }

    /** Order hasil "Kirim Harga" (maksimal satu). */
    public function order(): HasOne
    {
        return $this->hasOne(Order::class, 'shopping_request_id', 'shopping_request_id');
    }
}
