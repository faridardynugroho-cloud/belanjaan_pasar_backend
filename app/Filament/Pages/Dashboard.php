<?php

namespace App\Filament\Pages;

class Dashboard extends \Filament\Pages\Dashboard
{
    protected static string | \UnitEnum | null $navigationGroup = 'MENU UTAMA';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Dashboard';

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-home';
}