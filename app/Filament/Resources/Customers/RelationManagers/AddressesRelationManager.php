<?php

namespace App\Filament\Resources\Customers\RelationManagers;

use App\Models\Address;
use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class AddressesRelationManager extends RelationManager
{
    protected static string $relationship = 'addresses';

    protected static ?string $title = 'Alamat';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('label')
            ->paginated(false)
            ->columns([
                TextColumn::make('label')->label('Label')->badge(),
                IconColumn::make('is_default')->label('Utama')->boolean(),
                TextColumn::make('recipient_name')->label('Penerima'),
                TextColumn::make('phone')->label('Telepon'),
                TextColumn::make('full_address')->label('Alamat')->limit(80)->wrap(),
            ])
            ->recordActions([
                Action::make('lokasi')
                    ->label('Maps')
                    ->icon('heroicon-o-map-pin')
                    ->color('gray')
                    ->url(fn (Address $record) => "https://www.google.com/maps?q={$record->latitude},{$record->longitude}")
                    ->openUrlInNewTab(),
            ]);
    }
}