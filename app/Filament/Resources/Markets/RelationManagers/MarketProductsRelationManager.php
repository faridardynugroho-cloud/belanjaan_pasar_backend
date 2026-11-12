<?php

namespace App\Filament\Resources\Markets\RelationManagers;

use App\Models\Product;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\TextInputColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class MarketProductsRelationManager extends RelationManager
{
    protected static string $relationship = 'marketProducts';

    protected static ?string $title = 'Produk & Harga di Market Ini';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('product_id')
                ->label('Produk')
                ->options(function (?Model $record) {
                    // Satu produk hanya boleh sekali per market (UNIQUE market_id + product_id)
                    $sudahAda = $this->getOwnerRecord()->marketProducts()
                        ->pluck('product_id')
                        ->reject(fn ($id) => $record && $id == $record->product_id);

                    return Product::whereNotIn('product_id', $sudahAda)
                        ->orderBy('name')
                        ->pluck('name', 'product_id');
                })
                ->searchable()
                ->required()
                ->disabled(fn (string $operation) => $operation === 'edit')
                ->dehydrated(fn (string $operation) => $operation === 'create'),

            TextInput::make('price')
                ->label('Harga')
                ->numeric()
                ->prefix('Rp')
                ->minValue(0)
                ->required(),

            TextInput::make('stock')
                ->label('Stok')
                ->numeric()
                ->minValue(0)
                ->default(0)
                ->required()
                ->helperText('Satuannya mengikuti satuan produk (kg, ikat, dst.).'),

            Toggle::make('is_available')
                ->label('Tersedia')
                ->default(true),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('product.category'))
            ->columns([
                TextColumn::make('product.name')->label('Produk')->searchable(),
                TextColumn::make('product.category.name')->label('Kategori'),
                TextColumn::make('product.unit')->label('Satuan'),
                TextInputColumn::make('price')
                    ->label('Harga (Rp)')
                    ->type('number')
                    ->rules(['required', 'numeric', 'min:0']),
                TextInputColumn::make('stock')
                    ->label('Stok')
                    ->type('number')
                    ->rules(['required', 'numeric', 'min:0']),
                ToggleColumn::make('is_available')->label('Tersedia'),
            ])
            ->filters([
                TernaryFilter::make('is_available')
                    ->label('Ketersediaan')
                    ->placeholder('Semua')
                    ->trueLabel('Tersedia')
                    ->falseLabel('Tidak tersedia'),
            ])
            ->headerActions([
                CreateAction::make()->label('Tambah Produk'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}