<?php

namespace App\Http\Resources;

use App\Enums\DeliveryOrderStatus;
use App\Models\Delivery;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Delivery */
class DeliveryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $activeOrders = $this->whenLoaded('deliveryOrders', function () {
            return $this->deliveryOrders->filter(
                fn ($do) => in_array($do->status, DeliveryOrderStatus::activeValues(), true),
            );
        });

        return [
            'delivery_id' => $this->delivery_id,
            'driver_profile_id' => $this->driver_profile_id,
            'delivery_number' => $this->delivery_number,
            'status' => $this->status,
            'started_at' => $this->started_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'cancelled_at' => $this->cancelled_at?->toIso8601String(),
            'active_orders_count' => $activeOrders ? $activeOrders->count() : null,
            'active_weight_grams' => $activeOrders ? (int) $activeOrders->sum(fn ($do) => $do->order?->total_weight_grams ?? 0) : null,
            'delivery_orders' => DeliveryOrderResource::collection($this->whenLoaded('deliveryOrders')),
        ];
    }
}
