<?php

namespace App\Filament\Resources\Laporan\Driver\Pages;

use App\Filament\Resources\Laporan\Driver\LaporanDriverResource;
use App\Support\Concerns\HasLaporanDownload;
use Filament\Resources\Pages\ListRecords;

class ListLaporanDriver extends ListRecords
{
    use HasLaporanDownload;

    protected static string $resource = LaporanDriverResource::class;
}