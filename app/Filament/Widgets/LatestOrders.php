<?php

namespace App\Filament\Widgets;

use App\Models\Order;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use App\Support\OrderStatus;

class LatestOrders extends TableWidget
{
    protected static ?int $sort = 3;

    protected int | string | array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->heading('Pesanan Terbaru')
            ->query(fn () => Order::query()->latest()->limit(5))
            ->paginated(false)
            ->columns([
                TextColumn::make('order_number')
                    ->label('No. Pesanan'),
                TextColumn::make('recipient_name')
                    ->label('Penerima'),
                TextColumn::make('grand_total')
                    ->label('Total')
                    ->money('IDR', locale: 'id'),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => OrderStatus::label($state))
                    ->color(fn (string $state) => OrderStatus::color($state)),
                TextColumn::make('created_at')
                    ->label('Dibuat')
                    ->dateTime('d M Y H:i'),
            ]);
    }
}