<?php

namespace App\Filament\Resources\Helpers;

use App\Filament\Resources\Helpers\Pages\ManageHelpers;
use App\Models\Helper;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use UnitEnum;

class HelperResource extends Resource
{
    protected static ?string $model = Helper::class;

    protected static ?string $slug = 'helpers';

    protected static string|UnitEnum|null $navigationGroup = 'MANAJEMEN DATA';

    protected static ?int $navigationSort = 6;

    protected static ?string $navigationLabel = 'Helper';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-user-group';

    protected static ?string $modelLabel = 'Helper';

    protected static ?string $pluralModelLabel = 'Helper';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')
                ->label('Nama')
                ->required()
                ->maxLength(255),

            TextInput::make('phone')
                ->label('No. telepon')
                ->tel()
                ->maxLength(20),

            Toggle::make('is_active')
                ->label('Aktif')
                ->default(true)
                ->helperText('Hanya helper aktif yang muncul saat menugaskan permintaan Ngebelanjain.'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')->label('Nama')->searchable()->sortable(),
                TextColumn::make('phone')->label('Telepon')->placeholder('-'),
                TextColumn::make('assignments_count')
                    ->label('Total penugasan')
                    ->counts('assignments')
                    ->sortable(),
                TextColumn::make('penugasan_aktif')
                    ->label('Penugasan aktif')
                    ->state(fn (Helper $record) => $record->assignments()
                        ->whereIn('status', ['assigned', 'in_progress'])
                        ->count())
                    ->badge()
                    ->color(fn ($state) => $state > 0 ? 'warning' : 'gray'),
                ToggleColumn::make('is_active')
                    ->label('Aktif')
                    ->afterStateUpdated(function () {
                        if (! Helper::where('is_active', true)->exists()) {
                            Notification::make()
                                ->title('Tidak ada helper aktif')
                                ->body('Permintaan Ngebelanjain belum bisa ditugaskan sampai ada helper aktif.')
                                ->warning()
                                ->send();
                        }
                    }),
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
                    ->disabled(fn (Helper $record) => $record->assignments()->exists())
                    ->tooltip(fn (Helper $record) => $record->assignments()->exists()
                        ? 'Sudah punya riwayat penugasan. Nonaktifkan saja.'
                        : null)
                    ->before(function (DeleteAction $action, Helper $record) {
                        if ($record->assignments()->exists()) {
                            Notification::make()
                                ->title('Helper tidak bisa dihapus')
                                ->body('Sudah punya riwayat penugasan. Nonaktifkan saja.')
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
            'index' => ManageHelpers::route('/'),
        ];
    }
}
