<?php

namespace App\Filament\Resources\Orders;

use App\Filament\Resources\Orders\Pages\ListOrders;
use App\Filament\Resources\Orders\Pages\ViewOrder;
use App\Models\Order;
use App\Support\OrderStatus;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class OrderResource extends Resource
{
    protected static ?string $model = Order::class;

    protected static string|UnitEnum|null $navigationGroup = 'MENU UTAMA';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Pesanan';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-shopping-bag';

    protected static ?string $modelLabel = 'Pesanan';

    protected static ?string $pluralModelLabel = 'Pesanan';

    protected static ?string $recordTitleAttribute = 'order_number';

    public static function canCreate(): bool
    {
        return false;
    }

    // Badge sidebar: pesanan yang sudah dibayar & menunggu diproses
    public static function getNavigationBadge(): ?string
    {
        $count = Order::where('status', 'paid')->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Info Pesanan')
                ->columns(3)
                ->schema([
                    TextEntry::make('order_number')->label('No. Pesanan')->copyable(),
                    TextEntry::make('status')
                        ->badge()
                        ->formatStateUsing(fn (string $state) => OrderStatus::label($state))
                        ->color(fn (string $state) => OrderStatus::color($state)),
                    TextEntry::make('created_at')->label('Dibuat')->dateTime('d M Y H:i'),
                    TextEntry::make('user.name')->label('Customer'),
                    TextEntry::make('market.name')->label('Market'),
                    TextEntry::make('jenis')
                        ->label('Jenis')
                        ->state(fn (Order $record) => $record->shopping_request_id ? 'Ngebelanjain' : 'Marketplace')
                        ->badge(),
                ]),

            Section::make('Alamat Pengiriman')
                ->description('Salinan alamat saat checkout, tidak ikut berubah kalau customer edit profil.')
                ->columns(3)
                ->schema([
                    TextEntry::make('recipient_name')->label('Penerima'),
                    TextEntry::make('recipient_phone')->label('No. Telepon'),
                    TextEntry::make('maps')
                        ->label('Lokasi')
                        ->state('Buka di Google Maps')
                        ->color('primary')
                        ->url(fn (Order $record) => "https://www.google.com/maps?q={$record->shipping_latitude},{$record->shipping_longitude}")
                        ->openUrlInNewTab(),
                    TextEntry::make('shipping_address')->label('Alamat Lengkap')->columnSpanFull(),
                ]),

            Section::make('Rincian Barang')
                ->schema([
                    RepeatableEntry::make('items')
                        ->hiddenLabel()
                        ->columns(4)
                        ->schema([
                            TextEntry::make('product_name')->label('Barang'),
                            TextEntry::make('qty')
                                ->label('Jumlah')
                                ->state(fn ($record) => (float) $record->quantity.' '.$record->unit),
                            TextEntry::make('unit_price')->label('Harga Satuan')->money('IDR', locale: 'id', decimalPlaces: 0),
                            TextEntry::make('subtotal')->label('Subtotal')->money('IDR', locale: 'id', decimalPlaces: 0),
                            TextEntry::make('note')
                                ->label('Catatan customer')
                                ->columnSpanFull()
                                ->visible(fn ($record) => filled($record->note)),
                        ]),
                ]),

            Section::make('Ringkasan Biaya')
                ->columns(4)
                ->schema([
                    TextEntry::make('subtotal')->money('IDR', locale: 'id', decimalPlaces: 0),
                    TextEntry::make('delivery_fee')->label('Ongkir')->money('IDR', locale: 'id', decimalPlaces: 0),
                    TextEntry::make('grand_total')->label('Total')->money('IDR', locale: 'id', decimalPlaces: 0)->weight('bold'),
                    TextEntry::make('total_weight_grams')
                        ->label('Total Berat')
                        ->formatStateUsing(fn ($state) => number_format($state / 1000, 2, ',', '.').' kg'),
                ]),

            Section::make('Riwayat Pembayaran')
                ->description('Satu pesanan bisa punya beberapa percobaan pembayaran.')
                ->schema([
                    RepeatableEntry::make('payments')
                        ->hiddenLabel()
                        ->columns(5)
                        ->schema([
                            TextEntry::make('payment_number')->label('No. Pembayaran'),
                            TextEntry::make('payment_method')->label('Metode')->placeholder('-'),
                            TextEntry::make('amount')->label('Nominal')->money('IDR', locale: 'id', decimalPlaces: 0),
                            TextEntry::make('status')
                                ->badge()
                                ->color(fn (string $state) => match ($state) {
                                    'paid' => 'success',
                                    'pending' => 'warning',
                                    default => 'danger',
                                }),
                            TextEntry::make('paid_at')->label('Dibayar')->dateTime('d M Y H:i')->placeholder('-'),
                        ]),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['user', 'market']))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('order_number')->label('No. Pesanan')->searchable()->copyable(),
                TextColumn::make('user.name')->label('Customer')->searchable(),
                TextColumn::make('recipient_name')->label('Penerima')->searchable(),
                TextColumn::make('jenis')
                    ->label('Jenis')
                    ->state(fn (Order $record) => $record->shopping_request_id ? 'Ngebelanjain' : 'Marketplace')
                    ->badge()
                    ->color(fn (string $state) => $state === 'Ngebelanjain' ? 'warning' : 'gray'),
                TextColumn::make('market.name')->label('Market'),
                TextColumn::make('grand_total')->label('Total')->money('IDR', locale: 'id', decimalPlaces: 0)->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => OrderStatus::label($state))
                    ->color(fn (string $state) => OrderStatus::color($state)),
                TextColumn::make('created_at')->label('Dibuat')->dateTime('d M Y H:i')->sortable(),
            ])
            ->filters([
                SelectFilter::make('market_id')
                    ->label('Market')
                    ->relationship('market', 'name'),

                TernaryFilter::make('jenis')
                    ->label('Jenis')
                    ->placeholder('Semua')
                    ->trueLabel('Ngebelanjain')
                    ->falseLabel('Marketplace')
                    ->queries(
                        true: fn (Builder $q) => $q->whereNotNull('shopping_request_id'),
                        false: fn (Builder $q) => $q->whereNull('shopping_request_id'),
                        blank: fn (Builder $q) => $q,
                    ),

                Filter::make('tanggal')
                    ->schema([
                        DatePicker::make('dari'),
                        DatePicker::make('sampai'),
                    ])
                    ->query(fn (Builder $query, array $data) => $query
                        ->when($data['dari'] ?? null, fn ($q, $d) => $q->whereDate('created_at', '>=', $d))
                        ->when($data['sampai'] ?? null, fn ($q, $d) => $q->whereDate('created_at', '<=', $d))),
            ])
            ->recordActions([
                ViewAction::make()->label('Detail'),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListOrders::route('/'),
            'view' => ViewOrder::route('/{record}'),
        ];
    }
}
