<?php

namespace App\Http\Resources;

use App\Models\DeliveryOrder;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/** @mixin DeliveryOrder */
class DeliveryOrderResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var FilesystemAdapter $disk */
        $disk = Storage::disk('public');

        return [
            'delivery_order_id' => $this->delivery_order_id,
            'delivery_id' => $this->delivery_id,
            'order_id' => $this->order_id,
            'status' => $this->status,
            'rejection_reason' => $this->rejection_reason,
            'assigned_at' => $this->assigned_at?->toIso8601String(),
            'accepted_at' => $this->accepted_at?->toIso8601String(),
            'rejected_at' => $this->rejected_at?->toIso8601String(),
            'started_at' => $this->started_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'proof_photo_url' => $this->proof_photo ? $disk->url($this->proof_photo) : null,
            'received_by' => $this->received_by,
            'received_note' => $this->received_note,
            'order' => $this->whenLoaded('order', fn () => [
                'order_id' => $this->order->order_id,
                'order_number' => $this->order->order_number,
                'recipient_name' => $this->order->recipient_name,
                'recipient_phone' => $this->order->recipient_phone,
                'shipping_address' => $this->order->shipping_address,
                'shipping_latitude' => (float) $this->order->shipping_latitude,
                'shipping_longitude' => (float) $this->order->shipping_longitude,
                'subtotal' => (float) $this->order->subtotal,
                'delivery_fee' => (float) $this->order->delivery_fee,
                'grand_total' => (float) $this->order->grand_total,
                'total_weight_grams' => $this->order->total_weight_grams,
                'status' => $this->order->status,
                'market' => $this->order->relationLoaded('market') && $this->order->market ? [
                    'market_id' => $this->order->market->market_id,
                    'name' => $this->order->market->name,
                    'address' => $this->order->market->address,
                    'latitude' => (float) $this->order->market->latitude,
                    'longitude' => (float) $this->order->market->longitude,
                ] : null,
            ]),
        ];
    }
}
