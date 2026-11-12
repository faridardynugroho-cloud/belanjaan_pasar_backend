<?php

namespace App\Filament\Resources\Deliveries\Pages;

use App\Filament\Resources\Deliveries\DeliveryResource;
use App\Models\Delivery;
use App\Models\DeliveryOrder;
use App\Models\Order;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CreateDelivery extends CreateRecord
{
    protected static string $resource = DeliveryResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $orderIds = array_values($data['order_ids']);

        return DB::transaction(function () use ($data, $orderIds) {
            $gagal = function (string $pesan): never {
                Notification::make()
                    ->title('Pengiriman gagal dibuat')
                    ->body($pesan)
                    ->danger()
                    ->send();

                throw new Halt();
            };

            // Kunci baris pesanan dulu, supaya admin lain yang submit bersamaan menunggu
            $orders = Order::whereIn('order_id', $orderIds)->lockForUpdate()->get();

            $masihTersedia = DeliveryResource::availableOrdersQuery()
                ->whereIn('order_id', $orderIds)
                ->count();

            if ($orders->count() !== count($orderIds) || $masihTersedia !== count($orderIds)) {
                $gagal('Ada pesanan yang sudah tidak berstatus Siap Dikirim atau sudah masuk pengiriman lain.');
            }

            if (count($orderIds) > DeliveryResource::MAX_ORDERS) {
                $gagal('Maksimal ' . DeliveryResource::MAX_ORDERS . ' pesanan per pengiriman.');
            }

            if ($orders->sum('total_weight_grams') > DeliveryResource::MAX_GRAMS) {
                $gagal('Total berat melebihi 50 kg.');
            }

            $delivery = Delivery::create([
                'driver_id' => $data['driver_id'],
                'delivery_number' => 'DLV-' . now()->format('Ymd') . '-' . Str::upper(Str::random(6)),
                'status' => 'pending',
            ]);

            foreach ($orders as $order) {
                DeliveryOrder::create([
                    'delivery_id' => $delivery->getKey(),
                    'order_id' => $order->getKey(),
                    'assigned_at' => now(),
                ]);
            }

            return $delivery;
        });
    }
}