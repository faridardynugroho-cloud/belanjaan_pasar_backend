<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShoppingRequestAssignment extends Model
{
    public const STATUS_ASSIGNED = 'assigned';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';

    protected $primaryKey = 'shopping_request_assignment_id';

    protected $fillable = [
        'shopping_request_id',
        'helper_id',
        'assigned_by',
        'assigned_at',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'assigned_at' => 'datetime',
        ];
    }

    public function shoppingRequest(): BelongsTo
    {
        return $this->belongsTo(ShoppingRequest::class, 'shopping_request_id', 'shopping_request_id');
    }

    public function helper(): BelongsTo
    {
        return $this->belongsTo(Helper::class, 'helper_id', 'helper_id');
    }

    /** Admin yang membuat penugasan. */
    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by', 'user_id');
    }
}
