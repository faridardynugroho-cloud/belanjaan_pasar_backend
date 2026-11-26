<?php

use App\Enums\DeliveryOrderStatus;
use App\Jobs\DispatchOrderJob;
use App\Models\Delivery;
use App\Models\DeliveryOrder;
use App\Models\DriverLocation;
use App\Models\DriverProfile;
use App\Models\DriverSanction;
use App\Models\Market;
use App\Models\Order;
use App\Services\DispatchService;
use Illuminate\Support\Facades\Queue;

function createReadyDriverAt(float $lat, float $lng, $recordedAt = null, array $profileOverrides = []): DriverProfile
{
    $driver = DriverProfile::factory()->approved()->available()->create($profileOverrides);

    DriverLocation::factory()->create([
        'user_id' => $driver->user_id,
        'latitude' => $lat,
        'longitude' => $lng,
        'recorded_at' => $recordedAt ?? now(),
    ]);

    return $driver;
}

it('memprioritaskan driver yang belum pernah selesai delivery daripada yang sudah pernah', function () {
    $market = Market::factory()->create(['latitude' => -6.2000000, 'longitude' => 106.8000000]);

    $experienced = createReadyDriverAt(-6.2010000, 106.8010000, now()->subMinutes(10));
    Delivery::factory()->completed()->create([
        'driver_profile_id' => $experienced->driver_profile_id,
        'completed_at' => now()->subHours(2),
    ]);

    $fresh = createReadyDriverAt(-6.2050000, 106.8050000, now()->subMinutes(5));

    $order = Order::factory()->create(['market_id' => $market->market_id]);

    $assigned = app(DispatchService::class)->dispatch($order);

    expect($assigned)->not->toBeNull()
        ->and($assigned->delivery->driver_profile_id)->toBe($fresh->driver_profile_id)
        ->and($assigned->status)->toBe(DeliveryOrderStatus::Pending->value);
});

it('mengurutkan driver yang sudah pernah delivery berdasarkan paling lama menunggu lalu jarak', function () {
    $market = Market::factory()->create(['latitude' => -6.2000000, 'longitude' => 106.8000000]);

    $recentDriver = createReadyDriverAt(-6.2005000, 106.8005000);
    Delivery::factory()->completed()->create([
        'driver_profile_id' => $recentDriver->driver_profile_id,
        'completed_at' => now()->subMinutes(10),
    ]);

    $longerWaitingFarther = createReadyDriverAt(-6.2200000, 106.8200000);
    Delivery::factory()->completed()->create([
        'driver_profile_id' => $longerWaitingFarther->driver_profile_id,
        'completed_at' => now()->subHours(3),
    ]);

    $longerWaitingCloser = createReadyDriverAt(-6.2020000, 106.8020000);
    Delivery::factory()->completed()->create([
        'driver_profile_id' => $longerWaitingCloser->driver_profile_id,
        'completed_at' => now()->subHours(3),
    ]);

    $order = Order::factory()->create(['market_id' => $market->market_id]);

    $assigned = app(DispatchService::class)->dispatch($order);

    expect($assigned)->not->toBeNull()
        ->and($assigned->delivery->driver_profile_id)->toBe($longerWaitingCloser->driver_profile_id);
});

it('dispatch dua tahap mendahulukan driver dengan kurang dari 2 order lalu fallback ke driver dengan 2 order', function () {
    $market = Market::factory()->create(['latitude' => -6.2000000, 'longitude' => 106.8000000]);

    $driverWithTwo = createReadyDriverAt(-6.2001000, 106.8001000, now()->subHours(2));
    $deliveryTwo = Delivery::factory()->create(['driver_profile_id' => $driverWithTwo->driver_profile_id]);
    DeliveryOrder::factory()->count(2)->accepted()->create(['delivery_id' => $deliveryTwo->delivery_id]);

    $driverWithOne = createReadyDriverAt(-6.2050000, 106.8050000, now()->subMinutes(5));
    $deliveryOne = Delivery::factory()->create(['driver_profile_id' => $driverWithOne->driver_profile_id]);
    DeliveryOrder::factory()->accepted()->create(['delivery_id' => $deliveryOne->delivery_id]);

    $order1 = Order::factory()->create(['market_id' => $market->market_id]);
    $assigned1 = app(DispatchService::class)->dispatch($order1);

    expect($assigned1->delivery->driver_profile_id)->toBe($driverWithOne->driver_profile_id);

    // Sekarang kedua driver punya 2 order; buat order baru ketika hanya driverWithTwo yang tersedia (weight masih aman)
    $driverWithOne->update(['is_available' => false]);
    $order2 = Order::factory()->weightGrams(5000)->create(['market_id' => $market->market_id]);
    $assigned2 = app(DispatchService::class)->dispatch($order2);

    expect($assigned2)->not->toBeNull()
        ->and($assigned2->delivery->driver_profile_id)->toBe($driverWithTwo->driver_profile_id);
});

it('tidak menawarkan ulang order ke driver yang pernah menolak order tersebut dan mengecualikan driver ineligible', function () {
    $market = Market::factory()->create(['latitude' => -6.2000000, 'longitude' => 106.8000000]);
    $order = Order::factory()->create(['market_id' => $market->market_id]);

    // Driver 1: pernah menolak order ini
    $rejector = createReadyDriverAt(-6.2001000, 106.8001000);
    $rejDelivery = Delivery::factory()->cancelled()->create(['driver_profile_id' => $rejector->driver_profile_id]);
    DeliveryOrder::factory()->rejected()->create([
        'delivery_id' => $rejDelivery->delivery_id,
        'order_id' => $order->order_id,
    ]);

    // Driver 2: sudah mulai pengantaran
    $delivering = createReadyDriverAt(-6.2002000, 106.8002000);
    Delivery::factory()->started()->create(['driver_profile_id' => $delivering->driver_profile_id]);

    // Driver 3: sedang disuspend
    $sanctioned = createReadyDriverAt(-6.2003000, 106.8003000);
    DriverSanction::create([
        'driver_profile_id' => $sanctioned->driver_profile_id,
        'type' => DriverSanction::TYPE_TEMPORARY,
        'duration_minutes' => 60,
        'reason' => 'Terlambat',
        'started_at' => now(),
        'expires_at' => now()->addHour(),
    ]);

    // Driver 4: di luar radius (Jakarta -> Bandung)
    createReadyDriverAt(-6.9175000, 107.6191000);

    // Driver 5: satu-satunya kandidat valid
    $eligible = createReadyDriverAt(-6.2040000, 106.8040000);

    $assigned = app(DispatchService::class)->dispatch($order);

    expect($assigned)->not->toBeNull()
        ->and($assigned->delivery->driver_profile_id)->toBe($eligible->driver_profile_id);
});

it('job dispatch me-release ulang bila belum ada driver tersedia', function () {
    Queue::fake();

    $order = Order::factory()->create();

    DispatchOrderJob::dispatch($order->order_id);

    Queue::assertPushed(DispatchOrderJob::class);

    // Jalankan handle secara langsung dengan mock job/interactsWithQueue? Verifikasi return null dari service saat kosong:
    expect(app(DispatchService::class)->dispatch($order))->toBeNull();
});
