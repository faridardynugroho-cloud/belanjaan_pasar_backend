<?php

namespace App\Filament\Resources\Customers\RelationManagers;

use App\Filament\Resources\Orders\OrderResource;
use App\Models\Order;
use App\Support\OrderStatus;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class OrdersRelationManager extends RelationManager
{
    protected static string $relationship = 'orders';

    protected static ?string $title = 'Riwayat Pesanan';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('order_number')
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('order_number')
                    ->label('No. Pesanan')
                    ->color('primary')
                    ->url(fn (Order $record) => OrderResource::getUrl('view', ['record' => $record])),
                TextColumn::make('jenis')
                    ->label('Jenis')
                    ->state(fn (Order $record) => $record->shopping_request_id ? 'Ngebelanjain' : 'Marketplace')
                    ->badge()
                    ->color(fn (string $state) => $state === 'Ngebelanjain' ? 'warning' : 'gray'),
                TextColumn::make('grand_total')->label('Total')->money('IDR', locale: 'id', decimalPlaces: 0)->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => OrderStatus::label($state))
                    ->color(fn (string $state) => OrderStatus::color($state)),
                TextColumn::make('created_at')->label('Dibuat')->dateTime('d M Y H:i')->sortable(),
            ]);
    }
}
