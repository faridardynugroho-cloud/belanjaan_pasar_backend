<?php

namespace Database\Factories;

use App\Enums\DeliveryOrderStatus;
use App\Models\Delivery;
use App\Models\DeliveryOrder;
use App\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DeliveryOrder>
 */
class DeliveryOrderFactory extends Factory
{
    protected $model = DeliveryOrder::class;

    public function definition(): array
    {
        return [
            'delivery_id' => Delivery::factory(),
            'order_id' => Order::factory(),
            'status' => DeliveryOrderStatus::Pending->value,
            'assigned_at' => now(),
        ];
    }

    public function accepted(): static
    {
        return $this->state(fn () => [
            'status' => DeliveryOrderStatus::Accepted->value,
            'accepted_at' => now(),
        ]);
    }

    public function rejected(string $reason = 'Ban bocor'): static
    {
        return $this->state(fn () => [
            'status' => DeliveryOrderStatus::Rejected->value,
            'rejected_at' => now(),
            'rejection_reason' => $reason,
        ]);
    }

    public function onTheWay(): static
    {
        return $this->state(fn () => [
            'status' => DeliveryOrderStatus::OnTheWay->value,
            'accepted_at' => now()->subMinutes(5),
            'started_at' => now(),
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn () => [
            'status' => DeliveryOrderStatus::Completed->value,
            'accepted_at' => now()->subMinutes(20),
            'started_at' => now()->subMinutes(15),
            'completed_at' => now(),
        ]);
    }
}
