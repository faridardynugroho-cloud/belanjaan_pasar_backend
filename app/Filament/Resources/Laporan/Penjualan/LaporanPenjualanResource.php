<?php

namespace App\Filament\Resources\Laporan\Penjualan;

use App\Filament\Resources\Laporan\Penjualan\Pages\ListLaporanPenjualan;
use App\Models\Order;
use App\Support\Concerns\HasRentangTanggal;
use App\Support\OrderStatus;
use Filament\Resources\Resource;
use Filament\Tables\Columns\Summarizers\Count;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use UnitEnum;

class LaporanPenjualanResource extends Resource
{
    use HasRentangTanggal;

    protected static ?string $model = Order::class;

    protected static ?string $slug = 'laporan/penjualan';

    protected static string|UnitEnum|null $navigationGroup = 'Laporan';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Penjualan';

    protected static ?string $modelLabel = 'Penjualan';

    protected static ?string $pluralModelLabel = 'Laporan Penjualan';

    public static function canCreate(): bool
    {
        return false;
    }

    // Penjualan = pesanan yang sudah dibayar dan tidak dibatalkan
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->whereIn('orders.status', OrderStatus::SALES_STATUSES);
    }

    public static function judulLaporan(): string
    {
        return 'Laporan Penjualan';
    }

    public static function jenis(Order $order): string
    {
        return $order->shopping_request_id ? 'Ngebelanjain' : 'Marketplace';
    }

    public static function exportKolom(): array
    {
        return [
            ['label' => 'No. Pesanan', 'lebar' => 24],
            ['label' => 'Tanggal', 'lebar' => 18],
            ['label' => 'Customer', 'lebar' => 24],
            ['label' => 'Market', 'lebar' => 22],
            ['label' => 'Jenis', 'lebar' => 14],
            ['label' => 'Subtotal', 'tipe' => 'rupiah', 'lebar' => 16],
            ['label' => 'Ongkir/Layanan', 'tipe' => 'rupiah', 'lebar' => 16],
            ['label' => 'Total', 'tipe' => 'rupiah', 'lebar' => 16],
            ['label' => 'Status', 'lebar' => 20],
        ];
    }

    public static function exportBaris(Order $order): array
    {
        return [
            $order->order_number,
            $order->created_at->format('d/m/Y H:i'),
            $order->user?->name,
            $order->market?->name,
            self::jenis($order),
            $order->subtotal,
            $order->delivery_fee,
            $order->grand_total,
            OrderStatus::label($order->status),
        ];
    }

    public static function exportRingkasan(Collection $records): array
    {
        $total = (float) $records->sum('grand_total');
        $jumlah = $records->count();

        return [
            ['label' => 'Total penjualan', 'nilai' => $total, 'tipe' => 'rupiah'],
            ['label' => 'Jumlah pesanan', 'nilai' => $jumlah, 'tipe' => 'angka'],
            ['label' => 'Rata-rata per pesanan', 'nilai' => $jumlah ? $total / $jumlah : 0, 'tipe' => 'rupiah'],
        ];
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['user:user_id,name', 'market:market_id,name']))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('order_number')
                    ->label('No. Pesanan')
                    ->searchable()
                    ->summarize(Count::make()->label('Jumlah pesanan')),
                TextColumn::make('created_at')->label('Tanggal')->dateTime('d M Y H:i')->sortable(),
                TextColumn::make('user.name')->label('Customer')->searchable(),
                TextColumn::make('market.name')->label('Market'),
                TextColumn::make('jenis')
                    ->label('Jenis')
                    ->state(fn (Order $record) => self::jenis($record))
                    ->badge()
                    ->color(fn (string $state) => $state === 'Ngebelanjain' ? 'warning' : 'gray'),
                TextColumn::make('subtotal')->money('IDR', locale: 'id', decimalPlaces: 0),
                TextColumn::make('delivery_fee')->label('Ongkir/Layanan')->money('IDR', locale: 'id', decimalPlaces: 0),
                TextColumn::make('grand_total')
                    ->label('Total')
                    ->money('IDR', locale: 'id', decimalPlaces: 0)
                    ->sortable()
                    ->summarize(Sum::make()->label('Total penjualan')->money('IDR', locale: 'id', decimalPlaces: 0)),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => OrderStatus::label($state))
                    ->color(fn (string $state) => OrderStatus::color($state)),
            ])
            ->filters([
                static::filterTanggal('orders.created_at'),

                SelectFilter::make('market_id')
                    ->label('Market')
                    ->relationship('market', 'name'),

                TernaryFilter::make('jenis')
                    ->label('Jenis')
                    ->placeholder('Semua')
                    ->trueLabel('Ngebelanjain')
                    ->falseLabel('Marketplace')
                    ->queries(
                        true: fn (Builder $q) => $q->whereNotNull('orders.shopping_request_id'),
                        false: fn (Builder $q) => $q->whereNull('orders.shopping_request_id'),
                        blank: fn (Builder $q) => $q,
                    ),
            ], layout: FiltersLayout::AboveContent)
            ->filtersFormColumns(4)
            ->deferFilters(false);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListLaporanPenjualan::route('/'),
        ];
    }
}
