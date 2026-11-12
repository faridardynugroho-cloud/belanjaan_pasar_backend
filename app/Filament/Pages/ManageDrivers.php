<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;

class ManageDrivers extends Page
{
    protected static string | \UnitEnum | null $navigationGroup = 'MANAJEMEN DATA';

    protected static ?int $navigationSort = 5;

    protected static ?string $navigationLabel = 'Driver';

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-user-circle';

    protected string $view = 'filament.pages.manage-drivers';

    public function getTitle(): string
    {
        return 'Driver';
    }
}
