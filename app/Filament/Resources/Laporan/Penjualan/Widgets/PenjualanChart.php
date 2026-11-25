<?php

namespace App\Filament\Resources\Laporan\Penjualan\Widgets;

use App\Filament\Resources\Laporan\Penjualan\LaporanPenjualanResource;
use App\Filament\Resources\Laporan\Penjualan\Pages\ListLaporanPenjualan;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageTable;
use Illuminate\Support\Facades\DB;

class PenjualanChart extends ChartWidget
{
    use InteractsWithPageTable;

    protected int|string|array $columnSpan = 'full';

    protected function getTablePage(): string
    {
        return ListLaporanPenjualan::class;
    }

    public function getHeading(): ?string
    {
        return 'Penjualan per Hari';
    }

    protected function getType(): string
    {
        return 'line';
    }

    protected function getData(): array
    {
        $totals = $this->getPageTableQuery()
            ->reorder()
            ->whereIn('orders.status', LaporanPenjualanResource::STATUS_PENJUALAN)
            ->select(DB::raw('DATE(orders.created_at) as tanggal'), DB::raw('SUM(orders.grand_total) as total'))
            ->groupBy('tanggal')
            ->pluck('total', 'tanggal');

        $filter = $this->tableFilters['tanggal'] ?? [];
        $dari = $filter['dari'] ?? $totals->keys()->min();
        $sampai = $filter['sampai'] ?? $totals->keys()->max();

        $days = ($dari && $sampai)
            ? collect(CarbonPeriod::create(Carbon::parse($dari)->startOfDay(), Carbon::parse($sampai)->startOfDay()))
            : collect();

        return [
            'datasets' => [[
                'label' => 'Penjualan (Rp)',
                'data' => $days->map(fn ($d) => (float) ($totals[$d->toDateString()] ?? 0))->all(),
            ]],
            'labels' => $days->map(fn ($d) => $d->format('d M'))->all(),
        ];
    }
}
