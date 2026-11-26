<?php

use App\Exceptions\DeliveryRuleException;
use App\Models\Delivery;
use App\Models\DeliveryOrder;
use App\Models\Order;
use App\Services\CapacityChecker;

it('mengizinkan maksimal 3 order dan menolak order ke-4', function () {
    $checker = new CapacityChecker;
    $delivery = Delivery::factory()->create();

    for ($i = 0; $i < 3; $i++) {
        $order = Order::factory()->weightGrams(5000)->create();
        expect($checker->canAdd($delivery, $order))->toBeTrue();
        DeliveryOrder::factory()->accepted()->create([
            'delivery_id' => $delivery->delivery_id,
            'order_id' => $order->order_id,
        ]);
    }

    $fourthOrder = Order::factory()->weightGrams(5000)->create();

    expect($checker->canAdd($delivery, $fourthOrder))->toBeFalse()
        ->and(fn () => $checker->assertCanAdd($delivery, $fourthOrder))
        ->toThrow(DeliveryRuleException::class);
});

it('mengizinkan 40 kg + 10 kg dan menolak 40 kg + 10.001 kg', function () {
    $checker = new CapacityChecker;
    $delivery = Delivery::factory()->create();

    $heavyOrder = Order::factory()->weightGrams(40000)->create();
    DeliveryOrder::factory()->accepted()->create([
        'delivery_id' => $delivery->delivery_id,
        'order_id' => $heavyOrder->order_id,
    ]);

    $exactFitOrder = Order::factory()->weightGrams(10000)->create();
    expect($checker->canAdd($delivery, $exactFitOrder))->toBeTrue();

    $overWeightOrder = Order::factory()->weightGrams(10001)->create();
    expect($checker->canAdd($delivery, $overWeightOrder))->toBeFalse()
        ->and(fn () => $checker->assertCanAdd($delivery, $overWeightOrder))
        ->toThrow(DeliveryRuleException::class);
});

it('mengabaikan order rejected dari hitungan kapasitas dan berat', function () {
    $checker = new CapacityChecker;
    $delivery = Delivery::factory()->create();

    $rejectedOrder = Order::factory()->weightGrams(45000)->create();
    DeliveryOrder::factory()->rejected()->create([
        'delivery_id' => $delivery->delivery_id,
        'order_id' => $rejectedOrder->order_id,
    ]);

    $load = $checker->currentLoad($delivery);
    $newOrder = Order::factory()->weightGrams(20000)->create();

    expect($load)->toBe(['orders' => 0, 'weight_grams' => 0])
        ->and($checker->canAdd($delivery, $newOrder))->toBeTrue();
});

it('menolak tambahan order jika delivery sudah dimulai', function () {
    $checker = new CapacityChecker;
    $delivery = Delivery::factory()->started()->create();
    $order = Order::factory()->weightGrams(5000)->create();

    expect($checker->canAdd($delivery, $order))->toBeFalse()
        ->and(fn () => $checker->assertCanAdd($delivery, $order))
        ->toThrow(DeliveryRuleException::class);
});
