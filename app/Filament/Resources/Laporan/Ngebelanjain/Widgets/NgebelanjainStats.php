<?php

namespace App\Filament\Resources\Laporan\Ngebelanjain\Widgets;

use App\Filament\Resources\Laporan\Ngebelanjain\Pages\ListLaporanNgebelanjain;
use Filament\Widgets\Concerns\InteractsWithPageTable;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class NgebelanjainStats extends StatsOverviewWidget
{
    use InteractsWithPageTable;

    protected function getTablePage(): string
    {
        return ListLaporanNgebelanjain::class;
    }

    protected function getStats(): array
    {
        $dasar = fn () => $this->getPageTableQuery()->reorder();

        $total = $dasar()->count();
        $selesai = $dasar()->where('shopping_requests.status', 'completed')->count();
        $batal = $dasar()->where('shopping_requests.status', 'cancelled')->count();
        $biayaLayanan = (float) $dasar()->where('shopping_requests.status', 'completed')->sum('shopping_requests.service_fee');

        $persenSelesai = $total > 0 ? round($selesai / $total * 100) : 0;
        $persenBatal = $total > 0 ? round($batal / $total * 100) : 0;

        return [
            Stat::make('Jumlah Permintaan', $total)
                ->descriptionIcon('heroicon-m-shopping-cart'),

            Stat::make('Selesai', $selesai)
                ->description("{$persenSelesai}% dari permintaan")
                ->color('success'),

            Stat::make('Dibatalkan', $batal)
                ->description("{$persenBatal}% dari permintaan")
                ->color($batal > 0 ? 'warning' : 'gray'),

            Stat::make('Biaya Layanan Terkumpul', 'Rp ' . number_format($biayaLayanan, 0, ',', '.'))
                ->description('Dari permintaan yang selesai')
                ->descriptionIcon('heroicon-m-banknotes'),
        ];
    }
}