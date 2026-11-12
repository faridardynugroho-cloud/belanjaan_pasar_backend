<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Deliveries\DeliveryResource;
use App\Filament\Resources\Orders\OrderResource;
use App\Filament\Resources\ShoppingRequests\ShoppingRequestResource;
use App\Models\Delivery;
use App\Models\Order;
use App\Models\Payment;
use App\Models\ShoppingRequest;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatsOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $pesananHariIni = Order::whereDate('created_at', today())
            ->where('status', '!=', 'cancelled')
            ->count();

        $pendapatanHariIni = Payment::where('status', 'paid')
            ->whereDate('paid_at', today())
            ->sum('amount');

        $ngebelanjainBaru = ShoppingRequest::where('status', 'pending')->count();

        $pengirimanBerjalan = Delivery::where('status', 'on_the_way')->count();

        return [
            Stat::make('Pesanan Hari Ini', $pesananHariIni)
                ->description('Tidak termasuk yang dibatalkan')
                ->descriptionIcon('heroicon-m-shopping-bag')
                ->url(OrderResource::getUrl()),

            Stat::make('Pendapatan Hari Ini', 'Rp ' . number_format($pendapatanHariIni, 0, ',', '.'))
                ->description('Dari pembayaran berhasil')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('success'),

            Stat::make('Ngebelanjain Baru', $ngebelanjainBaru)
                ->description('Belum ditugaskan ke helper')
                ->descriptionIcon('heroicon-m-shopping-cart')
                ->color($ngebelanjainBaru > 0 ? 'warning' : 'gray')
                ->url(ShoppingRequestResource::getUrl()),

            Stat::make('Pengiriman Berjalan', $pengirimanBerjalan)
                ->description('Driver sedang di jalan')
                ->descriptionIcon('heroicon-m-truck')
                ->url(DeliveryResource::getUrl()),
        ];
    }
}