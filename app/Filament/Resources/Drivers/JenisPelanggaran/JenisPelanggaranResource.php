<?php

namespace App\Filament\Resources\Drivers\JenisPelanggaran;

use App\Filament\Resources\Drivers\JenisPelanggaran\Pages\ManageJenisPelanggaran;
use App\Models\ViolationType;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use UnitEnum;

class JenisPelanggaranResource extends Resource
{
    protected static ?string $model = ViolationType::class;

    protected static ?string $slug = 'driver/jenis-pelanggaran';

    protected static string|UnitEnum|null $navigationGroup = 'Driver';

    protected static ?int $navigationSort = 5;

    protected static ?string $navigationLabel = 'Jenis Pelanggaran';

    protected static ?string $modelLabel = 'Jenis Pelanggaran';

    protected static ?string $pluralModelLabel = 'Jenis Pelanggaran';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')
                ->label('Nama pelanggaran')
                ->required()
                ->maxLength(100)
                ->unique(ignoreRecord: true),

            Textarea::make('description')
                ->label('Deskripsi')
                ->rows(3)
                ->helperText('Penjelasan singkat supaya admin dan customer paham maksud pelanggaran ini.'),

            Grid::make(3)->schema([
                TextInput::make('min_points')
                    ->label('Poin minimum')
                    ->required()
                    ->numeric()
                    ->integer()
                    ->minValue(1)
                    ->maxValue(65535)
                    ->lte('default_points'),

                TextInput::make('default_points')
                    ->label('Poin default')
                    ->required()
                    ->numeric()
                    ->integer()
                    ->minValue(1)
                    ->maxValue(65535)
                    ->gte('min_points')
                    ->lte('max_points'),

                TextInput::make('max_points')
                    ->label('Poin maksimum')
                    ->required()
                    ->numeric()
                    ->integer()
                    ->minValue(1)
                    ->maxValue(65535)
                    ->gte('default_points'),
            ])->columnSpanFull(),

            Toggle::make('is_active')
                ->label('Aktif')
                ->default(true)
                ->helperText('Jenis nonaktif tidak bisa dipilih untuk laporan baru. Laporan lama tetap tercatat.'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')->label('Nama')->searchable()->sortable(),
                TextColumn::make('description')->label('Deskripsi')->limit(60)->wrap()->placeholder('-'),
                TextColumn::make('default_points')
                    ->label('Poin default')
                    ->numeric()
                    ->suffix(' poin')
                    ->sortable(),
                TextColumn::make('rentang')
                    ->label('Rentang poin')
                    ->state(fn (ViolationType $record) => $record->min_points.' - '.$record->max_points),
                TextColumn::make('violations_count')
                    ->label('Dipakai')
                    ->counts('violations')
                    ->suffix(' laporan')
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
                    ->disabled(fn (ViolationType $record) => $record->violations()->exists())
                    ->tooltip(fn (ViolationType $record) => $record->violations()->exists()
                        ? 'Sudah pernah dipakai di laporan. Nonaktifkan saja.'
                        : null)
                    ->before(function (DeleteAction $action, ViolationType $record) {
                        if ($record->violations()->exists()) {
                            Notification::make()
                                ->title('Jenis pelanggaran tidak bisa dihapus')
                                ->body('Sudah pernah dipakai di laporan. Nonaktifkan saja.')
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
            'index' => ManageJenisPelanggaran::route('/'),
        ];
    }
}
