<?php

namespace App\Filament\Resources\Review\Driver;

use App\Filament\Resources\Drivers\RiwayatSanksi\RiwayatSanksiResource;
use App\Filament\Resources\Review\Driver\Pages\ListReviewDriver;
use App\Models\DriverReview;
use App\Support\Concerns\TabelReview;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class ReviewDriverResource extends Resource
{
    use TabelReview;

    protected static ?string $model = DriverReview::class;

    protected static ?string $slug = 'review/driver';

    protected static string|UnitEnum|null $navigationGroup = 'Review';

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationLabel = 'Review Driver';

    protected static ?string $modelLabel = 'Review Driver';

    protected static ?string $pluralModelLabel = 'Review Driver';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with([
                'user:user_id,name',
                'driverProfile.user:user_id,name',
                'deliveryOrder.order:order_id,order_number',
                'deliveryOrder.delivery:delivery_id,delivery_number',
            ]))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('driverProfile.user.name')
                    ->label('Driver')
                    ->searchable()
                    ->description(fn (DriverReview $record) => $record->deliveryOrder?->delivery?->delivery_number
                        ? 'Pengiriman '.$record->deliveryOrder->delivery->delivery_number
                        : null),

                static::kolomRating(),
                ...static::kolomUmum(),
            ])
            ->filters([
                SelectFilter::make('driver_profile_id')
                    ->label('Driver')
                    ->options(fn () => RiwayatSanksiResource::opsiDriver(true))
                    ->searchable(),

                ...static::filterUmum(),
            ], layout: FiltersLayout::AboveContentCollapsible)
            ->filtersFormColumns(4)
            ->recordActions([
                static::aksiDetail(fn (DriverReview $review) => [
                    'Driver' => $review->driverProfile?->user?->name,
                    'Pengiriman' => $review->deliveryOrder?->delivery?->delivery_number,
                    'Pesanan' => $review->deliveryOrder?->order?->order_number,
                ]),
                static::aksiModerasi(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListReviewDriver::route('/'),
        ];
    }
}
