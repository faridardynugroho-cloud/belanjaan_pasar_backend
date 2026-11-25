<?php

namespace App\Filament\Resources\Review\Produk;

use App\Filament\Resources\Review\Produk\Pages\ListReviewProduk;
use App\Models\ProductReview;
use App\Support\Concerns\TabelReview;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class ReviewProdukResource extends Resource
{
    use TabelReview;

    protected static ?string $model = ProductReview::class;

    protected static ?string $slug = 'review/produk';

    protected static string | UnitEnum | null $navigationGroup = 'Review';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Review Produk';

    protected static ?string $modelLabel = 'Review Produk';

    protected static ?string $pluralModelLabel = 'Review Produk';

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
            ->modifyQueryUsing(fn(Builder $query) => $query->with(['user:user_id,name', 'product:product_id,name', 'orderItem.order:order_id,order_number']))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('product.name')
                    ->label('Produk')
                    ->searchable()
                    ->description(fn(ProductReview $record) => $record->orderItem?->order?->order_number
                        ? 'Pesanan ' . $record->orderItem->order->order_number
                        : null),

                static::kolomRating(),
                ...static::kolomUmum(),
            ])
            ->filters([
                SelectFilter::make('product_id')
                    ->label('Produk')
                    ->relationship('product', 'name')
                    ->searchable()
                    ->preload(),

                ...static::filterUmum(),
            ], layout: FiltersLayout::AboveContentCollapsible)
            ->filtersFormColumns(4)
            ->recordActions([
                static::aksiDetail(fn(ProductReview $review) => [
                    'Produk' => $review->product?->name,
                    'Pesanan' => $review->orderItem?->order?->order_number,
                ]),
                static::aksiModerasi(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListReviewProduk::route('/'),
        ];
    }
}