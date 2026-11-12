<?php

namespace App\Filament\Resources\Customers;

use App\Filament\Resources\Customers\Pages\ListCustomers;
use App\Filament\Resources\Customers\Pages\ViewCustomer;
use App\Filament\Resources\Customers\RelationManagers\AddressesRelationManager;
use App\Filament\Resources\Customers\RelationManagers\OrdersRelationManager;
use App\Models\User;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;
use App\Support\Gender;

class CustomerResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $slug = 'customers';

    protected static string | UnitEnum | null $navigationGroup = 'MANAJEMEN DATA';

    protected static ?int $navigationSort = 4;

    protected static ?string $navigationLabel = 'Customer';

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-users';

    protected static ?string $modelLabel = 'Customer';

    protected static ?string $pluralModelLabel = 'Customer';

    protected static ?string $recordTitleAttribute = 'name';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('role', 'customer');
    }

    // Akun customer lahir dari registrasi di app, admin hanya melihat
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

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Info Akun')
                ->columns(3)
                ->schema([
                    TextEntry::make('name')->label('Nama'),
                    TextEntry::make('email')->copyable(),
                    TextEntry::make('phone')->label('No. Telepon')->placeholder('-')->copyable(),
                    TextEntry::make('created_at')->label('Terdaftar')->dateTime('d M Y H:i'),
                    TextEntry::make('gender')
                        ->label('Jenis kelamin')
                        ->state(fn(User $record) => Gender::label($record->gender)),
                    TextEntry::make('birth_date')->label('Tanggal lahir')->date('d M Y')->placeholder('-'),
                    TextEntry::make('address')->label('Alamat lengkap')->placeholder('-')->columnSpanFull(),
                ]),

            Section::make('Ringkasan')
                ->columns(4)
                ->schema([
                    TextEntry::make('total_pesanan')
                        ->label('Total pesanan')
                        ->state(fn(User $record) => $record->orders()->count()),
                    TextEntry::make('pesanan_selesai')
                        ->label('Pesanan selesai')
                        ->state(fn(User $record) => $record->orders()->where('status', 'completed')->count()),
                    TextEntry::make('total_belanja')
                        ->label('Total belanja (selesai)')
                        ->state(fn(User $record) => $record->orders()->where('status', 'completed')->sum('grand_total'))
                        ->money('IDR', locale: 'id', decimalPlaces: 0),
                    TextEntry::make('total_ngebelanjain')
                        ->label('Permintaan Ngebelanjain')
                        ->state(fn(User $record) => $record->shoppingRequests()->count()),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('name')->label('Nama')->searchable()->sortable(),
                TextColumn::make('email')->searchable()->copyable(),
                TextColumn::make('phone')->label('Telepon')->searchable()->placeholder('-'),
                TextColumn::make('orders_count')
                    ->label('Pesanan')
                    ->counts('orders')
                    ->sortable(),
                TextColumn::make('orders_sum_grand_total')
                    ->label('Total belanja (selesai)')
                    ->sum(['orders' => fn(Builder $query) => $query->where('status', 'completed')], 'grand_total')
                    ->money('IDR', locale: 'id', decimalPlaces: 0)
                    ->sortable(),
                TextColumn::make('created_at')->label('Terdaftar')->dateTime('d M Y')->sortable(),
                TextColumn::make('gender')
                    ->label('Jenis kelamin')
                    ->formatStateUsing(fn(?string $state) => Gender::label($state))
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('birth_date')
                    ->label('Tanggal lahir')
                    ->date('d M Y')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TernaryFilter::make('punya_pesanan')
                    ->label('Riwayat pesanan')
                    ->placeholder('Semua')
                    ->trueLabel('Pernah memesan')
                    ->falseLabel('Belum pernah memesan')
                    ->queries(
                        true: fn(Builder $q) => $q->has('orders'),
                        false: fn(Builder $q) => $q->doesntHave('orders'),
                        blank: fn(Builder $q) => $q,
                    ),

                Filter::make('terdaftar')
                    ->schema([
                        DatePicker::make('dari')->label('Daftar dari'),
                        DatePicker::make('sampai')->label('Daftar sampai'),
                    ])
                    ->query(fn(Builder $query, array $data) => $query
                        ->when($data['dari'] ?? null, fn($q, $d) => $q->whereDate('created_at', '>=', $d))
                        ->when($data['sampai'] ?? null, fn($q, $d) => $q->whereDate('created_at', '<=', $d))),
            ])
            ->recordActions([
                ViewAction::make()->label('Detail'),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            AddressesRelationManager::class,
            OrdersRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCustomers::route('/'),
            'view' => ViewCustomer::route('/{record}'),
        ];
    }
}
