<?php

namespace App\Filament\Resources\Laporan\Driver;

use App\Filament\Resources\Laporan\Driver\Pages\ListLaporanDriver;
use App\Models\User;
use App\Support\Concerns\HasRentangTanggal;
use Carbon\Carbon;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use UnitEnum;

class LaporanDriverResource extends Resource
{
    use HasRentangTanggal;

    protected static ?string $model = User::class;

    protected static ?string $slug = 'laporan/driver';

    protected static string | UnitEnum | null $navigationGroup = 'Laporan';

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationLabel = 'Performa Driver';

    protected static ?string $modelLabel = 'Driver';

    protected static ?string $pluralModelLabel = 'Laporan Performa Driver';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('users.role', 'driver');
    }

    public static function judulLaporan(): string
    {
        return 'Laporan Performa Driver';
    }

    public static function tambahkanStatistik(Builder $query, Carbon $start, Carbon $end): Builder
    {
        $selesai = fn() => DB::table('deliveries')
            ->join('driver_profiles', 'driver_profiles.driver_profile_id', '=', 'deliveries.driver_profile_id')
            ->whereColumn('driver_profiles.user_id', 'users.user_id')
            ->whereNotNull('deliveries.completed_at')
            ->whereNull('deliveries.cancelled_at')
            ->whereBetween('deliveries.completed_at', [$start, $end]);

        return $query->addSelect([
            'pengiriman_selesai' => $selesai()->selectRaw('COUNT(*)'),
            'pesanan_dibawa' => DB::table('delivery_orders')
                ->join('deliveries', 'deliveries.delivery_id', '=', 'delivery_orders.delivery_id')
                ->join('driver_profiles', 'driver_profiles.driver_profile_id', '=', 'deliveries.driver_profile_id')
                ->whereColumn('driver_profiles.user_id', 'users.user_id')
                ->where('delivery_orders.status', 'completed')
                ->whereBetween('delivery_orders.completed_at', [$start, $end])
                ->selectRaw('COUNT(*)'),
            'rata_durasi_menit' => $selesai()
                ->selectRaw('AVG(TIMESTAMPDIFF(MINUTE, deliveries.started_at, deliveries.completed_at))'),
        ]);
    }

    public static function formatDurasi($menit): string
    {
        if ($menit === null) {
            return '-';
        }

        $menit = (int) round($menit);

        return $menit >= 60 ? intdiv($menit, 60) . ' j ' . ($menit % 60) . ' m' : $menit . ' m';
    }

    public static function exportKolom(): array
    {
        return [
            ['label' => 'Driver', 'lebar' => 28],
            ['label' => 'Pengiriman selesai', 'tipe' => 'angka', 'lebar' => 20],
            ['label' => 'Pesanan dibawa', 'tipe' => 'angka', 'lebar' => 18],
            ['label' => 'Rata-rata durasi (menit)', 'tipe' => 'angka', 'lebar' => 24],
        ];
    }

    public static function exportBaris(User $driver): array
    {
        return [
            $driver->name,
            $driver->pengiriman_selesai,
            $driver->pesanan_dibawa,
            $driver->rata_durasi_menit !== null ? round($driver->rata_durasi_menit) : null,
        ];
    }

    public static function exportRingkasan(Collection $records): array
    {
        return [
            ['label' => 'Jumlah driver', 'nilai' => $records->count(), 'tipe' => 'angka'],
            ['label' => 'Total pengiriman selesai', 'nilai' => (int) $records->sum('pengiriman_selesai'), 'tipe' => 'angka'],
            ['label' => 'Total pesanan dibawa', 'nilai' => (int) $records->sum('pesanan_dibawa'), 'tipe' => 'angka'],
        ];
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(function (Builder $query, $livewire) {
                [$start, $end] = static::rentangTanggal($livewire->tableFilters['tanggal'] ?? []);

                return static::tambahkanStatistik($query, $start, $end);
            })
            ->defaultSort(fn(Builder $query) => $query->orderByDesc('pengiriman_selesai'))
            ->columns([
                TextColumn::make('name')->label('Driver')->searchable(),
                TextColumn::make('pengiriman_selesai')
                    ->label('Pengiriman selesai')
                    ->numeric()
                    ->sortable(query: fn(Builder $query, string $direction) => $query->orderBy('pengiriman_selesai', $direction)),
                TextColumn::make('pesanan_dibawa')
                    ->label('Pesanan dibawa')
                    ->numeric()
                    ->sortable(query: fn(Builder $query, string $direction) => $query->orderBy('pesanan_dibawa', $direction)),
                TextColumn::make('rata_durasi_menit')
                    ->label('Rata-rata durasi')
                    ->sortable(query: fn(Builder $query, string $direction) => $query->orderBy('rata_durasi_menit', $direction))
                    ->formatStateUsing(fn($state) => static::formatDurasi($state)),
            ])
            // Hanya menyimpan rentang tanggal; perhitungannya ada di modifyQueryUsing di atas
            ->filters([
                static::filterTanggal(),
            ], layout: FiltersLayout::AboveContent)
            ->filtersFormColumns(2)
            ->deferFilters(false)
            ->paginated(false);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListLaporanDriver::route('/'),
        ];
    }
}
