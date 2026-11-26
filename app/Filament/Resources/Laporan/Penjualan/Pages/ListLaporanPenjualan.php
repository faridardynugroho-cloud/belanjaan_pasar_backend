<?php

namespace App\Filament\Resources\Laporan\Penjualan\Pages;

use App\Filament\Resources\Laporan\Penjualan\LaporanPenjualanResource;
use App\Support\Concerns\HasLaporanDownload;
use Filament\Resources\Pages\ListRecords;

class ListLaporanPenjualan extends ListRecords
{
    use HasLaporanDownload;

    protected static string $resource = LaporanPenjualanResource::class;
}
