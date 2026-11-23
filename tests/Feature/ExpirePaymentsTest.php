<?php

use App\Models\Market;
use App\Models\Order;
use App\Models\Payment;
use App\Models\ShoppingRequest;
use App\Models\User;
use Illuminate\Support\Str;

beforeEach(function () {
    // Bekukan waktu di detik penuh supaya batas 10 menit bisa diuji presisi
    $this->travelTo(now()->startOfSecond());
});

function expireBuatPesanan(array $override = []): Order
{
    $customer = User::factory()->create();

    $market = Market::create([
        'name' => 'Pasar Test '.Str::random(5),
        'address' => 'Jl. Pasar Test',
        'latitude' => -6.2000000,
        'longitude' => 106.8000000,
        'is_active' => true,
    ]);

    return Order::create(array_merge([
        'user_id' => $customer->user_id,
        'market_id' => $market->market_id,
        'order_number' => 'ORD-'.Str::upper(Str::random(10)),
        'recipient_name' => 'Budi',
        'recipient_phone' => '081234567890',
        'shipping_address' => 'Jl. Contoh No. 1',
        'shipping_latitude' => -6.2100000,
        'shipping_longitude' => 106.8100000,
        'subtotal' => 50000,
        'delivery_fee' => 10000,
        'grand_total' => 60000,
        'total_weight_grams' => 1000,
        'status' => 'pending_payment',
    ], $override));
}

function expireBuatPesananNgebelanjain(string $statusRequest = 'price_sent'): Order
{
    $order = expireBuatPesanan();

    $request = ShoppingRequest::create([
        'user_id' => $order->user_id,
        'market_id' => $order->market_id,
        'request_number' => 'REQ-'.Str::upper(Str::random(10)),
        'original_list' => '1 kg beras, 2 kg gula',
        'recipient_name' => 'Budi',
        'recipient_phone' => '081234567890',
        'shipping_address' => 'Jl. Contoh No. 1',
        'shipping_latitude' => -6.2100000,
        'shipping_longitude' => 106.8100000,
        'status' => $statusRequest,
    ]);

    $order->update(['shopping_request_id' => $request->shopping_request_id]);

    return $order->refresh();
}

function expireBuatPembayaran(Order $order, string $status, $expiredAt): Payment
{
    return Payment::create([
        'order_id' => $order->order_id,
        'payment_number' => 'PAY-'.Str::upper(Str::random(10)),
        'amount' => $order->grand_total,
        'status' => $status,
        'expired_at' => $expiredAt,
        'paid_at' => $status === 'paid' ? now() : null,
    ]);
}

it('membatalkan pesanan dan mengkedaluwarsakan tagihan yang lewat batas 10 menit', function () {
    $order = expireBuatPesanan();
    $payment = expireBuatPembayaran($order, 'pending', now()->subMinutes(11));

    $this->artisan('payments:expire')
        ->expectsOutputToContain('1 pesanan dibatalkan')
        ->assertSuccessful();

    expect($payment->fresh()->status)->toBe('expired')
        ->and($order->fresh()->status)->toBe('cancelled');
});

it('belum menyentuh tagihan yang masih dalam masa tenggang 10 menit', function () {
    $order = expireBuatPesanan();
    $payment = expireBuatPembayaran($order, 'pending', now()->subMinutes(5));

    $this->artisan('payments:expire')
        ->expectsOutputToContain('0 pesanan dibatalkan')
        ->assertSuccessful();

    expect($payment->fresh()->status)->toBe('pending')
        ->and($order->fresh()->status)->toBe('pending_payment');
});

it('batasnya tepat: lewat 10 menit pas belum kedaluwarsa, lewat satu detik sudah', function () {
    $orderPas = expireBuatPesanan();
    $paymentPas = expireBuatPembayaran($orderPas, 'pending', now()->subMinutes(10));

    $orderLewat = expireBuatPesanan();
    $paymentLewat = expireBuatPembayaran($orderLewat, 'pending', now()->subMinutes(10)->subSecond());

    $this->artisan('payments:expire')->assertSuccessful();

    expect($paymentPas->fresh()->status)->toBe('pending')
        ->and($orderPas->fresh()->status)->toBe('pending_payment')
        ->and($paymentLewat->fresh()->status)->toBe('expired')
        ->and($orderLewat->fresh()->status)->toBe('cancelled');
});

it('tidak menyentuh tagihan yang expired_at-nya masih di masa depan', function () {
    $order = expireBuatPesanan();
    $payment = expireBuatPembayaran($order, 'pending', now()->addHour());

    $this->artisan('payments:expire')->assertSuccessful();

    expect($payment->fresh()->status)->toBe('pending')
        ->and($order->fresh()->status)->toBe('pending_payment');
});

it('ikut membatalkan shopping request berstatus price_sent', function () {
    $order = expireBuatPesananNgebelanjain('price_sent');
    expireBuatPembayaran($order, 'pending', now()->subMinutes(11));

    $this->artisan('payments:expire')->assertSuccessful();

    expect($order->fresh()->status)->toBe('cancelled')
        ->and(ShoppingRequest::find($order->shopping_request_id)->status)->toBe('cancelled');
});

it('tidak mengubah shopping request yang statusnya bukan price_sent', function () {
    $order = expireBuatPesananNgebelanjain('shopping');
    expireBuatPembayaran($order, 'pending', now()->subMinutes(11));

    $this->artisan('payments:expire')->assertSuccessful();

    expect($order->fresh()->status)->toBe('cancelled')
        ->and(ShoppingRequest::find($order->shopping_request_id)->status)->toBe('shopping');
});

it('tidak membatalkan pesanan kalau masih ada tagihan pending yang aktif', function () {
    $order = expireBuatPesanan();
    $lama = expireBuatPembayaran($order, 'pending', now()->subMinutes(11));
    $baru = expireBuatPembayaran($order, 'pending', now()->addMinutes(30));

    $this->artisan('payments:expire')
        ->expectsOutputToContain('0 pesanan dibatalkan')
        ->assertSuccessful();

    expect($lama->fresh()->status)->toBe('expired')
        ->and($baru->fresh()->status)->toBe('pending')
        ->and($order->fresh()->status)->toBe('pending_payment');
});

it('tidak membatalkan pesanan yang sudah ada tagihan paid', function () {
    $order = expireBuatPesanan(['status' => 'paid']);
    $lama = expireBuatPembayaran($order, 'pending', now()->subMinutes(11));
    $lunas = expireBuatPembayaran($order, 'paid', now()->addMinutes(30));

    $this->artisan('payments:expire')->assertSuccessful();

    expect($lama->fresh()->status)->toBe('expired')
        ->and($lunas->fresh()->status)->toBe('paid')
        ->and($order->fresh()->status)->toBe('paid');
});

it('tidak membatalkan pesanan yang statusnya sudah bukan pending_payment', function () {
    $order = expireBuatPesanan(['status' => 'processing']);
    $payment = expireBuatPembayaran($order, 'pending', now()->subMinutes(11));

    $this->artisan('payments:expire')->assertSuccessful();

    expect($payment->fresh()->status)->toBe('expired')
        ->and($order->fresh()->status)->toBe('processing');
});

it('hanya memproses pesanan yang lewat batas, pesanan lain aman', function () {
    $basi = expireBuatPesanan();
    expireBuatPembayaran($basi, 'pending', now()->subMinutes(30));

    $aktif = expireBuatPesanan();
    $paymentAktif = expireBuatPembayaran($aktif, 'pending', now()->addMinutes(30));

    $this->artisan('payments:expire')
        ->expectsOutputToContain('1 pesanan dibatalkan')
        ->assertSuccessful();

    expect($basi->fresh()->status)->toBe('cancelled')
        ->and($aktif->fresh()->status)->toBe('pending_payment')
        ->and($paymentAktif->fresh()->status)->toBe('pending');
});

it('aman dijalankan berulang (idempotent)', function () {
    $order = expireBuatPesanan();
    expireBuatPembayaran($order, 'pending', now()->subMinutes(11));

    $this->artisan('payments:expire')->expectsOutputToContain('1 pesanan dibatalkan')->assertSuccessful();
    $this->artisan('payments:expire')->expectsOutputToContain('0 pesanan dibatalkan')->assertSuccessful();

    expect($order->fresh()->status)->toBe('cancelled');
});