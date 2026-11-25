<?php

use App\Enums\UserRole;
use App\Events\DriverLocationUpdated;
use App\Models\Delivery;
use App\Models\DeliveryOrder;
use App\Models\DriverLocation;
use App\Models\DriverProfile;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Event;

it('upsert lokasi driver hanya menyimpan satu baris per driver dan memancarkan event', function () {
    Event::fake([DriverLocationUpdated::class]);

    $driver = DriverProfile::factory()->approved()->create();

    $this->actingAs($driver->user, 'sanctum')
        ->putJson('/api/driver/location', [
            'latitude' => -6.2000000,
            'longitude' => 106.8000000,
        ])
        ->assertOk()
        ->assertJsonPath('location.latitude', -6.2);

    $this->actingAs($driver->user, 'sanctum')
        ->putJson('/api/driver/location', [
            'latitude' => -6.2100000,
            'longitude' => 106.8100000,
        ])
        ->assertOk()
        ->assertJsonPath('location.latitude', -6.21);

    expect(DriverLocation::where('user_id', $driver->user_id)->count())->toBe(1);

    $location = DriverLocation::where('user_id', $driver->user_id)->firstOrFail();
    expect((float) $location->latitude)->toBe(-6.21)
        ->and((float) $location->longitude)->toBe(106.81);

    Event::assertDispatchedTimes(DriverLocationUpdated::class, 2);
});

it('user non-driver ditolak saat mengirim lokasi atau mengubah availability', function () {
    $customer = User::factory()->create(['role' => UserRole::Customer->value]);

    $this->actingAs($customer, 'sanctum')
        ->putJson('/api/driver/location', [
            'latitude' => -6.2,
            'longitude' => 106.8,
        ])
        ->assertForbidden();

    $this->actingAs($customer, 'sanctum')
        ->patchJson('/api/driver/availability', ['is_available' => true])
        ->assertForbidden();
});

it('driver approved dapat mengubah status availability', function () {
    $driver = DriverProfile::factory()->approved()->create(['is_available' => false]);

    $this->actingAs($driver->user, 'sanctum')
        ->patchJson('/api/driver/availability', ['is_available' => true])
        ->assertOk()
        ->assertJsonPath('driver.is_available', true);

    expect($driver->fresh()->is_available)->toBeTrue();
});

it('otorisasi private channel lokasi mengizinkan admin, driver pemilik, dan customer order on_the_way saja', function () {
    config(['broadcasting.default' => 'redis']);
    Broadcast::forgetDrivers();
    require base_path('routes/channels.php');

    $driver = DriverProfile::factory()->approved()->create();
    $admin = User::factory()->create(['role' => UserRole::Admin->value]);
    $customerActive = User::factory()->create(['role' => UserRole::Customer->value]);
    $customerPending = User::factory()->create(['role' => UserRole::Customer->value]);
    $otherCustomer = User::factory()->create(['role' => UserRole::Customer->value]);

    $delivery = Delivery::factory()->started()->create(['driver_profile_id' => $driver->driver_profile_id]);

    $activeOrder = Order::factory()->create(['user_id' => $customerActive->user_id]);
    DeliveryOrder::factory()->onTheWay()->create([
        'delivery_id' => $delivery->delivery_id,
        'order_id' => $activeOrder->order_id,
    ]);

    $pendingOrder = Order::factory()->create(['user_id' => $customerPending->user_id]);
    DeliveryOrder::factory()->create([
        'delivery_id' => $delivery->delivery_id,
        'order_id' => $pendingOrder->order_id,
    ]);

    $channelName = "private-driver-location.{$driver->user_id}";

    $this->actingAs($admin, 'sanctum')
        ->postJson('/broadcasting/auth', ['channel_name' => $channelName, 'socket_id' => '1234.5678'])
        ->assertOk();

    $this->actingAs($driver->user, 'sanctum')
        ->postJson('/broadcasting/auth', ['channel_name' => $channelName, 'socket_id' => '1234.5678'])
        ->assertOk();

    $this->actingAs($customerActive, 'sanctum')
        ->postJson('/broadcasting/auth', ['channel_name' => $channelName, 'socket_id' => '1234.5678'])
        ->assertOk();

    $this->actingAs($customerPending, 'sanctum')
        ->postJson('/broadcasting/auth', ['channel_name' => $channelName, 'socket_id' => '1234.5678'])
        ->assertForbidden();

    $this->actingAs($otherCustomer, 'sanctum')
        ->postJson('/broadcasting/auth', ['channel_name' => $channelName, 'socket_id' => '1234.5678'])
        ->assertForbidden();
});
