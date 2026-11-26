<?php

namespace App\Services;

use App\Enums\ApprovalStatus;
use App\Enums\DeliveryOrderStatus;
use App\Exceptions\DeliveryRuleException;
use App\Models\Delivery;
use App\Models\DeliveryOrder;
use App\Models\DriverProfile;
use App\Models\Order;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DispatchService
{
    public function __construct(
        private readonly CapacityChecker $capacityChecker,
        private readonly DriverRanker $driverRanker,
    ) {}

    public function dispatch(Order $order): ?DeliveryOrder
    {
        return DB::transaction(function () use ($order) {
            $lockedOrder = Order::query()
                ->with('market')
                ->whereKey($order->order_id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedOrder->status !== Order::STATUS_READY_FOR_DELIVERY || ! $lockedOrder->market) {
                return null;
            }

            $alreadyActive = DeliveryOrder::query()
                ->where('order_id', $lockedOrder->order_id)
                ->whereIn('status', DeliveryOrderStatus::activeValues())
                ->exists();

            if ($alreadyActive) {
                return null;
            }

            if ((int) $lockedOrder->total_weight_grams > (int) config('delivery.max_weight_grams', 50000)) {
                return null;
            }

            $candidates = $this->findEligibleCandidates($lockedOrder);

            if ($candidates->isEmpty()) {
                return null;
            }

            $stageTwoThreshold = (int) config('delivery.dispatch_stage_two_min_orders', 2);

            $stageOneCandidates = $candidates->filter(
                fn (DriverProfile $driver) => (int) $driver->getAttribute('active_orders_count') < $stageTwoThreshold,
            );
            $stageTwoCandidates = $candidates->filter(
                fn (DriverProfile $driver) => (int) $driver->getAttribute('active_orders_count') >= $stageTwoThreshold,
            );

            $ranked = $this->driverRanker->rank($stageOneCandidates, $lockedOrder->market);

            if ($ranked->isEmpty()) {
                $ranked = $this->driverRanker->rank($stageTwoCandidates, $lockedOrder->market);
            }

            foreach ($ranked as $candidate) {
                $assigned = $this->tryAssignToCandidate($candidate, $lockedOrder);

                if ($assigned !== null) {
                    return $assigned;
                }
            }

            return null;
        });
    }

    /**
     * @return Collection<int, DriverProfile>
     */
    private function findEligibleCandidates(Order $order): Collection
    {
        $drivers = DriverProfile::query()
            ->where('approval_status', ApprovalStatus::Approved->value)
            ->where('is_available', true)
            ->whereHas('location')
            ->whereDoesntHave('sanctions', fn ($q) => $q->open())
            ->whereDoesntHave('deliveries', fn ($q) => $q->active()->whereNotNull('started_at'))
            ->whereDoesntHave('deliveries.deliveryOrders', fn ($q) => $q
                ->where('order_id', $order->order_id)
                ->where('status', DeliveryOrderStatus::Rejected->value))
            ->with('location')
            ->withMax(['deliveries as last_completed_at' => fn ($q) => $q->whereNotNull('completed_at')], 'completed_at')
            ->get();

        return $drivers->filter(function (DriverProfile $driver) use ($order): bool {
            $activeDelivery = Delivery::query()
                ->where('driver_profile_id', $driver->driver_profile_id)
                ->active()
                ->whereNull('started_at')
                ->first();

            if (! $activeDelivery) {
                $driver->setAttribute('active_orders_count', 0);

                return true;
            }

            if (! $this->capacityChecker->canAdd($activeDelivery, $order)) {
                return false;
            }

            $load = $this->capacityChecker->currentLoad($activeDelivery);
            $driver->setAttribute('active_orders_count', $load['orders']);

            return true;
        })->values();
    }

    private function tryAssignToCandidate(DriverProfile $candidate, Order $order): ?DeliveryOrder
    {
        $lockedDriver = DriverProfile::query()
            ->whereKey($candidate->driver_profile_id)
            ->lockForUpdate()
            ->first();

        if (! $lockedDriver || ! $lockedDriver->isApproved() || ! $lockedDriver->is_available) {
            return null;
        }

        if (! DriverSanctionService::bolehMenerimaOrder($lockedDriver)) {
            return null;
        }

        $hasStartedDelivery = Delivery::query()
            ->where('driver_profile_id', $lockedDriver->driver_profile_id)
            ->active()
            ->whereNotNull('started_at')
            ->lockForUpdate()
            ->exists();

        if ($hasStartedDelivery) {
            return null;
        }

        $delivery = Delivery::query()
            ->where('driver_profile_id', $lockedDriver->driver_profile_id)
            ->active()
            ->whereNull('started_at')
            ->lockForUpdate()
            ->first();

        if ($delivery) {
            try {
                $this->capacityChecker->assertCanAdd($delivery, $order);
            } catch (DeliveryRuleException) {
                return null;
            }
        } else {
            $delivery = Delivery::create([
                'driver_profile_id' => $lockedDriver->driver_profile_id,
                'delivery_number' => $this->generateUniqueDeliveryNumber(),
            ]);
        }

        return DeliveryOrder::create([
            'delivery_id' => $delivery->delivery_id,
            'order_id' => $order->order_id,
            'status' => DeliveryOrderStatus::Pending->value,
            'assigned_at' => now(),
        ]);
    }

    private function generateUniqueDeliveryNumber(): string
    {
        do {
            $number = 'DLV-'.now()->format('Ymd').'-'.Str::upper(Str::random(6));
        } while (Delivery::where('delivery_number', $number)->exists());

        return $number;
    }
}
