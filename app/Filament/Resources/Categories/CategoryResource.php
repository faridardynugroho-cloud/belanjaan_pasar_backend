<?php

namespace App\Filament\Resources\Categories;

use App\Filament\Resources\Categories\Pages\ManageCategories;
use App\Models\Category;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Forms\Components\Toggle;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\TernaryFilter;
use UnitEnum;

class CategoryResource extends Resource
{
    protected static ?string $model = Category::class;

    protected static string | UnitEnum | null $navigationGroup = 'MANAJEMEN DATA';

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationLabel = 'Kategori';

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-squares-2x2';

    protected static ?string $modelLabel = 'Kategori';

    protected static ?string $pluralModelLabel = 'Kategori';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')
                ->label('Nama kategori')
                ->required()
                ->maxLength(255)
                ->unique(ignoreRecord: true),

            Textarea::make('description')
                ->label('Deskripsi')
                ->rows(3),

            Toggle::make('is_active')
                ->label('Aktif')
                ->default(true)
                ->helperText('Kategori nonaktif tidak ditampilkan ke customer.'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')->label('Nama')->searchable()->sortable(),
                TextColumn::make('description')->label('Deskripsi')->limit(60)->wrap()->placeholder('-'),
                TextColumn::make('products_count')
                    ->label('Produk')
                    ->counts('products')
                    ->suffix(' produk')
                    ->sortable(),
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
                    ->disabled(fn(Category $record) => $record->products()->exists())
                    ->tooltip(fn(Category $record) => $record->products()->exists()
                        ? 'Masih punya produk. Pindahkan atau hapus produknya dulu.'
                        : null)
                    ->before(function (DeleteAction $action, Category $record) {
                        if ($record->products()->exists()) {
                            Notification::make()
                                ->title('Kategori tidak bisa dihapus')
                                ->body('Masih ada produk di kategori ini.')
                                ->danger()
                                ->send();

                            $action->cancel();
                        }
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageCategories::route('/'),
        ];
    }
}
