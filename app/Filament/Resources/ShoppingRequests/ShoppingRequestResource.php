<?php

namespace App\Filament\Resources\ShoppingRequests;

use App\Filament\Resources\Orders\OrderResource;
use App\Filament\Resources\ShoppingRequests\Pages\ListShoppingRequests;
use App\Filament\Resources\ShoppingRequests\Pages\ViewShoppingRequest;
use App\Models\ShoppingRequest;
use App\Support\ShoppingRequestStatus;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class ShoppingRequestResource extends Resource
{
    protected static ?string $model = ShoppingRequest::class;

    protected static string | UnitEnum | null $navigationGroup = 'MENU UTAMA';

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationLabel = 'Ngebelanjain';

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-shopping-cart';

    protected static ?string $modelLabel = 'Permintaan Ngebelanjain';

    protected static ?string $pluralModelLabel = 'Ngebelanjain';

    protected static ?string $recordTitleAttribute = 'request_number';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getNavigationBadge(): ?string
    {
        $count = ShoppingRequest::where('status', 'pending')->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Info Permintaan')
                ->columns(3)
                ->schema([
                    TextEntry::make('request_number')->label('No. Permintaan')->copyable(),
                    TextEntry::make('status')
                        ->badge()
                        ->formatStateUsing(fn (string $state) => ShoppingRequestStatus::label($state))
                        ->color(fn (string $state) => ShoppingRequestStatus::color($state)),
                    TextEntry::make('created_at')->label('Dibuat')->dateTime('d M Y H:i'),
                    TextEntry::make('user.name')->label('Customer'),
                    TextEntry::make('market.name')->label('Market'),
                    TextEntry::make('helper')
                        ->label('Helper')
                        ->state(fn (ShoppingRequest $record) => $record->currentAssignment?->helper?->name ?? 'Belum ditugaskan'),
                    TextEntry::make('notes')->label('Catatan Customer')->placeholder('-')->columnSpanFull(),
                ]),

            Section::make('Alamat Pengiriman')
                ->description('Alamat dari profil customer. Disalin (snapshot) ke pesanan saat Kirim Harga.')
                ->columns(3)
                ->schema([
                    TextEntry::make('address.recipient_name')->label('Penerima')->placeholder('-'),
                    TextEntry::make('address.phone')->label('No. Telepon')->placeholder('-'),
                    TextEntry::make('maps')
                        ->label('Lokasi')
                        ->state(fn (ShoppingRequest $record) => $record->address ? 'Buka di Google Maps' : '-')
                        ->color('primary')
                        ->url(fn (ShoppingRequest $record) => $record->address
                            ? "https://www.google.com/maps?q={$record->address->latitude},{$record->address->longitude}"
                            : null)
                        ->openUrlInNewTab(),
                    TextEntry::make('address.full_address')->label('Alamat Lengkap')->placeholder('Alamat sudah dihapus customer')->columnSpanFull(),
                ]),

            Section::make('Hasil Belanja')
                ->schema([
                    TextEntry::make('mode_input')
                        ->label('Metode Input')
                        ->state(function (ShoppingRequest $record) {
                            if ($record->items->isNotEmpty()) {
                                return 'Rincian barang';
                            }

                            return $record->goods_total > 0 ? 'Total saja' : 'Belum diinput';
                        })
                        ->badge(),

                    RepeatableEntry::make('items')
                        ->hiddenLabel()
                        ->columns(4)
                        ->visible(fn (ShoppingRequest $record) => $record->items->isNotEmpty())
                        ->schema([
                            TextEntry::make('name')->label('Barang'),
                            TextEntry::make('qty')
                                ->label('Jumlah')
                                ->state(fn ($record) => (float) $record->quantity . ' ' . $record->unit),
                            TextEntry::make('unit_price')->label('Harga Satuan')->money('IDR', locale: 'id', decimalPlaces: 0),
                            TextEntry::make('subtotal')->money('IDR', locale: 'id', decimalPlaces: 0),
                        ]),
                ]),

            Section::make('Ringkasan Biaya')
                ->columns(4)
                ->schema([
                    TextEntry::make('goods_total')->label('Total Belanja')->money('IDR', locale: 'id', decimalPlaces: 0),
                    TextEntry::make('service_fee')->label('Biaya Layanan')->money('IDR', locale: 'id', decimalPlaces: 0),
                    TextEntry::make('grand')
                        ->label('Total Tagihan')
                        ->state(fn (ShoppingRequest $record) => $record->goods_total + $record->service_fee)
                        ->money('IDR', locale: 'id', decimalPlaces: 0)
                        ->weight('bold'),
                    TextEntry::make('total_weight')
                        ->label('Total Berat')
                        ->formatStateUsing(fn ($state) => number_format((float) $state, 2, ',', '.') . ' kg'),
                ]),

            Section::make('Pesanan Terkait')
                ->visible(fn (ShoppingRequest $record) => $record->order()->exists())
                ->schema([
                    TextEntry::make('order_link')
                        ->label('No. Pesanan')
                        ->state(fn (ShoppingRequest $record) => $record->order?->order_number)
                        ->url(fn (ShoppingRequest $record) => $record->order
                            ? OrderResource::getUrl('view', ['record' => $record->order])
                            : null)
                        ->color('primary'),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['user', 'market']))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('request_number')->label('No. Permintaan')->searchable()->copyable(),
                TextColumn::make('user.name')->label('Customer')->searchable(),
                TextColumn::make('market.name')->label('Market'),
                TextColumn::make('items_count')->label('Item')->counts('items'),
                TextColumn::make('goods_total')->label('Total Belanja')->money('IDR', locale: 'id', decimalPlaces: 0),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => ShoppingRequestStatus::label($state))
                    ->color(fn (string $state) => ShoppingRequestStatus::color($state)),
                TextColumn::make('created_at')->label('Dibuat')->dateTime('d M Y H:i')->sortable(),
            ])
            ->filters([
                SelectFilter::make('market_id')->label('Market')->relationship('market', 'name'),
                SelectFilter::make('status')->options(ShoppingRequestStatus::LABELS),
            ])
            ->recordActions([
                ViewAction::make()->label('Detail'),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListShoppingRequests::route('/'),
            'view' => ViewShoppingRequest::route('/{record}'),
        ];
    }
}