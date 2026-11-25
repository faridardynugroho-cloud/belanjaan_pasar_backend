<?php

namespace Database\Factories;

use App\Models\Delivery;
use App\Models\DriverProfile;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Delivery>
 */
class DeliveryFactory extends Factory
{
    protected $model = Delivery::class;

    public function definition(): array
    {
        return [
            'driver_profile_id' => DriverProfile::factory()->approved()->available(),
            'delivery_number' => 'DLV-'.now()->format('Ymd').'-'.Str::upper(Str::random(6)),
        ];
    }

    public function started(): static
    {
        return $this->state(fn () => [
            'started_at' => now(),
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn () => [
            'started_at' => now()->subMinutes(20),
            'completed_at' => now(),
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn () => [
            'cancelled_at' => now(),
        ]);
    }
}
