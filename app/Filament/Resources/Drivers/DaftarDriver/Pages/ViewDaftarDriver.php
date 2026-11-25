<?php

namespace App\Filament\Resources\Drivers\DaftarDriver\Pages;

use App\Filament\Resources\Drivers\DaftarDriver\DaftarDriverResource;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewDaftarDriver extends ViewRecord
{
    protected static string $resource = DaftarDriverResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
            ActionGroup::make(DaftarDriverResource::aksiSanksi())
                ->label('Sanksi')
                ->icon('heroicon-m-shield-exclamation')
                ->color('gray')
                ->button(),
        ];
    }
}