<?php

namespace App\Filament\Resources\Drivers\DaftarDriver\Pages;

use App\Filament\Resources\Drivers\DaftarDriver\DaftarDriverResource;
use Filament\Resources\Pages\EditRecord;

class EditDaftarDriver extends EditRecord
{
    protected static string $resource = DaftarDriverResource::class;

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }
}
