<?php

namespace Database\Factories;

use App\Models\DriverProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DriverProfile>
 */
class DriverProfileFactory extends Factory
{
    protected $model = DriverProfile::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory()->state(['role' => User::ROLE_DRIVER]),
            'vehicle_type' => fake()->randomElement(['motorcycle', 'car']),
            'vehicle_plate' => strtoupper(fake()->bothify('? #### ??')),
            'is_available' => true,
            'approval_status' => 'pending',
        ];
    }
}
