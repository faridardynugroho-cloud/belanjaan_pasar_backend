<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Models\Payment;
use App\Models\ShoppingRequest;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ExpirePayments extends Command
{
    protected $signature = 'payments:expire';

    protected $description = 'Kedaluwarsakan tagihan yang lewat batas waktu dan batalkan pesanan yang belum dibayar';

    public function handle(): int
    {
        $orderIds = Payment::where('status', 'pending')
            ->where('expired_at', '<', now()->subMinutes(10))
            ->pluck('order_id')
            ->unique();

        $dibatalkan = 0;

        foreach ($orderIds as $orderId) {
            DB::transaction(function () use ($orderId, &$dibatalkan) {
                $order = Order::whereKey($orderId)->lockForUpdate()->first();

                if (! $order) {
                    return;
                }

                $order->payments()
                    ->where('status', 'pending')
                    ->where('expired_at', '<', now()->subMinutes(10))
                    ->update(['status' => 'expired']);

                // Batalkan pesanan hanya kalau belum ada tagihan aktif / yang sudah dibayar
                if ($order->status !== 'pending_payment'
                    || $order->payments()->whereIn('status', ['pending', 'paid'])->exists()) {
                    return;
                }

                $order->update(['status' => 'cancelled']);

                if ($order->shopping_request_id) {
                    ShoppingRequest::whereKey($order->shopping_request_id)
                        ->where('status', 'price_sent')
                        ->update(['status' => 'cancelled']);
                }

                $dibatalkan++;
            });
        }

        $this->info("Selesai. {$dibatalkan} pesanan dibatalkan karena lewat batas pembayaran.");

        return self::SUCCESS;
    }
}