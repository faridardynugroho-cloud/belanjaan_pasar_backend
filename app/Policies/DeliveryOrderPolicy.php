<?php

namespace App\Policies;

use App\Models\DeliveryOrder;
use App\Models\User;

class DeliveryOrderPolicy
{
    public function view(User $user, DeliveryOrder $deliveryOrder): bool
    {
        return $user->isDriver()
            && $user->driverProfile !== null
            && (int) $deliveryOrder->delivery?->driver_profile_id === (int) $user->driverProfile->driver_profile_id;
    }

    public function accept(User $user, DeliveryOrder $deliveryOrder): bool
    {
        return $this->view($user, $deliveryOrder) && $user->driverProfile->isApproved();
    }

    public function reject(User $user, DeliveryOrder $deliveryOrder): bool
    {
        return $this->view($user, $deliveryOrder) && $user->driverProfile->isApproved();
    }

    public function complete(User $user, DeliveryOrder $deliveryOrder): bool
    {
        return $this->view($user, $deliveryOrder) && $user->driverProfile->isApproved();
    }
}
