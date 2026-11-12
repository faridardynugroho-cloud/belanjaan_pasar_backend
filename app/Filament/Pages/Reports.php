<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;

class Reports extends Page
{
    protected static string | \UnitEnum | null $navigationGroup = 'LAINNYA';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Laporan';

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-chart-bar';

    protected string $view = 'filament.pages.reports';

    public function getTitle(): string
    {
        return 'Laporan';
    }
}
