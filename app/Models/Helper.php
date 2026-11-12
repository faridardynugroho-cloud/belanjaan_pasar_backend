<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Helper extends Model
{
    protected $primaryKey = 'helper_id';

    protected $fillable = [
        'name',
        'phone',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(ShoppingRequestAssignment::class, 'helper_id', 'helper_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
