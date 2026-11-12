<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Filament\Resources\Orders\OrderResource;
use App\Models\Order;
use App\Support\OrderStatus;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListOrders extends ListRecords
{
    protected static string $resource = OrderResource::class;

    public function getTabs(): array
    {
        $tabs = ['semua' => Tab::make('Semua')];

        foreach (OrderStatus::LABELS as $status => $label) {
            $tabs[$status] = Tab::make($label)
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', $status));
        }

        // Pesanan yang sudah dibayar = perlu ditindaklanjuti admin
        $tabs['paid']
            ->badge(Order::where('status', 'paid')->count())
            ->badgeColor('warning');

        return $tabs;
    }
}