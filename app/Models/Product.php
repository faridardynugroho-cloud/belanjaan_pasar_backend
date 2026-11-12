<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    protected $primaryKey = 'product_id';

    protected $fillable = [
        'category_id',
        'name',
        'description',
        'unit',
        'weight_grams',
        'image',
    ];

    protected function casts(): array
    {
        return [
            'weight_grams' => 'integer',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'category_id', 'category_id');
    }

    public function marketProducts(): HasMany
    {
        return $this->hasMany(MarketProduct::class, 'product_id', 'product_id');
    }

    public function markets(): BelongsToMany
    {
        return $this->belongsToMany(Market::class, 'market_products', 'product_id', 'market_id')
            ->withPivot(['market_product_id', 'price', 'stock', 'is_available'])
            ->withTimestamps();
    }
}
