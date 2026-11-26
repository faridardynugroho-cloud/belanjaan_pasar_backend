<?php

namespace Database\Factories;

use App\Models\DriverDocument;
use App\Models\DriverProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DriverDocument>
 */
class DriverDocumentFactory extends Factory
{
    protected $model = DriverDocument::class;

    public function definition(): array
    {
        return [
            'driver_profile_id' => DriverProfile::factory(),
            'nik' => fake()->unique()->numerify('################'),
            'ktp_photo' => 'driver-documents/ktp/sample.jpg',
            'selfie_photo' => 'driver-documents/selfie/sample.jpg',
            'sim_number' => fake()->numerify('############'),
            'sim_photo' => 'driver-documents/sim/sample.jpg',
            'sim_expired_at' => now()->addYears(3)->toDateString(),
        ];
    }
}
