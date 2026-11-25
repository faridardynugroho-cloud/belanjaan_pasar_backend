<?php

namespace Database\Factories;

use App\Enums\UserRole;
use App\Models\DriverLocation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DriverLocation>
 */
class DriverLocationFactory extends Factory
{
    protected $model = DriverLocation::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory()->state(['role' => UserRole::Driver->value]),
            'latitude' => -6.2000000,
            'longitude' => 106.8000000,
            'recorded_at' => now(),
        ];
    }
}
