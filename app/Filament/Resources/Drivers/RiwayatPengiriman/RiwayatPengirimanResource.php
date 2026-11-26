<?php

namespace App\Filament\Resources\Drivers\RiwayatPengiriman;

use App\Filament\Resources\Drivers\RiwayatPengiriman\Pages\ListRiwayatPengiriman;
use App\Filament\Resources\Drivers\RiwayatPengiriman\Pages\ViewRiwayatPengiriman;
use App\Filament\Resources\Drivers\RiwayatPengiriman\RelationManagers\OrderPengirimanRelationManager;
use App\Filament\Resources\Drivers\RiwayatPengiriman\Widgets\PerformaDriverTable;
use App\Filament\Resources\Drivers\RiwayatSanksi\RiwayatSanksiResource;
use App\Models\Delivery;
use App\Support\DeliveryStatus;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use UnitEnum;

class RiwayatPengirimanResource extends Resource
{
    protected static ?string $model = Delivery::class;

    protected static ?string $slug = 'driver/riwayat-pengiriman';

    protected static string|UnitEnum|null $navigationGroup = 'Driver';

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationLabel = 'Riwayat Pengiriman & Performa';

    protected static ?string $modelLabel = 'Pengiriman';

    protected static ?string $pluralModelLabel = 'Riwayat Pengiriman';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function getRecordTitle(?Model $record): string|Htmlable|null
    {
        return $record?->delivery_number;
    }

    public static function getWidgets(): array
    {
        return [
            PerformaDriverTable::class,
        ];
    }

    public static function getRelations(): array
    {
        return [
            OrderPengirimanRelationManager::class,
        ];
    }

    /**
     * Satu perjalanan lengkap dengan angka turunannya: jumlah pesanan, berat, rating (hanya review
     * yang ditampilkan), dan jumlah komplain customer. Dipakai tabel dan halaman detail.
     */
    public static function getEloquentQuery(): Builder
    {
        $perTrip = fn (string $tabel) => DB::table($tabel)
            ->join('delivery_orders', 'delivery_orders.delivery_order_id', '=', $tabel.'.delivery_order_id')
            ->whereColumn('delivery_orders.delivery_id', 'deliveries.delivery_id');

        return parent::getEloquentQuery()
            ->with(['driver.user'])
            ->withCount('activeOrders as jumlah_order')
            ->withSum('activeOrders as berat_gram', 'total_weight_grams')
            ->addSelect([
                'rating_trip' => $perTrip('driver_reviews')
                    ->where('driver_reviews.is_visible', true)
                    ->selectRaw('AVG(driver_reviews.rating)'),
                'jumlah_review' => $perTrip('driver_reviews')
                    ->where('driver_reviews.is_visible', true)
                    ->selectRaw('COUNT(*)'),
                'komplain_count' => $perTrip('driver_violations')
                    ->where('driver_violations.source', 'customer')
                    ->selectRaw('COUNT(*)'),
            ]);
    }

    // ===== Helper =====

    public static function durasiMenit(Delivery $trip): ?int
    {
        if (! $trip->started_at || ! $trip->completed_at) {
            return null;
        }

        return (int) round($trip->started_at->diffInMinutes($trip->completed_at));
    }

    public static function labelDurasi(Delivery $trip): string
    {
        return static::formatDurasi(static::durasiMenit($trip));
    }

    public static function formatDurasi($menit): string
    {
        if ($menit === null) {
            return '-';
        }

        $menit = (int) round($menit);

        return $menit >= 60 ? intdiv($menit, 60).' j '.($menit % 60).' m' : $menit.' m';
    }

    public static function labelBerat($gram): string
    {
        return number_format(((int) $gram) / 1000, 2, ',', '.').' kg';
    }

    public static function labelRating($rata, $jumlah): string
    {
        return $jumlah > 0
            ? '★ '.number_format((float) $rata, 1).' ('.$jumlah.' review)'
            : 'Belum ada review';
    }

    // ===== Detail =====

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Info pengiriman')
                ->columns(4)
                ->schema([
                    TextEntry::make('delivery_number')->label('No. Pengiriman')->copyable(),

                    TextEntry::make('status')
                        ->badge()
                        ->formatStateUsing(fn (string $state) => DeliveryStatus::label($state))
                        ->color(fn (string $state) => DeliveryStatus::color($state)),

                    TextEntry::make('driver.user.name')->label('Driver')->placeholder('-'),

                    TextEntry::make('durasi')
                        ->label('Durasi pengiriman')
                        ->state(fn (Delivery $record) => static::labelDurasi($record)),

                    TextEntry::make('started_at')->label('Mulai')->dateTime('d M Y H:i')->placeholder('Belum berangkat'),
                    TextEntry::make('completed_at')->label('Selesai')->dateTime('d M Y H:i')->placeholder('-'),
                    TextEntry::make('cancelled_at')->label('Dibatalkan')->dateTime('d M Y H:i')->placeholder('-'),

                    TextEntry::make('muatan')
                        ->label('Muatan')
                        ->state(fn (Delivery $record) => $record->jumlah_order.' pesanan, '.static::labelBerat($record->berat_gram)),
                ]),

            Section::make('Penilaian dan komplain')
                ->columns(2)
                ->schema([
                    TextEntry::make('rating')
                        ->label('Rating customer')
                        ->state(fn (Delivery $record) => static::labelRating($record->rating_trip, (int) $record->jumlah_review)),

                    TextEntry::make('komplain')
                        ->label('Komplain customer')
                        ->state(fn (Delivery $record) => (int) $record->komplain_count.' komplain')
                        ->badge()
                        ->color(fn (Delivery $record) => $record->komplain_count > 0 ? 'danger' : 'gray'),
                ]),
        ]);
    }

    // ===== Tabel =====

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('delivery_id', 'desc')
            ->columns([
                TextColumn::make('delivery_number')->label('No. Pengiriman')->searchable()->copyable(),

                TextColumn::make('driver.user.name')->label('Driver')->searchable(),

                TextColumn::make('jumlah_order')
                    ->label('Pesanan')
                    ->numeric()
                    ->sortable(query: fn (Builder $query, string $direction) => $query->orderBy('jumlah_order', $direction)),

                TextColumn::make('berat_gram')
                    ->label('Berat muatan')
                    ->formatStateUsing(fn ($state) => static::labelBerat($state))
                    ->sortable(query: fn (Builder $query, string $direction) => $query->orderBy('berat_gram', $direction)),

                TextColumn::make('started_at')->label('Mulai')->dateTime('d M Y H:i')->placeholder('-')->sortable(),

                TextColumn::make('berakhir')
                    ->label('Selesai / dibatalkan')
                    ->state(fn (Delivery $record) => ($record->completed_at ?? $record->cancelled_at)?->format('d M Y H:i') ?? '-'),

                TextColumn::make('durasi')
                    ->label('Durasi')
                    ->state(fn (Delivery $record) => static::labelDurasi($record)),

                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => DeliveryStatus::label($state))
                    ->color(fn (string $state) => DeliveryStatus::color($state)),

                TextColumn::make('rating_trip')
                    ->label('Rating')
                    ->state(fn (Delivery $record) => $record->jumlah_review > 0
                        ? '★ '.number_format((float) $record->rating_trip, 1)
                        : '-')
                    ->description(fn (Delivery $record) => $record->jumlah_review > 0 ? $record->jumlah_review.' review' : null)
                    ->sortable(query: fn (Builder $query, string $direction) => $query->orderBy('rating_trip', $direction)),

                TextColumn::make('komplain_count')
                    ->label('Komplain')
                    ->state(fn (Delivery $record) => (int) $record->komplain_count)
                    ->badge()
                    ->color(fn (int $state) => $state > 0 ? 'danger' : 'gray')
                    ->sortable(query: fn (Builder $query, string $direction) => $query->orderBy('komplain_count', $direction)),
            ])
            ->filters([
                SelectFilter::make('driver_profile_id')
                    ->label('Driver')
                    ->options(fn () => RiwayatSanksiResource::opsiDriver(true))
                    ->searchable(),

                SelectFilter::make('status')
                    ->label('Status')
                    ->options(DeliveryStatus::LABELS)
                    ->query(fn (Builder $query, array $data) => filled($data['value'] ?? null)
                        ? $query->withStatus($data['value'])
                        : $query),

                Filter::make('ada_komplain')
                    ->label('Ada komplain')
                    ->toggle()
                    ->query(fn (Builder $query) => $query->whereHas('deliveryOrders', fn (Builder $q) => $q
                        ->whereHas('violations', fn (Builder $v) => $v->where('source', 'customer')))),

                Filter::make('ada_review')
                    ->label('Ada review')
                    ->toggle()
                    ->query(fn (Builder $query) => $query->whereHas('deliveryOrders.review')),

                // Kosong berarti semua riwayat (beda dengan laporan yang default-nya bulan ini).
                Filter::make('periode')
                    ->label('Periode')
                    ->schema([
                        DatePicker::make('dari')->label('Dari tanggal'),
                        DatePicker::make('sampai')->label('Sampai tanggal'),
                    ])
                    ->columns(2)
                    ->columnSpan(2)
                    ->query(fn (Builder $query, array $data) => $query
                        ->when($data['dari'] ?? null, fn (Builder $q, $tanggal) => $q->whereDate('deliveries.created_at', '>=', $tanggal))
                        ->when($data['sampai'] ?? null, fn (Builder $q, $tanggal) => $q->whereDate('deliveries.created_at', '<=', $tanggal))),
            ], layout: FiltersLayout::AboveContentCollapsible)
            ->filtersFormColumns(4)
            ->recordActions([
                ViewAction::make()->label('Detail'),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRiwayatPengiriman::route('/'),
            'view' => ViewRiwayatPengiriman::route('/{record}'),
        ];
    }
}
