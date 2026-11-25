<?php

namespace App\Filament\Resources\Drivers\RiwayatPengiriman\Pages;

use App\Filament\Resources\Drivers\RiwayatPengiriman\RiwayatPengirimanResource;
use App\Filament\Resources\Drivers\RiwayatPengiriman\Widgets\PerformaDriverTable;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\Livewire;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Support\Components\Attributes\Unexposed;

class ListRiwayatPengiriman extends ListRecords
{
    protected static string $resource = RiwayatPengirimanResource::class;

    public function getTitle(): string
    {
        return 'Riwayat Pengiriman & Performa';
    }

    #[Unexposed]
    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Tabs::make()
                ->persistTabInQueryString()
                ->tabs([
                    Tab::make('Riwayat Pengiriman')
                        ->icon('heroicon-o-truck')
                        ->schema([
                            EmbeddedTable::make(),
                        ]),

                    Tab::make('Performa Driver')
                        ->icon('heroicon-o-chart-bar')
                        ->schema([
                            Livewire::make(PerformaDriverTable::class),
                        ]),
                ]),
        ]);
    }
}
