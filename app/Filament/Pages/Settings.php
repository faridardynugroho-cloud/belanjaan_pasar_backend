<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;

class Settings extends Page
{
    protected static string | \UnitEnum | null $navigationGroup = 'LAINNYA';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Pengaturan';

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected string $view = 'filament.pages.settings';

    public function getTitle(): string
    {
        return 'Pengaturan';
    }
}
