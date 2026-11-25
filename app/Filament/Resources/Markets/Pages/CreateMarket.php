<?php

namespace App\Filament\Resources\Markets\Pages;

use App\Filament\Resources\Markets\MarketResource;
use Filament\Resources\Pages\CreateRecord;

class CreateMarket extends CreateRecord
{
    protected static string $resource = MarketResource::class;

    protected function getRedirectUrl(): string
    {
        return MarketResource::getUrl('edit', ['record' => $this->getRecord()]);
    }
}
