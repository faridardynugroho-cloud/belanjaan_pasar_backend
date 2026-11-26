<?php

namespace Database\Factories;

use App\Models\DriverProfile;
use App\Models\DriverVehicle;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DriverVehicle>
 */
class DriverVehicleFactory extends Factory
{
    protected $model = DriverVehicle::class;

    public function definition(): array
    {
        return [
            'driver_profile_id' => DriverProfile::factory(),
            'vehicle_type' => 'Motor',
            'vehicle_brand' => fake()->randomElement(['Honda', 'Yamaha', 'Suzuki']),
            'vehicle_model' => fake()->randomElement(['Beat', 'Vario', 'NMAX', 'Mio']),
            'vehicle_plate' => strtoupper(fake()->unique()->bothify('B #### ???')),
            'vehicle_color' => fake()->randomElement(['Hitam', 'Putih', 'Merah', 'Biru']),
            'production_year' => fake()->numberBetween(2015, 2025),
            'vehicle_photo' => 'driver-vehicles/vehicle/sample.jpg',
            'stnk_photo' => 'driver-vehicles/stnk/sample.jpg',
        ];
    }
}
