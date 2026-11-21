<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ViolationType extends Model
{
    protected $primaryKey = 'violation_type_id';

    protected $fillable = [
        'name',
        'description',
        'default_points',
        'min_points',
        'max_points',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'default_points' => 'integer',
            'min_points' => 'integer',
            'max_points' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function violations(): HasMany
    {
        return $this->hasMany(DriverViolation::class, 'violation_type_id', 'violation_type_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /** Poin yang diinput admin harus ada di rentang min-max jenis ini. */
    public function allowsPoints(int $points): bool
    {
        return $points >= $this->min_points && $points <= $this->max_points;
    }
}