<?php

namespace App\Filament\Resources\Markets;

use App\Filament\Resources\Markets\Pages\CreateMarket;
use App\Filament\Resources\Markets\Pages\EditMarket;
use App\Filament\Resources\Markets\Pages\ListMarkets;
use App\Filament\Resources\Markets\RelationManagers\MarketProductsRelationManager;
use App\Models\Cart;
use App\Models\Market;
use App\Models\Order;
use App\Models\ShoppingRequest;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use UnitEnum;

class MarketResource extends Resource
{
    protected static ?string $model = Market::class;

    protected static string|UnitEnum|null $navigationGroup = 'MANAJEMEN DATA';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Market';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-building-storefront';

    protected static ?string $modelLabel = 'Market';

    protected static ?string $pluralModelLabel = 'Market';

    protected static ?string $recordTitleAttribute = 'name';

    // FK carts/orders/shopping_requests ke market itu restrict, jadi market yang sudah dipakai tidak boleh dihapus
    public static function isUsed(Market $market): bool
    {
        $id = $market->getKey();

        return Cart::where('market_id', $id)->exists()
            || Order::where('market_id', $id)->exists()
            || ShoppingRequest::where('market_id', $id)->exists();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Info Market')
                ->columns(2)
                ->schema([
                    TextInput::make('name')
                        ->label('Nama market')
                        ->required()
                        ->maxLength(255)
                        ->columnSpanFull(),

                    Textarea::make('address')
                        ->label('Alamat')
                        ->required()
                        ->rows(3)
                        ->columnSpanFull(),

                    TextInput::make('latitude')
                        ->label('Latitude')
                        ->numeric()
                        ->required()
                        ->minValue(-90)
                        ->maxValue(90)
                        ->placeholder('-6.2088000')
                        ->helperText('Di Google Maps: klik kanan titik market, lalu klik koordinatnya untuk menyalin.'),

                    TextInput::make('longitude')
                        ->label('Longitude')
                        ->numeric()
                        ->required()
                        ->minValue(-180)
                        ->maxValue(180)
                        ->placeholder('106.8456000'),

                    Toggle::make('is_active')
                        ->label('Aktif')
                        ->default(true)
                        ->helperText('Market nonaktif tidak dipilih otomatis untuk alamat customer.'),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')->label('Nama')->searchable()->sortable(),
                TextColumn::make('address')->label('Alamat')->limit(50)->wrap(),
                TextColumn::make('koordinat')
                    ->label('Koordinat')
                    ->state(fn (Market $record) => "{$record->latitude}, {$record->longitude}")
                    ->color('primary')
                    ->url(fn (Market $record) => "https://www.google.com/maps?q={$record->latitude},{$record->longitude}")
                    ->openUrlInNewTab(),
                TextColumn::make('market_products_count')
                    ->label('Produk')
                    ->counts('marketProducts'),
                ToggleColumn::make('is_active')->label('Aktif'),
            ])
            ->filters([
                TernaryFilter::make('is_active')
                    ->label('Status')
                    ->placeholder('Semua')
                    ->trueLabel('Aktif')
                    ->falseLabel('Nonaktif'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()
                    ->disabled(fn (Market $record) => static::isUsed($record))
                    ->tooltip(fn (Market $record) => static::isUsed($record)
                        ? 'Market sudah dipakai di keranjang/pesanan. Nonaktifkan saja.'
                        : null),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            MarketProductsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMarkets::route('/'),
            'create' => CreateMarket::route('/create'),
            'edit' => EditMarket::route('/{record}/edit'),
        ];
    }
}
