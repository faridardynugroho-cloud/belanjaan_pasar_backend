<?php

namespace App\Filament\Resources\Drivers\RiwayatSanksi\Widgets;

use App\Models\DriverSanction;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class RiwayatSanksiStats extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        $suspendBulanIni = DriverSanction::whereIn('type', [DriverSanction::TYPE_TEMPORARY, DriverSanction::TYPE_PERMANENT])
            ->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()])
            ->count();

        $driverDisuspend = DriverSanction::inEffect()
            ->where('type', '!=', DriverSanction::TYPE_TERMINATION)
            ->distinct()
            ->count('driver_profile_id');

        $terjadwal = DriverSanction::waiting()->count();

        $akanBerakhir = DriverSanction::inEffect()
            ->where('type', DriverSanction::TYPE_TEMPORARY)
            ->whereBetween('expires_at', [now(), now()->addDay()])
            ->count();

        $putusMitra = DriverSanction::inEffect()
            ->where('type', DriverSanction::TYPE_TERMINATION)
            ->count();

        return [
            Stat::make('Suspend Bulan Ini', $suspendBulanIni)
                ->description('Sementara dan permanen')
                ->descriptionIcon('heroicon-m-no-symbol'),

            Stat::make('Driver Sedang Disuspend', $driverDisuspend)
                ->description($terjadwal . ' sanksi terjadwal')
                ->color($driverDisuspend > 0 ? 'warning' : 'gray'),

            Stat::make('Akan Berakhir', $akanBerakhir)
                ->description('Dalam 24 jam ke depan')
                ->descriptionIcon('heroicon-m-clock'),

            Stat::make('Putus Mitra', $putusMitra)
                ->description('Total driver diputus mitra')
                ->color($putusMitra > 0 ? 'danger' : 'gray'),
        ];
    }
}