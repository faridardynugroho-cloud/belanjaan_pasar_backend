<?php

namespace App\Filament\Resources\Deliveries\RelationManagers;

use App\Filament\Resources\Deliveries\DeliveryResource;
use App\Filament\Resources\Orders\OrderResource;
use App\Models\Order;
use App\Models\ShoppingRequest;
use App\Support\OrderStatus;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class OrdersRelationManager extends RelationManager
{
    protected static string $relationship = 'orders';

    protected static ?string $title = 'Pesanan dalam Pengiriman';

    // Di halaman View, relation manager default-nya read-only
    public function isReadOnly(): bool
    {
        return false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('order_number')
            ->modifyQueryUsing(fn (Builder $query) => $query->orderBy('delivery_orders.delivery_order_id'))
            ->paginated(false)
            ->columns([
                TextColumn::make('order_number')
                    ->label('No. Pesanan')
                    ->color('primary')
                    ->url(fn (Order $record) => OrderResource::getUrl('view', ['record' => $record])),
                TextColumn::make('recipient_name')->label('Penerima'),
                TextColumn::make('shipping_address')->label('Alamat')->limit(60)->wrap(),
                TextColumn::make('grand_total')->label('Total')->money('IDR', locale: 'id', decimalPlaces: 0),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => OrderStatus::label($state))
                    ->color(fn (string $state) => OrderStatus::color($state)),
            ])
            ->recordActions([
                Action::make('lokasi')
                    ->label('Maps')
                    ->icon('heroicon-o-map-pin')
                    ->color('gray')
                    ->url(fn (Order $record) => "https://www.google.com/maps?q={$record->shipping_latitude},{$record->shipping_longitude}")
                    ->openUrlInNewTab(),

                Action::make('terkirim')
                    ->label('Tandai Terkirim')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (Order $record) => $record->status === 'on_delivery'
                        && $this->getOwnerRecord()->status === 'on_the_way')
                    ->requiresConfirmation()
                    ->modalDescription('Pesanan ini ditandai Selesai. Aksi ini tidak bisa dibatalkan.')
                    ->action(function (Order $record) {
                        $delivery = $this->getOwnerRecord();

                        $pengirimanSelesai = DB::transaction(function () use ($record, $delivery) {
                            $order = Order::whereKey($record->getKey())->lockForUpdate()->first();

                            if ($order->status !== 'on_delivery') {
                                return null;
                            }

                            $order->update(['status' => 'completed']);

                            if ($order->shopping_request_id) {
                                ShoppingRequest::whereKey($order->shopping_request_id)
                                    ->where('status', 'price_sent')
                                    ->update(['status' => 'completed']);
                            }

                            $masihAda = $delivery->orders()
                                ->where('orders.status', 'on_delivery')
                                ->exists();

                            if (! $masihAda) {
                                $delivery->update(['status' => 'completed', 'completed_at' => now()]);
                            }

                            return ! $masihAda;
                        });

                        if ($pengirimanSelesai === null) {
                            Notification::make()->title('Pesanan sudah diproses sebelumnya')->warning()->send();
                        } else {
                            Notification::make()
                                ->title($pengirimanSelesai ? 'Semua pesanan terkirim, pengiriman selesai' : 'Pesanan ditandai terkirim')
                                ->success()
                                ->send();
                        }

                        // Reload halaman supaya status & tombol di header ikut update
                        $this->redirect(DeliveryResource::getUrl('view', ['record' => $delivery]));
                    }),
            ]);
    }
}
