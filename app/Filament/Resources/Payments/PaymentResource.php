<?php

namespace App\Filament\Resources\Payments;

use App\Filament\Resources\Orders\OrderResource;
use App\Filament\Resources\Payments\Pages\ListPayments;
use App\Filament\Resources\Payments\Pages\ViewPayment;
use App\Models\Payment;
use App\Support\PaymentStatus;
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
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class PaymentResource extends Resource
{
    protected static ?string $model = Payment::class;

    protected static string | UnitEnum | null $navigationGroup = 'MENU UTAMA';

    protected static ?int $navigationSort = 5;

    protected static ?string $navigationLabel = 'Pembayaran';

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-credit-card';

    protected static ?string $modelLabel = 'Pembayaran';

    protected static ?string $pluralModelLabel = 'Pembayaran';

    protected static ?string $recordTitleAttribute = 'payment_number';

    public static function canCreate(): bool
    {
        return false;
    }

    // Badge sidebar: tagihan yang masih menunggu dibayar
    public static function getNavigationBadge(): ?string
    {
        $count = Payment::where('status', 'pending')->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Info Pembayaran')
                ->columns(3)
                ->schema([
                    TextEntry::make('payment_number')->label('No. Pembayaran')->copyable(),
                    TextEntry::make('status')
                        ->badge()
                        ->formatStateUsing(fn (string $state) => PaymentStatus::label($state))
                        ->color(fn (string $state) => PaymentStatus::color($state)),
                    TextEntry::make('amount')->label('Nominal')->money('IDR', locale: 'id', decimalPlaces: 0)->weight('bold'),
                    TextEntry::make('order.order_number')
                        ->label('Pesanan')
                        ->color('primary')
                        ->url(fn (Payment $record) => OrderResource::getUrl('view', ['record' => $record->order_id])),
                    TextEntry::make('order.user.name')->label('Customer'),
                    TextEntry::make('created_at')->label('Dibuat')->dateTime('d M Y H:i'),
                ]),

            Section::make('Rincian Transaksi')
                ->columns(3)
                ->schema([
                    TextEntry::make('payment_method')->label('Metode')->placeholder('-'),
                    TextEntry::make('payment_gateway')->label('Gateway')->placeholder('-'),
                    TextEntry::make('transaction_id')->label('ID Transaksi')->placeholder('-')->copyable(),
                    TextEntry::make('paid_at')->label('Dibayar')->dateTime('d M Y H:i')->placeholder('-'),
                    TextEntry::make('expired_at')->label('Kedaluwarsa')->dateTime('d M Y H:i')->placeholder('-'),
                    TextEntry::make('payment_url')
                        ->label('Link Pembayaran')
                        ->state('Buka link pembayaran')
                        ->color('primary')
                        ->url(fn (Payment $record) => $record->payment_url)
                        ->openUrlInNewTab()
                        ->visible(fn (Payment $record) => filled($record->payment_url)),
                ]),

            Section::make('Riwayat Percobaan Pembayaran')
                ->description('Semua tagihan untuk pesanan yang sama.')
                ->schema([
                    RepeatableEntry::make('riwayat')
                        ->hiddenLabel()
                        ->columns(4)
                        ->state(fn (Payment $record) => $record->order->payments()->oldest()->get()
                            ->map(fn (Payment $p) => [
                                'payment_number' => $p->payment_number,
                                'amount' => $p->amount,
                                'status' => $p->status,
                                'paid_at' => $p->paid_at,
                            ])->all())
                        ->schema([
                            TextEntry::make('payment_number')->label('No. Pembayaran'),
                            TextEntry::make('amount')->label('Nominal')->money('IDR', locale: 'id', decimalPlaces: 0),
                            TextEntry::make('status')
                                ->badge()
                                ->formatStateUsing(fn (string $state) => PaymentStatus::label($state))
                                ->color(fn (string $state) => PaymentStatus::color($state)),
                            TextEntry::make('paid_at')->label('Dibayar')->dateTime('d M Y H:i')->placeholder('-'),
                        ]),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['order.user']))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('payment_number')->label('No. Pembayaran')->searchable()->copyable(),
                TextColumn::make('order.order_number')
                    ->label('Pesanan')
                    ->searchable()
                    ->color('primary')
                    ->url(fn (Payment $record) => OrderResource::getUrl('view', ['record' => $record->order_id])),
                TextColumn::make('order.user.name')->label('Customer'),
                TextColumn::make('amount')->label('Nominal')->money('IDR', locale: 'id', decimalPlaces: 0)->sortable(),
                TextColumn::make('payment_method')->label('Metode')->placeholder('-'),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => PaymentStatus::label($state))
                    ->color(fn (string $state) => PaymentStatus::color($state)),
                TextColumn::make('paid_at')->label('Dibayar')->dateTime('d M Y H:i')->placeholder('-')->sortable(),
                TextColumn::make('expired_at')->label('Kedaluwarsa')->dateTime('d M Y H:i')->placeholder('-'),
            ])
            ->filters([
                SelectFilter::make('status')->options(PaymentStatus::LABELS),

                SelectFilter::make('payment_method')
                    ->label('Metode')
                    ->options(fn () => Payment::whereNotNull('payment_method')
                        ->distinct()
                        ->orderBy('payment_method')
                        ->pluck('payment_method', 'payment_method')),

                Filter::make('tanggal')
                    ->schema([
                        DatePicker::make('dari')->label('Dibuat dari'),
                        DatePicker::make('sampai')->label('Dibuat sampai'),
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
            'index' => ListPayments::route('/'),
            'view' => ViewPayment::route('/{record}'),
        ];
    }
}