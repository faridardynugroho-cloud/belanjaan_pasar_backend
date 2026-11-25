<?php

namespace App\Policies;

use App\Models\Delivery;
use App\Models\User;

class DeliveryPolicy
{
    public function view(User $user, Delivery $delivery): bool
    {
        return $user->isDriver()
            && $user->driverProfile !== null
            && (int) $delivery->driver_profile_id === (int) $user->driverProfile->driver_profile_id;
    }

    public function start(User $user, Delivery $delivery): bool
    {
        return $this->view($user, $delivery)
            && $user->driverProfile->isApproved();
    }
}
