<?php

namespace App\Filament\Resources\Laporan\Ngebelanjain\Pages;

use App\Filament\Resources\Laporan\Ngebelanjain\LaporanNgebelanjainResource;
use App\Support\Concerns\HasLaporanDownload;
use Filament\Resources\Pages\ListRecords;

class ListLaporanNgebelanjain extends ListRecords
{
    use HasLaporanDownload;

    protected static string $resource = LaporanNgebelanjainResource::class;
}