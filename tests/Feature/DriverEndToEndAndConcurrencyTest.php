<?php

use App\Enums\ApprovalStatus;
use App\Enums\DeliveryOrderStatus;
use App\Enums\UserRole;
use App\Exceptions\DeliveryRuleException;
use App\Models\Delivery;
use App\Models\DeliveryOrder;
use App\Models\DriverLocation;
use App\Models\DriverProfile;
use App\Models\Market;
use App\Models\Order;
use App\Models\User;
use App\Services\DeliveryService;
use App\Services\DispatchService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

function e2eFakePng(string $name): UploadedFile
{
    $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==');

    return UploadedFile::fake()->createWithContent($name, $png);
}

it('end-to-end: daftar -> approve -> online -> dapat order -> terima -> mulai -> selesai', function () {
    Storage::fake('public');

    // 1. Daftar driver
    $registerResponse = $this->postJson('/api/driver/register', [
        'name' => 'Andi Driver',
        'email' => 'andi.e2e@example.test',
        'password' => 'secret1234',
        'phone' => '081399990001',
        'gender' => 'male',
        'birth_date' => '1994-03-10',
        'domicile_address' => 'Jl. Pasar Minggu No. 5',
        'nik' => '3174010101940001',
        'ktp_photo' => e2eFakePng('ktp.png'),
        'selfie_photo' => e2eFakePng('selfie.png'),
        'sim_type' => 'C',
        'sim_number' => '940312345678',
        'sim_photo' => e2eFakePng('sim.png'),
        'sim_expired_at' => now()->addYears(2)->toDateString(),
        'vehicle_type' => 'Motor',
        'vehicle_brand' => 'Yamaha',
        'vehicle_model' => 'NMAX',
        'vehicle_plate' => 'B 9001 E2E',
        'vehicle_color' => 'Putih',
        'production_year' => 2023,
        'vehicle_photo' => e2eFakePng('vehicle.png'),
        'stnk_photo' => e2eFakePng('stnk.png'),
    ])->assertCreated();

    $driverToken = $registerResponse->json('token');
    $driverProfileId = $registerResponse->json('driver.driver_profile_id');

    // 2. Admin approve
    $admin = User::factory()->create(['role' => UserRole::Admin->value]);

    $this->actingAs($admin, 'sanctum')
        ->postJson("/api/admin/drivers/{$driverProfileId}/approve")
        ->assertOk()
        ->assertJsonPath('driver.approval_status', ApprovalStatus::Approved->value);

    // Ambil user driver yang sudah approved dari DB
    $driverUser = User::where('email', 'andi.e2e@example.test')->firstOrFail();

    // 3. Driver online & kirim lokasi GPS
    $this->actingAs($driverUser, 'sanctum')
        ->patchJson('/api/driver/availability', ['is_available' => true])
        ->assertOk()
        ->assertJsonPath('driver.is_available', true);

    $this->actingAs($driverUser, 'sanctum')
        ->putJson('/api/driver/location', [
            'latitude' => -6.2005000,
            'longitude' => 106.8005000,
        ])
        ->assertOk();

    // 4. Sistem dispatch order siap kirim
    $market = Market::factory()->create(['latitude' => -6.2000000, 'longitude' => 106.8000000]);
    $order = Order::factory()->weightGrams(15000)->create(['market_id' => $market->market_id]);

    $assigned = app(DispatchService::class)->dispatch($order);
    expect($assigned)->not->toBeNull();

    // 5. Driver cek current delivery, terima, mulai, selesaikan, cek history
    $current = $this->actingAs($driverUser, 'sanctum')
        ->getJson('/api/driver/deliveries/current')
        ->assertOk()
        ->assertJsonPath('delivery.delivery_orders.0.status', DeliveryOrderStatus::Pending->value);

    $deliveryId = $current->json('delivery.delivery_id');
    $deliveryOrderId = $current->json('delivery.delivery_orders.0.delivery_order_id');

    $this->actingAs($driverUser, 'sanctum')
        ->postJson("/api/driver/delivery-orders/{$deliveryOrderId}/accept")
        ->assertOk()
        ->assertJsonPath('delivery_order.status', DeliveryOrderStatus::Accepted->value);

    $this->actingAs($driverUser, 'sanctum')
        ->postJson("/api/driver/deliveries/{$deliveryId}/start")
        ->assertOk()
        ->assertJsonPath('delivery.status', Delivery::STATUS_ON_THE_WAY);

    $this->actingAs($driverUser, 'sanctum')
        ->postJson("/api/driver/delivery-orders/{$deliveryOrderId}/complete", [
            'received_by' => DeliveryOrder::RECEIVED_CUSTOMER,
            'received_note' => 'Diterima langsung',
        ])
        ->assertOk()
        ->assertJsonPath('delivery_order.status', DeliveryOrderStatus::Completed->value);

    $this->actingAs($driverUser, 'sanctum')
        ->getJson('/api/driver/deliveries/history')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.status', Delivery::STATUS_COMPLETED);
});

it('menjaga batas maksimal 3 order dan 50 kg saat dispatch beruntun dan penerimaan order', function () {
    $market = Market::factory()->create(['latitude' => -6.2000000, 'longitude' => 106.8000000]);
    $driver = DriverProfile::factory()->approved()->available()->create();

    DriverLocation::factory()->create([
        'user_id' => $driver->user_id,
        'latitude' => -6.2001000,
        'longitude' => 106.8001000,
    ]);

    $dispatchService = app(DispatchService::class);
    $deliveryService = app(DeliveryService::class);

    // 3 order @ 10 kg berhasil masuk dan diterima
    $orders = Order::factory()->count(4)->weightGrams(10000)->create(['market_id' => $market->market_id]);

    foreach ($orders->take(3) as $order) {
        $do = $dispatchService->dispatch($order);
        expect($do)->not->toBeNull();
        $deliveryService->accept($driver, $do);
    }

    // Order ke-4 tidak bisa di-dispatch ke driver yang sudah 3 order
    expect($dispatchService->dispatch($orders[3]))->toBeNull();

    // Simulasi kondisi ekstrem: jika 2 delivery_order pending total 60 kg (30 kg + 30 kg) ada di DB,
    // accept kedua ditolak oleh CapacityChecker di dalam lockForUpdate()
    $driver2 = DriverProfile::factory()->approved()->available()->create();
    $delivery2 = Delivery::factory()->create(['driver_profile_id' => $driver2->driver_profile_id]);
    $heavy1 = DeliveryOrder::factory()->create([
        'delivery_id' => $delivery2->delivery_id,
        'order_id' => Order::factory()->weightGrams(30000)->create()->order_id,
    ]);
    $heavy2 = DeliveryOrder::factory()->create([
        'delivery_id' => $delivery2->delivery_id,
        'order_id' => Order::factory()->weightGrams(30000)->create()->order_id,
    ]);

    expect(fn () => $deliveryService->accept($driver2, $heavy1))
        ->toThrow(DeliveryRuleException::class);

    // Setelah heavy2 ditolak, heavy1 (30 kg) langsung bisa diterima
    $deliveryService->reject($driver2, $heavy2, 'Terlalu berat');
    $acceptedHeavy1 = $deliveryService->accept($driver2, $heavy1);
    expect($acceptedHeavy1->status)->toBe(DeliveryOrderStatus::Accepted->value);
});
