<?php

namespace App\Filament\Resources\Deliveries\Pages;

use App\Filament\Resources\Deliveries\DeliveryResource;
use App\Models\Delivery;
use App\Models\Order;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\DB;

class ViewDelivery extends ViewRecord
{
    protected static string $resource = DeliveryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('mulai')
                ->label('Mulai Pengiriman')
                ->icon('heroicon-o-play')
                ->color('primary')
                ->visible(fn(Delivery $record) => $record->status === 'pending')
                ->requiresConfirmation()
                ->modalDescription('Semua pesanan di pengiriman ini berubah jadi "Dalam Pengiriman".')
                ->action(function (Delivery $record) {
                    DB::transaction(function () use ($record) {
                        $ids = $record->orders()->pluck('orders.order_id');

                        Order::whereIn('order_id', $ids)
                            ->where('status', 'ready_for_delivery')
                            ->update(['status' => 'on_delivery']);

                        $record->update(['status' => 'on_the_way', 'started_at' => now()]);
                    });

                    Notification::make()->title('Pengiriman dimulai')->success()->send();
                }),

            Action::make('batalkan')
                ->label('Batalkan')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->visible(fn(Delivery $record) => in_array($record->status, ['pending', 'on_the_way']))
                ->requiresConfirmation()
                ->modalDescription('Pesanan yang belum terkirim dikembalikan ke "Siap Dikirim" supaya bisa dijadwalkan ulang. Pesanan yang sudah terkirim tetap Selesai.')
                ->action(function (Delivery $record) {
                    DB::transaction(function () use ($record) {
                        $ids = $record->orders()->pluck('orders.order_id');

                        Order::whereIn('order_id', $ids)
                            ->where('status', 'on_delivery')
                            ->update(['status' => 'ready_for_delivery']);

                        $adaTerkirim = Order::whereIn('order_id', $ids)->where('status', 'completed')->exists();

                        $record->update($adaTerkirim
                            ? ['status' => 'completed', 'completed_at' => now()]
                            : ['status' => 'cancelled']);
                    });

                    Notification::make()->title('Pengiriman dibatalkan')->success()->send();
                }),
        ];
    }
}
