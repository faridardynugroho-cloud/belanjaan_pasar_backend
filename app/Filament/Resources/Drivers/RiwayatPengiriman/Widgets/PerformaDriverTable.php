<?php

namespace App\Filament\Resources\Drivers\RiwayatPengiriman\Widgets;

use App\Filament\Resources\Drivers\RiwayatPengiriman\RiwayatPengirimanResource;
use App\Models\DriverProfile;
use App\Support\Concerns\HasRentangTanggal;
use App\Support\LaporanExporter;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** Statistik performa per driver untuk satu periode (default bulan ini). */
class PerformaDriverTable extends TableWidget
{
    use HasRentangTanggal;

    protected static ?string $heading = 'Performa Driver';

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => DriverProfile::query()
                ->where('approval_status', DriverProfile::APPROVAL_APPROVED)
                ->with('user'))
            ->modifyQueryUsing(function (Builder $query) {
                [$start, $end] = static::rentangTanggal($this->tableFilters['tanggal'] ?? []);

                return static::tambahkanStatistik($query, $start, $end);
            })
            ->description(function () {
                [$start, $end] = static::rentangTanggal($this->tableFilters['tanggal'] ?? []);

                return 'Periode '.$start->format('d M Y').' sampai '.$end->format('d M Y');
            })
            ->headerActions([
                Action::make('excel')
                    ->label('Download Excel')
                    ->icon('heroicon-o-table-cells')
                    ->color('success')
                    ->action(fn () => $this->unduhLaporan('xlsx')),

                Action::make('pdf')
                    ->label('Download PDF')
                    ->icon('heroicon-o-document-arrow-down')
                    ->color('danger')
                    ->action(fn () => $this->unduhLaporan('pdf')),
            ])
            ->defaultSort(fn (Builder $query) => $query->orderByDesc('pengiriman_selesai'))
            ->columns([
                TextColumn::make('user.name')->label('Driver')->searchable(),

                TextColumn::make('pengiriman_selesai')
                    ->label('Selesai')
                    ->numeric()
                    ->sortable(query: fn (Builder $query, string $direction) => $query->orderBy('pengiriman_selesai', $direction)),

                TextColumn::make('pengiriman_batal')
                    ->label('Dibatalkan')
                    ->numeric()
                    ->color(fn ($state) => $state > 0 ? 'danger' : null)
                    ->sortable(query: fn (Builder $query, string $direction) => $query->orderBy('pengiriman_batal', $direction)),

                TextColumn::make('persen_selesai')
                    ->label('% Selesai')
                    ->state(fn (DriverProfile $record) => static::persenSelesai($record)),

                TextColumn::make('pesanan_selesai')
                    ->label('Pesanan diantar')
                    ->numeric()
                    ->sortable(query: fn (Builder $query, string $direction) => $query->orderBy('pesanan_selesai', $direction)),

                TextColumn::make('pesanan_ditolak')
                    ->label('Pesanan ditolak')
                    ->numeric()
                    ->sortable(query: fn (Builder $query, string $direction) => $query->orderBy('pesanan_ditolak', $direction)),

                TextColumn::make('rata_durasi_menit')
                    ->label('Rata-rata durasi')
                    ->formatStateUsing(fn ($state) => RiwayatPengirimanResource::formatDurasi($state))
                    ->sortable(query: fn (Builder $query, string $direction) => $query->orderBy('rata_durasi_menit', $direction)),

                TextColumn::make('rating_periode')
                    ->label('Rating')
                    ->state(fn (DriverProfile $record) => $record->jumlah_review > 0
                        ? '★ '.number_format((float) $record->rating_periode, 1)
                        : '-')
                    ->description(fn (DriverProfile $record) => $record->jumlah_review > 0 ? $record->jumlah_review.' review' : null)
                    ->sortable(query: fn (Builder $query, string $direction) => $query->orderBy('rating_periode', $direction)),

                TextColumn::make('komplain')
                    ->label('Komplain')
                    ->numeric()
                    ->badge()
                    ->color(fn ($state) => $state > 0 ? 'danger' : 'gray')
                    ->sortable(query: fn (Builder $query, string $direction) => $query->orderBy('komplain', $direction)),
            ])
            // Filter hanya menyimpan rentang tanggal; perhitungannya ada di modifyQueryUsing di atas.
            ->filters([
                static::filterTanggal(),
            ], layout: FiltersLayout::AboveContent)
            ->filtersFormColumns(2)
            ->deferFilters(false)
            ->paginated(false);
    }

    protected static function persenSelesai(DriverProfile $record): string
    {
        $total = (int) $record->pengiriman_selesai + (int) $record->pengiriman_batal;

        return $total > 0 ? round($record->pengiriman_selesai / $total * 100).'%' : '-';
    }

    protected function unduhLaporan(string $format)
    {
        [$start, $end] = static::rentangTanggal($this->tableFilters['tanggal'] ?? []);

        // Semua baris sesuai pencarian/urutan aktif, bukan cuma yang tampil di layar
        $records = $this->getFilteredSortedTableQuery()->get();

        $judul = 'Laporan Performa Driver';
        $periode = 'Periode: '.$start->format('d/m/Y').' - '.$end->format('d/m/Y');
        $kolom = [
            ['label' => 'Driver', 'lebar' => 28],
            ['label' => 'Selesai', 'tipe' => 'angka', 'lebar' => 12],
            ['label' => 'Dibatalkan', 'tipe' => 'angka', 'lebar' => 14],
            ['label' => '% Selesai', 'lebar' => 12],
            ['label' => 'Pesanan diantar', 'tipe' => 'angka', 'lebar' => 18],
            ['label' => 'Pesanan ditolak', 'tipe' => 'angka', 'lebar' => 18],
            ['label' => 'Rata-rata durasi (menit)', 'tipe' => 'angka', 'lebar' => 24],
            ['label' => 'Rating', 'tipe' => 'desimal', 'lebar' => 10],
            ['label' => 'Jumlah review', 'tipe' => 'angka', 'lebar' => 16],
            ['label' => 'Komplain', 'tipe' => 'angka', 'lebar' => 12],
        ];
        $rows = $records->map(fn (DriverProfile $d) => [
            $d->user?->name,
            $d->pengiriman_selesai,
            $d->pengiriman_batal,
            static::persenSelesai($d),
            $d->pesanan_selesai,
            $d->pesanan_ditolak,
            $d->rata_durasi_menit !== null ? round($d->rata_durasi_menit) : null,
            $d->jumlah_review > 0 ? $d->rating_periode : null,
            $d->jumlah_review,
            $d->komplain,
        ])->all();
        $ringkasan = [
            ['label' => 'Jumlah driver', 'nilai' => $records->count(), 'tipe' => 'angka'],
            ['label' => 'Total pengiriman selesai', 'nilai' => (int) $records->sum('pengiriman_selesai'), 'tipe' => 'angka'],
            ['label' => 'Total pesanan diantar', 'nilai' => (int) $records->sum('pesanan_selesai'), 'tipe' => 'angka'],
            ['label' => 'Total komplain', 'nilai' => (int) $records->sum('komplain'), 'tipe' => 'angka'],
        ];
        $nama = Str::slug($judul).'-'.$start->format('Ymd').'-'.$end->format('Ymd');

        return $format === 'xlsx'
            ? LaporanExporter::xlsx("{$nama}.xlsx", $judul, $periode, $kolom, $rows, $ringkasan)
            : LaporanExporter::pdf("{$nama}.pdf", $judul, $periode, $kolom, $rows, $ringkasan);
    }

    protected static function tambahkanStatistik(Builder $query, $start, $end): Builder
    {
        $trip = fn () => DB::table('deliveries')
            ->whereColumn('deliveries.driver_profile_id', 'driver_profiles.driver_profile_id');

        $selesai = fn () => $trip()
            ->whereNotNull('deliveries.completed_at')
            ->whereNull('deliveries.cancelled_at')
            ->whereBetween('deliveries.completed_at', [$start, $end]);

        $pesanan = fn () => DB::table('delivery_orders')
            ->join('deliveries', 'deliveries.delivery_id', '=', 'delivery_orders.delivery_id')
            ->whereColumn('deliveries.driver_profile_id', 'driver_profiles.driver_profile_id');

        $review = fn () => DB::table('driver_reviews')
            ->whereColumn('driver_reviews.driver_profile_id', 'driver_profiles.driver_profile_id')
            ->where('driver_reviews.is_visible', true)
            ->whereBetween('driver_reviews.created_at', [$start, $end]);

        return $query->addSelect([
            'pengiriman_selesai' => $selesai()->selectRaw('COUNT(*)'),
            'pengiriman_batal' => $trip()
                ->whereNotNull('deliveries.cancelled_at')
                ->whereBetween('deliveries.cancelled_at', [$start, $end])
                ->selectRaw('COUNT(*)'),
            'pesanan_selesai' => $pesanan()
                ->where('delivery_orders.status', 'completed')
                ->whereBetween('delivery_orders.completed_at', [$start, $end])
                ->selectRaw('COUNT(*)'),
            'pesanan_ditolak' => $pesanan()
                ->where('delivery_orders.status', 'rejected')
                ->whereBetween('delivery_orders.rejected_at', [$start, $end])
                ->selectRaw('COUNT(*)'),
            'rata_durasi_menit' => $selesai()
                ->selectRaw('AVG(TIMESTAMPDIFF(MINUTE, deliveries.started_at, deliveries.completed_at))'),
            'rating_periode' => $review()->selectRaw('AVG(driver_reviews.rating)'),
            'jumlah_review' => $review()->selectRaw('COUNT(*)'),
            'komplain' => DB::table('driver_violations')
                ->whereColumn('driver_violations.driver_profile_id', 'driver_profiles.driver_profile_id')
                ->where('driver_violations.source', 'customer')
                ->whereBetween('driver_violations.created_at', [$start, $end])
                ->selectRaw('COUNT(*)'),
        ]);
    }
}
