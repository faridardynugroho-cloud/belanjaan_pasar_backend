<?php

namespace App\Filament\Resources\Laporan\Penjualan\Widgets;

use App\Filament\Resources\Laporan\Penjualan\LaporanPenjualanResource;
use App\Filament\Resources\Laporan\Penjualan\Pages\ListLaporanPenjualan;
use Filament\Widgets\Concerns\InteractsWithPageTable;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class PenjualanStats extends StatsOverviewWidget
{
    use InteractsWithPageTable;

    protected function getTablePage(): string
    {
        return ListLaporanPenjualan::class;
    }

    protected function getStats(): array
    {
        $semuaStatus = fn () => $this->getPageTableQuery()->reorder();
        $penjualan = fn () => $semuaStatus()->whereIn('orders.status', LaporanPenjualanResource::STATUS_PENJUALAN);

        $total = (float) $penjualan()->sum('orders.grand_total');
        $jumlah = $penjualan()->count();
        $rataRata = $jumlah > 0 ? $total / $jumlah : 0;

        $semua = $semuaStatus()->count();
        $batal = $semuaStatus()->where('orders.status', 'cancelled')->count();
        $persenBatal = $semua > 0 ? round($batal / $semua * 100) : 0;

        $rupiah = fn ($v) => 'Rp '.number_format((float) $v, 0, ',', '.');

        return [
            Stat::make('Total Penjualan', $rupiah($total))
                ->description('Pesanan yang sudah dibayar')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('success'),

            Stat::make('Jumlah Pesanan', $jumlah)
                ->description('Tidak termasuk menunggu bayar dan dibatalkan')
                ->descriptionIcon('heroicon-m-shopping-bag'),

            Stat::make('Rata-rata per Pesanan', $rupiah($rataRata))
                ->descriptionIcon('heroicon-m-calculator'),

            Stat::make('Pesanan Dibatalkan', $batal)
                ->description("{$persenBatal}% dari semua pesanan")
                ->descriptionIcon('heroicon-m-x-circle')
                ->color($batal > 0 ? 'warning' : 'gray'),
        ];
    }
}
