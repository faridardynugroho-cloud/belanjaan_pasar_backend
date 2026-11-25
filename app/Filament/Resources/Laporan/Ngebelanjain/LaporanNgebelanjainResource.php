<?php

namespace App\Filament\Resources\Laporan\Ngebelanjain;

use App\Filament\Resources\Laporan\Ngebelanjain\Pages\ListLaporanNgebelanjain;
use App\Models\ShoppingRequest;
use App\Support\Concerns\HasRentangTanggal;
use App\Support\ShoppingRequestStatus;
use Filament\Resources\Resource;
use Filament\Tables\Columns\Summarizers\Count;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use UnitEnum;

class LaporanNgebelanjainResource extends Resource
{
    use HasRentangTanggal;

    protected static ?string $model = ShoppingRequest::class;

    protected static ?string $slug = 'laporan/ngebelanjain';

    protected static string|UnitEnum|null $navigationGroup = 'Laporan';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Ngebelanjain';

    protected static ?string $modelLabel = 'Ngebelanjain';

    protected static ?string $pluralModelLabel = 'Laporan Ngebelanjain';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function judulLaporan(): string
    {
        return 'Laporan Ngebelanjain';
    }

    public static function exportKolom(): array
    {
        return [
            ['label' => 'No. Permintaan', 'lebar' => 24],
            ['label' => 'Tanggal', 'lebar' => 18],
            ['label' => 'Customer', 'lebar' => 24],
            ['label' => 'Market', 'lebar' => 22],
            ['label' => 'Status', 'lebar' => 18],
            ['label' => 'Total Belanja', 'tipe' => 'rupiah', 'lebar' => 18],
            ['label' => 'Biaya Layanan', 'tipe' => 'rupiah', 'lebar' => 18],
            ['label' => 'Total Tagihan', 'tipe' => 'rupiah', 'lebar' => 18],
        ];
    }

    public static function exportBaris(ShoppingRequest $request): array
    {
        return [
            $request->request_number,
            $request->created_at->format('d/m/Y H:i'),
            $request->user?->name,
            $request->market?->name,
            ShoppingRequestStatus::label($request->status),
            $request->total,
            $request->service_fee,
            $request->total + $request->service_fee,
        ];
    }

    public static function exportRingkasan(Collection $records): array
    {
        $selesai = $records->where('status', 'completed');

        return [
            ['label' => 'Jumlah permintaan', 'nilai' => $records->count(), 'tipe' => 'angka'],
            ['label' => 'Selesai', 'nilai' => $selesai->count(), 'tipe' => 'angka'],
            ['label' => 'Dibatalkan', 'nilai' => $records->where('status', 'cancelled')->count(), 'tipe' => 'angka'],
            ['label' => 'Total belanja (selesai)', 'nilai' => (float) $selesai->sum('total'), 'tipe' => 'rupiah'],
            ['label' => 'Biaya layanan (selesai)', 'nilai' => (float) $selesai->sum('service_fee'), 'tipe' => 'rupiah'],
        ];
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['user:user_id,name', 'market:market_id,name']))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('request_number')
                    ->label('No. Permintaan')
                    ->searchable()
                    ->summarize(Count::make()->label('Jumlah permintaan')),
                TextColumn::make('created_at')->label('Tanggal')->dateTime('d M Y H:i')->sortable(),
                TextColumn::make('user.name')->label('Customer')->searchable(),
                TextColumn::make('market.name')->label('Market'),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => ShoppingRequestStatus::label($state))
                    ->color(fn (string $state) => ShoppingRequestStatus::color($state)),
                TextColumn::make('total')
                    ->label('Total Belanja')
                    ->money('IDR', locale: 'id', decimalPlaces: 0)
                    ->summarize(Sum::make()
                        ->label('Selesai')
                        ->money('IDR', locale: 'id', decimalPlaces: 0)
                        ->query(fn ($query) => $query->where('status', 'completed'))),
                TextColumn::make('service_fee')
                    ->label('Biaya Layanan')
                    ->money('IDR', locale: 'id', decimalPlaces: 0)
                    ->summarize(Sum::make()
                        ->label('Selesai')
                        ->money('IDR', locale: 'id', decimalPlaces: 0)
                        ->query(fn ($query) => $query->where('status', 'completed'))),
            ])
            ->filters([
                static::filterTanggal('shopping_requests.created_at'),

                SelectFilter::make('market_id')
                    ->label('Market')
                    ->relationship('market', 'name'),

                SelectFilter::make('status')->options(ShoppingRequestStatus::LABELS),
            ], layout: FiltersLayout::AboveContent)
            ->filtersFormColumns(4)
            ->deferFilters(false);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListLaporanNgebelanjain::route('/'),
        ];
    }
}
