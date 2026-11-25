<?php

namespace App\Filament\Resources\Laporan\Penjualan\Widgets;

use App\Filament\Resources\Laporan\Penjualan\LaporanPenjualanResource;
use App\Filament\Resources\Laporan\Penjualan\Pages\ListLaporanPenjualan;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageTable;
use Illuminate\Support\Facades\DB;

class PenjualanSumber extends ChartWidget
{
    use InteractsWithPageTable;

    protected int|string|array $columnSpan = 1;

    protected function getTablePage(): string
    {
        return ListLaporanPenjualan::class;
    }

    public function getHeading(): ?string
    {
        return 'Marketplace vs Ngebelanjain';
    }

    protected function getType(): string
    {
        return 'doughnut';
    }

    protected function getData(): array
    {
        $rows = $this->getPageTableQuery()
            ->reorder()
            ->whereIn('orders.status', LaporanPenjualanResource::STATUS_PENJUALAN)
            ->select(
                DB::raw("CASE WHEN orders.shopping_request_id IS NULL THEN 'marketplace' ELSE 'ngebelanjain' END as jenis"),
                DB::raw('SUM(orders.grand_total) as total')
            )
            ->groupBy('jenis')
            ->pluck('total', 'jenis');

        return [
            'datasets' => [[
                'label' => 'Penjualan (Rp)',
                'data' => [
                    (float) ($rows['marketplace'] ?? 0),
                    (float) ($rows['ngebelanjain'] ?? 0),
                ],
                'backgroundColor' => ['#f59e0b', '#3b82f6'],
            ]],
            'labels' => ['Marketplace', 'Ngebelanjain'],
        ];
    }
}
