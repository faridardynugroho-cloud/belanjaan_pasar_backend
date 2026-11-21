<?php

namespace App\Models\Component;

use Illuminate\Database\Eloquent\Builder;
/**
 * @property \Illuminate\Support\Carbon|null $created_at
 */

trait IsReview
{
    public function scopeVisible(Builder $query): Builder
    {
        return $query->where('is_visible', true);
    }

    // Bisa diedit selama X jam sejak pertama kali dikirim
    public function isEditable(): bool
    {
        return $this->created_at !== null
            && $this->created_at->copy()->addHours((int) config('review.edit_hours'))->isFuture();
    }
}