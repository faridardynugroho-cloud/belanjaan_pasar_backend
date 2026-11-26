<?php

use App\Enums\DeliveryOrderStatus;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

// TODO(confirm): Otorisasi channel lokasi realtime driver (default: admin, driver sendiri, atau customer dengan order on_the_way)
Broadcast::channel('driver-location.{driverUserId}', function (User $user, int $driverUserId): bool {
    if ($user->isAdmin() || (int) $user->user_id === $driverUserId) {
        return true;
    }

    return Order::query()
        ->where('user_id', $user->user_id)
        ->whereHas('deliveryOrders', function ($query) use ($driverUserId) {
            $query->where('status', DeliveryOrderStatus::OnTheWay->value)
                ->whereHas('delivery.driver', fn ($q) => $q->where('user_id', $driverUserId));
        })
        ->exists();
});
