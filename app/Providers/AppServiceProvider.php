<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Filament\Tables\Table;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
        Table::configureUsing(fn (Table $table) => $table
            ->striped()
            ->paginationPageOptions([10, 25, 50])
            ->defaultPaginationPageOption(10));
    }
}
