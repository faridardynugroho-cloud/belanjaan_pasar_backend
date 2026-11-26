<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Filament\Resources\Orders\OrderResource;
use App\Models\Order;
use App\Models\ShoppingRequest;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\DB;

class ViewOrder extends ViewRecord
{
    protected static string $resource = OrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('proses')
                ->label('Proses Pesanan')
                ->icon('heroicon-o-play')
                ->color('info')
                ->visible(fn (Order $record) => $record->status === 'paid')
                ->requiresConfirmation()
                ->action(function (Order $record) {
                    $record->update(['status' => 'processing']);
                    Notification::make()->title('Pesanan mulai diproses')->success()->send();
                }),

            Action::make('siapKirim')
                ->label('Siap Dikirim')
                ->icon('heroicon-o-truck')
                ->color('primary')
                ->visible(fn (Order $record) => $record->status === 'processing')
                ->requiresConfirmation()
                ->action(function (Order $record) {
                    $record->update(['status' => 'ready_for_delivery']);
                    Notification::make()->title('Pesanan siap dikirim')->success()->send();
                }),

            Action::make('batalkan')
                ->label('Batalkan')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->visible(fn (Order $record) => in_array($record->status, ['pending_payment', 'paid', 'processing']))
                ->requiresConfirmation()
                ->modalDescription('Pembayaran yang masih pending ikut dibatalkan. Kalau pesanan sudah dibayar, refund diurus manual di luar sistem.')
                ->action(function (Order $record) {
                    DB::transaction(function () use ($record) {
                        $record->payments()->where('status', 'pending')->update(['status' => 'cancelled']);
                        $record->update(['status' => 'cancelled']);

                        if ($record->shopping_request_id) {
                            ShoppingRequest::whereKey($record->shopping_request_id)
                                ->where('status', 'price_sent')
                                ->update(['status' => 'cancelled']);
                        }
                    });
                    Notification::make()->title('Pesanan dibatalkan')->success()->send();
                }),
        ];
    }
}
