<?php

namespace App\Filament\Resources\Review\Ngebelanjain;

use App\Filament\Resources\Review\Ngebelanjain\Pages\ListReviewNgebelanjain;
use App\Models\ShoppingRequestReview;
use App\Support\Concerns\TabelReview;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class ReviewNgebelanjainResource extends Resource
{
    use TabelReview;

    protected static ?string $model = ShoppingRequestReview::class;

    protected static ?string $slug = 'review/ngebelanjain';

    protected static string | UnitEnum | null $navigationGroup = 'Review';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Review Ngebelanjain';

    protected static ?string $modelLabel = 'Review Ngebelanjain';

    protected static ?string $pluralModelLabel = 'Review Ngebelanjain';

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
            ->modifyQueryUsing(fn(Builder $query) => $query->with(['user:user_id,name', 'order.shoppingRequest']))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('order.order_number')
                    ->label('Pesanan')
                    ->searchable()
                    ->copyable()
                    ->description(fn(ShoppingRequestReview $record) => $record->order?->shoppingRequest?->request_number
                        ? 'Permintaan ' . $record->order->shoppingRequest->request_number
                        : null),

                static::kolomRating(),
                ...static::kolomUmum(),
            ])
            ->filters(static::filterUmum(), layout: FiltersLayout::AboveContentCollapsible)
            ->filtersFormColumns(4)
            ->recordActions([
                static::aksiDetail(fn(ShoppingRequestReview $review) => [
                    'Pesanan' => $review->order?->order_number,
                    'Permintaan Ngebelanjain' => $review->order?->shoppingRequest?->request_number,
                ]),
                static::aksiModerasi(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListReviewNgebelanjain::route('/'),
        ];
    }
}