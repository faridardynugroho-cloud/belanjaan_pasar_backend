<?php

namespace App\Filament\Resources\Deliveries\Pages;

use App\Filament\Resources\Deliveries\DeliveryResource;
use App\Support\DeliveryStatus;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListDeliveries extends ListRecords
{
    protected static string $resource = DeliveryResource::class;

    public function getTabs(): array
    {
        $tabs = ['semua' => Tab::make('Semua')];

        foreach (DeliveryStatus::LABELS as $status => $label) {
            $tabs[$status] = Tab::make($label)
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', $status));
        }

        return $tabs;
    }
}
