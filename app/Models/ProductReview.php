<?php

namespace App\Models;

use App\Models\Component\IsReview;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductReview extends Model
{
    use IsReview;

    protected $primaryKey = 'product_review_id';

    protected $fillable = [
        'order_item_id',
        'user_id',
        'product_id',
        'rating',
        'comment',
        'is_visible',
    ];

    protected function casts(): array
    {
        return [

            'rating' => 'integer',
            'is_visible' => 'boolean',
        ];
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class, 'order_item_id', 'order_item_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id', 'product_id');
    }
}
