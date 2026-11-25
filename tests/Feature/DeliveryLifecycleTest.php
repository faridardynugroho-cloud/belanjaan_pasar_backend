<?php

use App\Enums\DeliveryOrderStatus;
use App\Exceptions\DeliveryRuleException;
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

it('driver dapat menerima order pending dan menolak order pending tanpa membatalkan order lain', function () {
    Queue::fake();

    $driver = DriverProfile::factory()->approved()->available()->create();
    $delivery = Delivery::factory()->create(['driver_profile_id' => $driver->driver_profile_id]);

    $orderA = Order::factory()->create();
    $orderB = Order::factory()->create();

    $doA = DeliveryOrder::factory()->create([
        'delivery_id' => $delivery->delivery_id,
        'order_id' => $orderA->order_id,
    ]);
    $doB = DeliveryOrder::factory()->create([
        'delivery_id' => $delivery->delivery_id,
        'order_id' => $orderB->order_id,
    ]);

    $this->actingAs($driver->user, 'sanctum')
        ->postJson("/api/driver/delivery-orders/{$doA->delivery_order_id}/accept")
        ->assertOk()
        ->assertJsonPath('delivery_order.status', DeliveryOrderStatus::Accepted->value);

    $this->actingAs($driver->user, 'sanctum')
        ->postJson("/api/driver/delivery-orders/{$doB->delivery_order_id}/reject", [
            'rejection_reason' => 'Lokasi terlalu jauh',
        ])
        ->assertOk()
        ->assertJsonPath('delivery_order.status', DeliveryOrderStatus::Rejected->value)
        ->assertJsonPath('delivery_order.rejection_reason', 'Lokasi terlalu jauh');

    expect($doA->fresh()->status)->toBe(DeliveryOrderStatus::Accepted->value)
        ->and($doB->fresh()->rejected_at)->not->toBeNull()
        ->and($delivery->fresh()->cancelled_at)->toBeNull();

    Queue::assertPushed(DispatchOrderJob::class, fn (DispatchOrderJob $job) => $job->orderId === $orderB->order_id);
});

it('menolak semua order pada satu delivery mengisi cancelled_at dan me-redispatch ke driver lain', function () {
    $market = Market::factory()->create(['latitude' => -6.2000000, 'longitude' => 106.8000000]);

    $driver1 = DriverProfile::factory()->approved()->available()->create();
    DriverLocation::factory()->create(['user_id' => $driver1->user_id, 'latitude' => -6.2001, 'longitude' => 106.8001]);

    $driver2 = DriverProfile::factory()->approved()->available()->create();
    DriverLocation::factory()->create(['user_id' => $driver2->user_id, 'latitude' => -6.2010, 'longitude' => 106.8010]);

    $order = Order::factory()->create(['market_id' => $market->market_id]);
    $do = app(DispatchService::class)->dispatch($order);
    $originalDeliveryId = $do->delivery_id;

    // Driver 1 menolak -> otomatis re-dispatch (sync queue di test) ke Driver 2!
    $this->actingAs($driver1->user, 'sanctum')
        ->postJson("/api/driver/delivery-orders/{$do->delivery_order_id}/reject", [
            'rejection_reason' => 'Motor bermasalah',
        ])
        ->assertOk();

    // Riwayat penolakan tetap ada, delivery lama cancelled, order ditawarkan ke driver 2
    expect(DeliveryOrder::find($do->delivery_order_id)->status)->toBe(DeliveryOrderStatus::Rejected->value)
        ->and(Delivery::find($originalDeliveryId)->cancelled_at)->not->toBeNull();

    $newOffer = DeliveryOrder::where('order_id', $order->order_id)
        ->where('status', DeliveryOrderStatus::Pending->value)
        ->first();

    expect($newOffer)->not->toBeNull()
        ->and($newOffer->delivery->driver_profile_id)->toBe($driver2->driver_profile_id);
});

it('melarang tolak order saat sudah on_the_way atau accepted dan melarang akses bukan pemilik', function () {
    $driver = DriverProfile::factory()->approved()->available()->create();
    $otherDriver = DriverProfile::factory()->approved()->available()->create();
    $delivery = Delivery::factory()->started()->create(['driver_profile_id' => $driver->driver_profile_id]);

    $onTheWayDo = DeliveryOrder::factory()->onTheWay()->create(['delivery_id' => $delivery->delivery_id]);

    $this->actingAs($driver->user, 'sanctum')
        ->postJson("/api/driver/delivery-orders/{$onTheWayDo->delivery_order_id}/reject", [
            'rejection_reason' => 'Ingin batal',
        ])
        ->assertUnprocessable()
        ->assertJsonPath('error_code', DeliveryRuleException::CODE_DELIVERY_ALREADY_STARTED);

    $this->actingAs($otherDriver->user, 'sanctum')
        ->postJson("/api/driver/delivery-orders/{$onTheWayDo->delivery_order_id}/complete")
        ->assertForbidden()
        ->assertJsonPath('error_code', DeliveryRuleException::CODE_NOT_OWNER);
});

it('perjalanan tetap berlangsung sampai semua order selesai lalu otomatis selesai dan memicu sanksi menunggu', function () {
    $driver = DriverProfile::factory()->approved()->available()->create();
    $delivery = Delivery::factory()->create(['driver_profile_id' => $driver->driver_profile_id]);

    $order1 = Order::factory()->create();
    $order2 = Order::factory()->create();
    $order3 = Order::factory()->create();

    $do1 = DeliveryOrder::factory()->accepted()->create(['delivery_id' => $delivery->delivery_id, 'order_id' => $order1->order_id]);
    $do2 = DeliveryOrder::factory()->accepted()->create(['delivery_id' => $delivery->delivery_id, 'order_id' => $order2->order_id]);
    $do3 = DeliveryOrder::factory()->create(['delivery_id' => $delivery->delivery_id, 'order_id' => $order3->order_id]);

    // Tidak bisa mulai jika masih ada pending
    $this->actingAs($driver->user, 'sanctum')
        ->postJson("/api/driver/deliveries/{$delivery->delivery_id}/start")
        ->assertUnprocessable()
        ->assertJsonPath('error_code', DeliveryRuleException::CODE_PENDING_ORDERS_EXIST);

    // Tolak do3 dulu
    Queue::fake();
    $this->actingAs($driver->user, 'sanctum')
        ->postJson("/api/driver/delivery-orders/{$do3->delivery_order_id}/reject", ['rejection_reason' => 'Penuh'])
        ->assertOk();

    // Buat sanksi menunggu sebelum start
    $waitingSanction = DriverSanction::create([
        'driver_profile_id' => $driver->driver_profile_id,
        'type' => DriverSanction::TYPE_TEMPORARY,
        'duration_minutes' => 60,
        'reason' => 'Menunggu selesai antar',
        'started_at' => null,
        'expires_at' => null,
    ]);

    // Mulai pengantaran
    $this->actingAs($driver->user, 'sanctum')
        ->postJson("/api/driver/deliveries/{$delivery->delivery_id}/start")
        ->assertOk()
        ->assertJsonPath('delivery.status', Delivery::STATUS_ON_THE_WAY);

    expect($delivery->fresh()->started_at)->not->toBeNull()
        ->and($do1->fresh()->status)->toBe(DeliveryOrderStatus::OnTheWay->value)
        ->and($order1->fresh()->status)->toBe(Order::STATUS_ON_DELIVERY);

    // Current delivery menampilkan perjalanan aktif
    $this->actingAs($driver->user, 'sanctum')
        ->getJson('/api/driver/deliveries/current')
        ->assertOk()
        ->assertJsonPath('delivery.delivery_id', $delivery->delivery_id);

    // Selesaikan order 1 -> delivery masih berjalan
    $this->actingAs($driver->user, 'sanctum')
        ->postJson("/api/driver/delivery-orders/{$do1->delivery_order_id}/complete", [
            'received_by' => DeliveryOrder::RECEIVED_CUSTOMER,
        ])
        ->assertOk()
        ->assertJsonPath('delivery_order.status', DeliveryOrderStatus::Completed->value);

    expect($delivery->fresh()->completed_at)->toBeNull()
        ->and($order1->fresh()->status)->toBe(Order::STATUS_COMPLETED)
        ->and($waitingSanction->fresh()->started_at)->toBeNull();

    // Selesaikan order 2 -> delivery auto-complete & sanksi menunggu mulai berjalan
    $this->actingAs($driver->user, 'sanctum')
        ->postJson("/api/driver/delivery-orders/{$do2->delivery_order_id}/complete")
        ->assertOk();

    expect($delivery->fresh()->completed_at)->not->toBeNull()
        ->and($order2->fresh()->status)->toBe(Order::STATUS_COMPLETED)
        ->and($waitingSanction->fresh()->started_at)->not->toBeNull();

    // Current sekarang null dan history berisi 1 delivery
    $this->actingAs($driver->user, 'sanctum')
        ->getJson('/api/driver/deliveries/current')
        ->assertOk()
        ->assertJsonPath('delivery', null);

    $this->actingAs($driver->user, 'sanctum')
        ->getJson('/api/driver/deliveries/history')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.delivery_id', $delivery->delivery_id);
});
