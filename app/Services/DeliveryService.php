<?php

namespace App\Services;

use App\Enums\DeliveryOrderStatus;
use App\Exceptions\DeliveryRuleException;
use App\Jobs\DispatchOrderJob;
use App\Models\Delivery;
use App\Models\DeliveryOrder;
use App\Models\DriverProfile;
use App\Models\Order;
use Illuminate\Support\Facades\DB;

class DeliveryService
{
    public function __construct(
        private readonly CapacityChecker $capacityChecker,
    ) {}

    public function accept(DriverProfile $driver, DeliveryOrder $deliveryOrder): DeliveryOrder
    {
        return DB::transaction(function () use ($driver, $deliveryOrder) {
            $lockedOrder = DeliveryOrder::query()
                ->whereKey($deliveryOrder->delivery_order_id)
                ->lockForUpdate()
                ->firstOrFail();

            $delivery = Delivery::query()
                ->whereKey($lockedOrder->delivery_id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->assertOwner($driver, $delivery);

            if ($lockedOrder->status !== DeliveryOrderStatus::Pending->value) {
                throw new DeliveryRuleException(
                    DeliveryRuleException::CODE_INVALID_STATUS,
                    'Hanya order berstatus pending yang dapat diterima.',
                );
            }

            $this->capacityChecker->assertLoadWithinLimits($delivery);

            $lockedOrder->update([
                'status' => DeliveryOrderStatus::Accepted->value,
                'accepted_at' => now(),
            ]);

            return $lockedOrder->fresh(['order.market', 'delivery']);
        });
    }

    public function reject(DriverProfile $driver, DeliveryOrder $deliveryOrder, string $reason): DeliveryOrder
    {
        return DB::transaction(function () use ($driver, $deliveryOrder, $reason) {
            $lockedOrder = DeliveryOrder::query()
                ->whereKey($deliveryOrder->delivery_order_id)
                ->lockForUpdate()
                ->firstOrFail();

            $delivery = Delivery::query()
                ->whereKey($lockedOrder->delivery_id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->assertOwner($driver, $delivery);

            if ($delivery->started_at !== null) {
                throw new DeliveryRuleException(
                    DeliveryRuleException::CODE_DELIVERY_ALREADY_STARTED,
                    'Pengantaran sudah dimulai, order tidak dapat ditolak.',
                );
            }

            // TODO(confirm): Order yang sudah accepted sebelum berangkat tidak diizinkan dibatalkan [TANYA TIM]
            if ($lockedOrder->status !== DeliveryOrderStatus::Pending->value) {
                throw new DeliveryRuleException(
                    DeliveryRuleException::CODE_INVALID_STATUS,
                    'Hanya order berstatus pending yang dapat ditolak.',
                );
            }

            $lockedOrder->update([
                'status' => DeliveryOrderStatus::Rejected->value,
                'rejected_at' => now(),
                'rejection_reason' => trim($reason),
            ]);

            $hasNonRejectedOrders = DeliveryOrder::query()
                ->where('delivery_id', $delivery->delivery_id)
                ->where('status', '!=', DeliveryOrderStatus::Rejected->value)
                ->exists();

            if (! $hasNonRejectedOrders) {
                // TODO(confirm): Set deliveries.cancelled_at jika seluruh delivery_orders berstatus rejected [ASUMSI]
                $delivery->update(['cancelled_at' => now()]);
                DriverSanctionService::mulaiSanksiMenunggu($driver);
            }

            DispatchOrderJob::dispatch($lockedOrder->order_id)->afterCommit();

            return $lockedOrder->fresh(['order.market', 'delivery']);
        });
    }

    public function start(DriverProfile $driver, Delivery $delivery): Delivery
    {
        return DB::transaction(function () use ($driver, $delivery) {
            $lockedDelivery = Delivery::query()
                ->whereKey($delivery->delivery_id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->assertOwner($driver, $lockedDelivery);

            if ($lockedDelivery->cancelled_at !== null || $lockedDelivery->completed_at !== null) {
                throw new DeliveryRuleException(
                    DeliveryRuleException::CODE_INVALID_STATUS,
                    'Pengantaran sudah selesai atau dibatalkan.',
                );
            }

            if ($lockedDelivery->started_at !== null) {
                throw new DeliveryRuleException(
                    DeliveryRuleException::CODE_DELIVERY_ALREADY_STARTED,
                    'Pengantaran sudah dimulai sebelumnya.',
                );
            }

            $deliveryOrders = DeliveryOrder::query()
                ->where('delivery_id', $lockedDelivery->delivery_id)
                ->lockForUpdate()
                ->get();

            // TODO(confirm): Tolak mulai pengantaran jika masih ada order berstatus pending [ASUMSI]
            $hasPending = $deliveryOrders->contains(
                fn (DeliveryOrder $do) => $do->status === DeliveryOrderStatus::Pending->value,
            );

            if ($hasPending) {
                throw new DeliveryRuleException(
                    DeliveryRuleException::CODE_PENDING_ORDERS_EXIST,
                    'Terima atau tolak semua order yang masih pending sebelum memulai pengantaran.',
                );
            }

            $acceptedOrders = $deliveryOrders->filter(
                fn (DeliveryOrder $do) => $do->status === DeliveryOrderStatus::Accepted->value,
            );

            if ($acceptedOrders->isEmpty()) {
                throw new DeliveryRuleException(
                    DeliveryRuleException::CODE_NO_ACCEPTED_ORDERS,
                    'Minimal harus ada 1 order yang diterima untuk memulai pengantaran.',
                );
            }

            $now = now();

            $lockedDelivery->update(['started_at' => $now]);

            DeliveryOrder::query()
                ->whereIn('delivery_order_id', $acceptedOrders->pluck('delivery_order_id'))
                ->update([
                    'status' => DeliveryOrderStatus::OnTheWay->value,
                    'started_at' => $now,
                ]);

            // TODO(confirm): Sinkronisasi orders.status ke on_delivery saat driver mulai mengantar [TANYA TIM]
            Order::query()
                ->whereIn('order_id', $acceptedOrders->pluck('order_id'))
                ->update(['status' => Order::STATUS_ON_DELIVERY]);

            return $lockedDelivery->fresh(['deliveryOrders.order.market']);
        });
    }

    /**
     * @param  array{proof_photo?: ?string, received_by?: ?string, received_note?: ?string}  $proofData
     */
    public function complete(DriverProfile $driver, DeliveryOrder $deliveryOrder, array $proofData = []): DeliveryOrder
    {
        return DB::transaction(function () use ($driver, $deliveryOrder, $proofData) {
            $lockedOrder = DeliveryOrder::query()
                ->whereKey($deliveryOrder->delivery_order_id)
                ->lockForUpdate()
                ->firstOrFail();

            $delivery = Delivery::query()
                ->whereKey($lockedOrder->delivery_id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->assertOwner($driver, $delivery);

            if ($lockedOrder->status !== DeliveryOrderStatus::OnTheWay->value) {
                throw new DeliveryRuleException(
                    DeliveryRuleException::CODE_INVALID_STATUS,
                    'Hanya order yang sedang dalam pengantaran (on_the_way) yang dapat diselesaikan.',
                );
            }

            $now = now();

            $updatePayload = [
                'status' => DeliveryOrderStatus::Completed->value,
                'completed_at' => $now,
            ];

            foreach (['proof_photo', 'received_by', 'received_note'] as $field) {
                if (array_key_exists($field, $proofData) && $proofData[$field] !== null) {
                    $updatePayload[$field] = $proofData[$field];
                }
            }

            $lockedOrder->update($updatePayload);

            // TODO(confirm): Sinkronisasi orders.status ke completed saat order selesai diantar [TANYA TIM]
            Order::query()
                ->where('order_id', $lockedOrder->order_id)
                ->update(['status' => Order::STATUS_COMPLETED]);

            $hasRemainingActive = DeliveryOrder::query()
                ->where('delivery_id', $delivery->delivery_id)
                ->whereIn('status', DeliveryOrderStatus::activeValues())
                ->exists();

            if (! $hasRemainingActive) {
                $delivery->update(['completed_at' => $now]);
                DriverSanctionService::mulaiSanksiMenunggu($driver);
            }

            return $lockedOrder->fresh(['order.market', 'delivery']);
        });
    }

    private function assertOwner(DriverProfile $driver, Delivery $delivery): void
    {
        if ((int) $delivery->driver_profile_id !== (int) $driver->driver_profile_id) {
            throw new DeliveryRuleException(
                DeliveryRuleException::CODE_NOT_OWNER,
                'Anda bukan pemilik pengantaran ini.',
                403,
            );
        }
    }
}
