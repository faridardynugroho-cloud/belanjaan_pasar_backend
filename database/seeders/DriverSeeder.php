<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\DriverDocument;
use App\Models\DriverLocation;
use App\Models\DriverProfile;
use App\Models\DriverVehicle;
use App\Models\Market;
use App\Models\Order;
use App\Models\User;
use App\Services\DriverVerificationService;
use Illuminate\Database\Seeder;

class DriverSeeder extends Seeder
{
    public function run(): void
    {
        $market = Market::factory()->create([
            'name' => 'Pasar Baru Demo',
            'latitude' => -6.2000000,
            'longitude' => 106.8000000,
        ]);

        $driver = DriverProfile::factory()
            ->approved()
            ->available()
            ->create([
                'user_id' => User::factory()->create([
                    'name' => 'Driver Demo',
                    'email' => 'driver@example.test',
                    'phone' => '081200000001',
                    'role' => UserRole::Driver->value,
                ])->user_id,
            ]);

        DriverDocument::factory()->create(['driver_profile_id' => $driver->driver_profile_id]);
        DriverVehicle::factory()->create(['driver_profile_id' => $driver->driver_profile_id]);
        DriverVerificationService::pastikanPemeriksaan($driver);

        DriverLocation::factory()->create([
            'user_id' => $driver->user_id,
            'latitude' => -6.2010000,
            'longitude' => 106.8010000,
        ]);

        $customer = User::factory()->create([
            'name' => 'Customer Demo',
            'email' => 'customer@example.test',
            'role' => UserRole::Customer->value,
        ]);

        foreach ([5000, 20000, 25000, 40000, 10000] as $weight) {
            Order::factory()->weightGrams($weight)->create([
                'user_id' => $customer->user_id,
                'market_id' => $market->market_id,
            ]);
        }
    }
}
