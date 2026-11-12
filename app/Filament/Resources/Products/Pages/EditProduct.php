<?php

namespace App\Filament\Resources\Products\Pages;

use App\Filament\Resources\Products\ProductResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Enums\Width;

class EditProduct extends EditRecord
{
    protected static string $resource = ProductResource::class;

    public function getMaxContentWidth(): Width | string | null
    {
        return Width::ThreeExtraLarge;
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->modalDescription('Produk akan hilang dari semua market dan dari keranjang customer. Riwayat pesanan tetap aman.'),
        ];
    }
}