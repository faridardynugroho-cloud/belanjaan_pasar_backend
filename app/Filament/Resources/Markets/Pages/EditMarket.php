<?php

namespace App\Filament\Resources\Markets\Pages;

use App\Filament\Resources\Markets\MarketResource;
use App\Models\Market;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditMarket extends EditRecord
{
    protected static string $resource = MarketResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->disabled(fn (Market $record) => MarketResource::isUsed($record))
                ->tooltip(fn (Market $record) => MarketResource::isUsed($record)
                    ? 'Market sudah dipakai di keranjang/pesanan. Nonaktifkan saja.'
                    : null),
        ];
    }
}
