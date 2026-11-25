<?php

namespace Database\Factories;

use App\Models\Market;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Market>
 */
class MarketFactory extends Factory
{
    protected $model = Market::class;

    public function definition(): array
    {
        return [
            'name' => 'Pasar '.fake()->city(),
            'address' => fake()->address(),
            'latitude' => -6.2000000,
            'longitude' => 106.8000000,
            'is_active' => true,
        ];
    }
}
