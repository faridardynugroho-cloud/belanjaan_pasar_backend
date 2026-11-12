<?php

namespace App\Filament\Resources\Payments\Pages;

use App\Filament\Resources\Payments\PaymentResource;
use App\Models\Payment;
use App\Support\PaymentStatus;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListPayments extends ListRecords
{
    protected static string $resource = PaymentResource::class;

    public function getTabs(): array
    {
        $tabs = ['semua' => Tab::make('Semua')];

        foreach (PaymentStatus::LABELS as $status => $label) {
            $tabs[$status] = Tab::make($label)
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', $status));
        }

        $tabs['pending']
            ->badge(Payment::where('status', 'pending')->count())
            ->badgeColor('warning');

        return $tabs;
    }
}