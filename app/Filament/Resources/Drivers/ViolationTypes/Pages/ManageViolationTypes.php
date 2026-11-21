<?php

namespace App\Filament\Resources\Drivers\ViolationTypes\Pages;

use App\Filament\Resources\ViolationTypes\ViolationTypeResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageViolationTypes extends ManageRecords
{
    protected static string $resource = ViolationTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Tambah Jenis Pelanggaran'),
        ];
    }
}