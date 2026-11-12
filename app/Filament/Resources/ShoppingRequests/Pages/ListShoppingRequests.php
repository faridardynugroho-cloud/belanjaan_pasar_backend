<?php

namespace App\Filament\Resources\ShoppingRequests\Pages;

use App\Filament\Resources\ShoppingRequests\ShoppingRequestResource;
use App\Models\ShoppingRequest;
use App\Support\ShoppingRequestStatus;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListShoppingRequests extends ListRecords
{
    protected static string $resource = ShoppingRequestResource::class;

    public function getTabs(): array
    {
        $tabs = ['semua' => Tab::make('Semua')];

        foreach (ShoppingRequestStatus::LABELS as $status => $label) {
            $tabs[$status] = Tab::make($label)
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', $status));
        }

        $tabs['pending']
            ->badge(ShoppingRequest::where('status', 'pending')->count())
            ->badgeColor('warning');

        return $tabs;
    }
}