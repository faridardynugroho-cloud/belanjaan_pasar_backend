<?php

namespace App\Filament\Resources\Drivers\VerifikasiPendaftaran\Pages;

use App\Filament\Resources\Drivers\VerifikasiPendaftaran\VerifikasiPendaftaranResource;
use App\Models\DriverProfile;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListVerifikasiPendaftaran extends ListRecords
{
    protected static string $resource = VerifikasiPendaftaranResource::class;

    public function getDefaultActiveTab(): string | int | null
    {
        return 'menunggu';
    }

    public function getTabs(): array
    {
        $hitung = fn(string $status) => DriverProfile::where('approval_status', $status)->count();

        return [
            'menunggu' => Tab::make('Menunggu')
                ->badge($hitung(DriverProfile::APPROVAL_PENDING))
                ->badgeColor('warning')
                ->modifyQueryUsing(fn(Builder $query) => $query->where('approval_status', DriverProfile::APPROVAL_PENDING)),

            'perbaikan' => Tab::make('Perlu perbaikan')
                ->badge($hitung(DriverProfile::APPROVAL_REVISION))
                ->badgeColor('info')
                ->modifyQueryUsing(fn(Builder $query) => $query->where('approval_status', DriverProfile::APPROVAL_REVISION)),

            'ditolak' => Tab::make('Ditolak')
                ->badge($hitung(DriverProfile::APPROVAL_REJECTED))
                ->badgeColor('danger')
                ->modifyQueryUsing(fn(Builder $query) => $query->where('approval_status', DriverProfile::APPROVAL_REJECTED)),

            'semua' => Tab::make('Semua'),
        ];
    }
}