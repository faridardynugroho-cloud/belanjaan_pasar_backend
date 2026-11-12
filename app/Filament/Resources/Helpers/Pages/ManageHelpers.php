<?php

namespace App\Filament\Resources\Helpers\Pages;

use App\Filament\Resources\Helpers\HelperResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageHelpers extends ManageRecords
{
    protected static string $resource = HelperResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Tambah Helper'),
        ];
    }
}
