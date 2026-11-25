<?php

namespace Database\Factories;

use App\Models\Market;
use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    protected $model = Order::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'market_id' => Market::factory(),
            'order_number' => 'ORD-'.now()->format('Ymd').'-'.Str::upper(Str::random(6)),
            'recipient_name' => fake()->name(),
            'recipient_phone' => '08'.fake()->numerify('##########'),
            'shipping_address' => fake()->address(),
            'shipping_latitude' => -6.2100000,
            'shipping_longitude' => 106.8100000,
            'subtotal' => 50000,
            'delivery_fee' => 10000,
            'grand_total' => 60000,
            'total_weight_grams' => 5000,
            'status' => Order::STATUS_READY_FOR_DELIVERY,
        ];
    }

    public function weightGrams(int $grams): static
    {
        return $this->state(fn () => [
            'total_weight_grams' => $grams,
        ]);
    }
}
