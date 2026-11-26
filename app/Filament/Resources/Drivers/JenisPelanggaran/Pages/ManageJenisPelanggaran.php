<?php

namespace App\Filament\Resources\Drivers\JenisPelanggaran\Pages;

use App\Filament\Resources\Drivers\JenisPelanggaran\JenisPelanggaranResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageJenisPelanggaran extends ManageRecords
{
    protected static string $resource = JenisPelanggaranResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Tambah Jenis Pelanggaran'),
        ];
    }
}
