<?php

namespace App\Filament\Resources\Laporan\Penjualan\Widgets;

use App\Filament\Resources\Laporan\Penjualan\LaporanPenjualanResource;
use App\Filament\Resources\Laporan\Penjualan\Pages\ListLaporanPenjualan;
use App\Models\Market;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageTable;
use Illuminate\Support\Facades\DB;

class PenjualanPerMarket extends ChartWidget
{
    use InteractsWithPageTable;

    protected int | string | array $columnSpan = 1;

    protected function getTablePage(): string
    {
        return ListLaporanPenjualan::class;
    }

    public function getHeading(): ?string
    {
        return 'Penjualan per Market';
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        $totals = $this->getPageTableQuery()
            ->reorder()
            ->whereIn('orders.status', LaporanPenjualanResource::STATUS_PENJUALAN)
            ->select('orders.market_id', DB::raw('SUM(orders.grand_total) as total'))
            ->groupBy('orders.market_id')
            ->pluck('total', 'market_id');

        $nama = Market::whereIn('market_id', $totals->keys())->pluck('name', 'market_id');

        return [
            'datasets' => [[
                'label' => 'Penjualan (Rp)',
                'data' => $totals->values()->map(fn ($v) => (float) $v)->all(),
            ]],
            'labels' => $totals->keys()->map(fn ($id) => $nama[$id] ?? "Market #{$id}")->all(),
        ];
    }
}