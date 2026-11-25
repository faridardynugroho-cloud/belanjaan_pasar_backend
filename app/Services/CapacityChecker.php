<?php

namespace App\Services;

use App\Enums\DeliveryOrderStatus;
use App\Exceptions\DeliveryRuleException;
use App\Models\Delivery;
use App\Models\DeliveryOrder;
use App\Models\Order;

class CapacityChecker
{
    /**
     * Hitung muatan aktif pada delivery (pending, accepted, on_the_way). Order rejected diabaikan.
     *
     * @return array{orders: int, weight_grams: int}
     */
    public function currentLoad(Delivery $delivery): array
    {
        // TODO(confirm): Status pending ikut dihitung sebagai muatan aktif agar tidak over-assign [ASUMSI]
        $row = DeliveryOrder::query()
            ->join('orders', 'orders.order_id', '=', 'delivery_orders.order_id')
            ->where('delivery_orders.delivery_id', $delivery->delivery_id)
            ->whereIn('delivery_orders.status', DeliveryOrderStatus::activeValues())
            ->selectRaw('COUNT(*) as orders_count, COALESCE(SUM(orders.total_weight_grams), 0) as total_weight')
            ->first();

        return [
            'orders' => (int) ($row?->orders_count ?? 0),
            'weight_grams' => (int) ($row?->total_weight ?? 0),
        ];
    }

    public function canAdd(Delivery $delivery, Order $order): bool
    {
        try {
            $this->assertCanAdd($delivery, $order);

            return true;
        } catch (DeliveryRuleException) {
            return false;
        }
    }

    public function assertCanAdd(Delivery $delivery, Order $order): void
    {
        $this->assertDeliveryAcceptingOrders($delivery);

        $load = $this->currentLoad($delivery);
        $maxOrders = (int) config('delivery.max_orders', 3);
        $maxWeightGrams = (int) config('delivery.max_weight_grams', 50000);

        if ($load['orders'] >= $maxOrders) {
            throw new DeliveryRuleException(
                DeliveryRuleException::CODE_CAPACITY_EXCEEDED,
                "Kapasitas maksimal {$maxOrders} order per pengantaran sudah tercapai.",
            );
        }

        if (($load['weight_grams'] + (int) $order->total_weight_grams) > $maxWeightGrams) {
            throw new DeliveryRuleException(
                DeliveryRuleException::CODE_WEIGHT_EXCEEDED,
                "Total berat pengantaran melebihi batas maksimal {$maxWeightGrams} gram.",
            );
        }
    }

    /**
     * Dipakai saat driver menerima order yang sudah berada di delivery (status pending).
     */
    public function assertLoadWithinLimits(Delivery $delivery): void
    {
        $this->assertDeliveryAcceptingOrders($delivery);

        $load = $this->currentLoad($delivery);
        $maxOrders = (int) config('delivery.max_orders', 3);
        $maxWeightGrams = (int) config('delivery.max_weight_grams', 50000);

        if ($load['orders'] > $maxOrders) {
            throw new DeliveryRuleException(
                DeliveryRuleException::CODE_CAPACITY_EXCEEDED,
                "Kapasitas maksimal {$maxOrders} order per pengantaran terlampaui.",
            );
        }

        if ($load['weight_grams'] > $maxWeightGrams) {
            throw new DeliveryRuleException(
                DeliveryRuleException::CODE_WEIGHT_EXCEEDED,
                "Total berat pengantaran melebihi batas maksimal {$maxWeightGrams} gram.",
            );
        }
    }

    private function assertDeliveryAcceptingOrders(Delivery $delivery): void
    {
        if ($delivery->cancelled_at !== null || $delivery->completed_at !== null) {
            throw new DeliveryRuleException(
                DeliveryRuleException::CODE_INVALID_STATUS,
                'Pengantaran sudah selesai atau dibatalkan.',
            );
        }

        if ($delivery->started_at !== null) {
            throw new DeliveryRuleException(
                DeliveryRuleException::CODE_DELIVERY_ALREADY_STARTED,
                'Pengantaran sudah dimulai, tidak dapat menambah atau menerima order lagi.',
            );
        }
    }
}
