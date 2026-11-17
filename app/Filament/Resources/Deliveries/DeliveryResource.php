<?php

namespace App\Filament\Resources\Deliveries;

use App\Filament\Resources\Deliveries\Pages\CreateDelivery;
use App\Filament\Resources\Deliveries\Pages\ListDeliveries;
use App\Filament\Resources\Deliveries\Pages\ViewDelivery;
use App\Filament\Resources\Deliveries\RelationManagers\OrdersRelationManager;
use App\Models\Delivery;
use App\Models\Order;
use App\Models\User;
use App\Models\DeliveryOrder;
use App\Models\DriverProfile;
use App\Support\DeliveryStatus;
use BackedEnum;
use Closure;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use UnitEnum;

class DeliveryResource extends Resource
{
    public const MAX_ORDERS = 3;

    public const MAX_GRAMS = 50000; // 50 kg

    protected static ?string $model = Delivery::class;

    protected static string | UnitEnum | null $navigationGroup = 'MENU UTAMA';

    protected static ?int $navigationSort = 4;

    protected static ?string $navigationLabel = 'Pengiriman';

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-truck';

    protected static ?string $modelLabel = 'Pengiriman';

    protected static ?string $pluralModelLabel = 'Pengiriman';

    protected static ?string $recordTitleAttribute = 'delivery_number';

    /**
     * Pesanan berstatus "Siap Dikirim" yang belum masuk pengiriman aktif.
     */
    public static function availableOrdersQuery(): Builder
    {
        $aktif = DB::table('delivery_orders')
            ->join('deliveries', 'deliveries.delivery_id', '=', 'delivery_orders.delivery_id')
            ->whereNull('deliveries.cancelled_at')
            ->whereIn('delivery_orders.status', DeliveryOrder::ACTIVE_STATUSES)
            ->select('delivery_orders.order_id');

        return Order::query()
            ->where('status', 'ready_for_delivery')
            ->whereNotIn('order_id', $aktif);
    }

    // Badge sidebar: pesanan siap kirim yang belum dapat driver
    public static function getNavigationBadge(): ?string
    {
        $count = static::availableOrdersQuery()->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function driverOptions(bool $semua = false): array
    {
        return DriverProfile::query()
            ->when(! $semua, fn($q) => $q->where('approval_status', 'approved'))
            ->with('user:user_id,name')
            ->get()
            ->sortBy('user.name')
            ->mapWithKeys(fn(DriverProfile $p) => [$p->driver_profile_id => $p->user?->name])
            ->all();
    }

    public static function muatan(array $orderIds): array
    {
        $grams = (int) Order::whereIn('order_id', $orderIds)->sum('total_weight_grams');
        $count = count($orderIds);

        return [
            'text' => "{$count}/" . self::MAX_ORDERS . ' pesanan · '
                . number_format($grams / 1000, 2, ',', '.') . '/' . (self::MAX_GRAMS / 1000) . ' kg',
            'over' => $count > self::MAX_ORDERS || $grams > self::MAX_GRAMS,
        ];
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Buat Pengiriman')
                ->description('Maksimal 3 pesanan dan total 50 kg per perjalanan.')
                ->schema([
                    Select::make('driver_profile_id')
                        ->label('Driver')
                        ->options(fn() => static::driverOptions())
                        ->searchable()
                        ->required(),

                    Select::make('order_ids')
                        ->label('Pesanan siap kirim')
                        ->multiple()
                        ->options(fn() => static::availableOrdersQuery()
                            ->get()
                            ->mapWithKeys(fn(Order $o) => [
                                $o->order_id => "{$o->order_number} · {$o->recipient_name} · "
                                    . number_format($o->total_weight_grams / 1000, 2, ',', '.') . ' kg',
                            ]))
                        ->maxItems(self::MAX_ORDERS)
                        ->live()
                        ->required()
                        ->rules([
                            static fn() => function (string $attribute, $value, Closure $fail) {
                                if (static::muatan((array) $value)['over']) {
                                    $fail('Muatan melebihi batas 3 pesanan / 50 kg.');
                                }
                            },
                        ]),

                    TextEntry::make('muatan')
                        ->label('Muatan terpilih')
                        ->state(fn(Get $get) => static::muatan((array) $get('order_ids'))['text'])
                        ->color(fn(Get $get) => static::muatan((array) $get('order_ids'))['over'] ? 'danger' : 'success')
                        ->weight('bold'),
                ]),
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Info Pengiriman')
                ->columns(3)
                ->schema([
                    TextEntry::make('delivery_number')->label('No. Pengiriman')->copyable(),
                    TextEntry::make('status')
                        ->badge()
                        ->formatStateUsing(fn(string $state) => DeliveryStatus::label($state))
                        ->color(fn(string $state) => DeliveryStatus::color($state)),
                    TextEntry::make('driver.user.name')->label('Driver'),
                    TextEntry::make('muatan')
                        ->label('Muatan')
                        ->state(fn(Delivery $record) => static::muatan($record->orders->modelKeys())['text']),
                    TextEntry::make('started_at')->label('Berangkat')->dateTime('d M Y H:i')->placeholder('-'),
                    TextEntry::make('completed_at')->label('Selesai')->dateTime('d M Y H:i')->placeholder('-'),
                ]),


        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn(Builder $query) => $query->with(['driver.user', 'orders']))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('delivery_number')->label('No. Pengiriman')->searchable()->copyable(),
                TextColumn::make('driver.user.name')->label('Driver')->searchable(),
                TextColumn::make('orders_count')->label('Pesanan')->counts('orders'),
                TextColumn::make('berat')
                    ->label('Berat')
                    ->state(fn(Delivery $record) => number_format($record->orders->sum('total_weight_grams') / 1000, 2, ',', '.') . ' kg'),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn(string $state) => DeliveryStatus::label($state))
                    ->color(fn(string $state) => DeliveryStatus::color($state)),
                TextColumn::make('started_at')->label('Berangkat')->dateTime('d M Y H:i')->placeholder('-'),
                TextColumn::make('completed_at')->label('Selesai')->dateTime('d M Y H:i')->placeholder('-'),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(DeliveryStatus::LABELS)
                    ->query(fn(Builder $query, array $data) => filled($data['value'] ?? null)
                        ? $query->withStatus($data['value'])
                        : $query),
                SelectFilter::make('driver_profile_id')
                    ->label('Driver')
                    ->options(fn() => static::driverOptions(true)),
            ])
            ->recordActions([
                ViewAction::make()->label('Detail'),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            OrdersRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDeliveries::route('/'),
            'create' => CreateDelivery::route('/create'),
            'view' => ViewDelivery::route('/{record}'),
        ];
    }
}
