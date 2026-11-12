<?php

namespace App\Filament\Widgets;

use App\Models\Order;
use Filament\Widgets\ChartWidget;

class OrdersChart extends ChartWidget
{
    protected static ?int $sort = 2;

    protected int | string | array $columnSpan = 'full';

    public function getHeading(): ?string
    {
        return 'Pesanan 7 Hari Terakhir';
    }

    protected function getType(): string
    {
        return 'line';
    }

    protected function getData(): array
    {
        $days = collect(range(6, 0))->map(fn ($i) => now()->subDays($i)->startOfDay());

        $counts = Order::query()
            ->where('created_at', '>=', $days->first())
            ->where('status', '!=', 'cancelled')
            ->selectRaw('DATE(created_at) as tanggal, COUNT(*) as total')
            ->groupBy('tanggal')
            ->pluck('total', 'tanggal');

        return [
            'datasets' => [[
                'label' => 'Pesanan',
                'data' => $days->map(fn ($d) => (int) ($counts[$d->toDateString()] ?? 0))->all(),
            ]],
            'labels' => $days->map(fn ($d) => $d->format('d M'))->all(),
        ];
    }
}