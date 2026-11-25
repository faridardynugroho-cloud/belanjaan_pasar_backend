<?php

namespace App\Filament\Resources\Promo\DiskonProduk\Pages;

use App\Filament\Resources\Promo\DiskonProduk\DiskonProdukResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageDiskonProduk extends ManageRecords
{
    protected static string $resource = DiskonProdukResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Tambah Diskon Produk'),
        ];
    }
}