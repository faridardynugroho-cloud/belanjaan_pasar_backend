<?php

namespace App\Filament\Resources\Products;

use App\Filament\Resources\Products\Pages\CreateProduct;
use App\Filament\Resources\Products\Pages\EditProduct;
use App\Filament\Resources\Products\Pages\ListProducts;
use App\Filament\Resources\Products\RelationManagers\ReviewsRelationManager;
use App\Models\Product;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class ProductResource extends Resource
{
    protected static ?string $model = Product::class;

    protected static string|UnitEnum|null $navigationGroup = 'MANAJEMEN DATA';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Produk';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-cube';

    protected static ?string $modelLabel = 'Produk';

    protected static ?string $pluralModelLabel = 'Produk';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Info Produk')
                ->columns(2)
                ->extraAttributes(['style' => 'max-width: 48rem; margin-inline: auto;'])
                ->schema([
                    TextInput::make('name')
                        ->label('Nama produk')
                        ->required()
                        ->maxLength(255)
                        ->columnSpanFull(),

                    Select::make('category_id')
                        ->label('Kategori')
                        ->relationship('category', 'name', fn (Builder $query) => $query->active())
                        ->searchable()
                        ->preload()
                        ->required()
                        ->createOptionForm([
                            TextInput::make('name')
                                ->label('Nama kategori')
                                ->required()
                                ->maxLength(255),
                            Textarea::make('description')
                                ->label('Deskripsi')
                                ->rows(2),
                        ]),

                    TextInput::make('unit')
                        ->label('Satuan')
                        ->required()
                        ->maxLength(20)
                        ->datalist(['kg', 'ons', 'gram', 'ikat', 'buah', 'butir', 'biji', 'sisir', 'pak', 'liter'])
                        ->helperText('Satuan jual, misal kg atau ikat.'),

                    TextInput::make('weight_grams')
                        ->label('Berat per 1 satuan')
                        ->numeric()
                        ->integer()
                        ->minValue(1)
                        ->required()
                        ->suffix('gram')
                        ->helperText('Dipakai untuk batas 50 kg per pengiriman. Contoh: satuan kg isi 1000, satuan ikat isi perkiraan berat seikat.')
                        ->columnSpanFull(),

                    Textarea::make('description')
                        ->label('Deskripsi')
                        ->rows(3)
                        ->columnSpanFull(),

                    FileUpload::make('image')
                        ->label('Foto produk')
                        ->image()
                        ->disk('public')
                        ->directory('products')
                        ->visibility('public')
                        ->maxSize(2048)
                        ->helperText('Maksimal 2 MB.')
                        ->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('category'))
            ->defaultSort('name')
            ->columns([
                ImageColumn::make('image')
                    ->label('Foto')
                    ->disk('public')
                    ->square()
                    ->imageSize(48),
                TextColumn::make('name')->label('Nama')->searchable()->sortable(),
                TextColumn::make('category.name')->label('Kategori')->searchable()->sortable(),
                TextColumn::make('unit')->label('Satuan'),
                TextColumn::make('weight_grams')
                    ->label('Berat/satuan')
                    ->formatStateUsing(fn ($state) => number_format((int) $state, 0, ',', '.').' g')
                    ->sortable(),
                TextColumn::make('market_products_count')
                    ->label('Dijual di')
                    ->counts('marketProducts')
                    ->suffix(' market')
                    ->sortable(),
                TextColumn::make('rating_avg')
                    ->label('Rating')
                    ->formatStateUsing(fn ($state, Product $record) => $record->rating_count
                        ? number_format((float) $state, 1).' ('.$record->rating_count.')'
                        : '-')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('category_id')
                    ->label('Kategori')
                    ->relationship('category', 'name'),

                Filter::make('tanpa_market')
                    ->label('Belum dijual di market')
                    ->toggle()
                    ->query(fn (Builder $query) => $query->doesntHave('marketProducts')),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()
                    ->modalDescription('Produk akan hilang dari semua market dan dari keranjang customer. Riwayat pesanan tetap aman karena nama dan harga sudah disalin ke pesanan.'),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            ReviewsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProducts::route('/'),
            'create' => CreateProduct::route('/create'),
            'edit' => EditProduct::route('/{record}/edit'),
        ];
    }
}
