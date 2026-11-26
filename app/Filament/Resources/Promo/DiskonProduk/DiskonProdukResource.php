<?php

namespace App\Filament\Resources\Promo\DiskonProduk;

use App\Filament\Resources\Promo\DiskonProduk\Pages\ManageDiskonProduk;
use App\Models\Market;
use App\Models\MarketProduct;
use App\Models\ProductDiscount;
use App\Services\DiskonProdukService;
use Closure;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use UnitEnum;

class DiskonProdukResource extends Resource
{
    protected static ?string $model = ProductDiscount::class;

    protected static ?string $slug = 'promo/diskon-produk';

    protected static string|UnitEnum|null $navigationGroup = 'Promo';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Diskon Produk';

    protected static ?string $modelLabel = 'Diskon Produk';

    protected static ?string $pluralModelLabel = 'Diskon Produk';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('market_product_id')
                ->label('Produk di pasar')
                ->required()
                ->searchable()
                ->live()
                ->getSearchResultsUsing(fn (string $search) => MarketProduct::query()
                    ->with(['product', 'market'])
                    ->whereHas('product', fn (Builder $q) => $q->where('name', 'like', "%{$search}%"))
                    ->orWhereHas('market', fn (Builder $q) => $q->where('name', 'like', "%{$search}%"))
                    ->limit(50)
                    ->get()
                    ->mapWithKeys(fn (MarketProduct $mp) => [$mp->getKey() => static::labelProduk($mp)])
                    ->all())
                ->getOptionLabelUsing(fn ($value) => ($mp = MarketProduct::with(['product', 'market'])->find($value))
                    ? static::labelProduk($mp)
                    : null)
                ->helperText('Diskon berlaku untuk produk di satu pasar. Produk yang sama di pasar lain punya diskon sendiri.')
                ->columnSpanFull(),

            Grid::make(2)->schema([
                Select::make('type')
                    ->label('Jenis potongan')
                    ->options([
                        ProductDiscount::TYPE_FIXED => 'Nominal (Rp)',
                        ProductDiscount::TYPE_PERCENT => 'Persen (%)',
                    ])
                    ->default(ProductDiscount::TYPE_FIXED)
                    ->required()
                    ->live(),

                TextInput::make('value')
                    ->label('Besar potongan')
                    ->required()
                    ->numeric()
                    ->minValue(1)
                    ->maxValue(fn (Get $get) => $get('type') === ProductDiscount::TYPE_PERCENT ? 100 : null)
                    ->prefix(fn (Get $get) => $get('type') === ProductDiscount::TYPE_FIXED ? 'Rp' : null)
                    ->suffix(fn (Get $get) => $get('type') === ProductDiscount::TYPE_PERCENT ? '%' : null)
                    ->helperText('Potongan per satuan produk (per kg, per ekor, dst).')
                    ->rules([
                        fn (Get $get): Closure => function (string $attribute, $value, Closure $fail) use ($get) {
                            if ($get('type') !== ProductDiscount::TYPE_FIXED) {
                                return;
                            }

                            $mp = MarketProduct::find($get('market_product_id'));

                            if ($mp && (float) $value >= (float) $mp->price) {
                                $fail('Potongan harus lebih kecil dari harga produk ('.static::rupiah($mp->price).').');
                            }
                        },
                    ]),
            ]),

            Grid::make(2)->schema([
                DateTimePicker::make('starts_at')
                    ->label('Mulai')
                    ->required()
                    ->seconds(false)
                    ->native(false)
                    ->displayFormat('d/m/Y H:i')
                    ->default(now()),

                DateTimePicker::make('ends_at')
                    ->label('Berakhir')
                    ->required()
                    ->seconds(false)
                    ->native(false)
                    ->displayFormat('d/m/Y H:i')
                    ->after('starts_at')
                    ->rules([
                        fn (Get $get, ?Model $record): Closure => function (string $attribute, $value, Closure $fail) use ($get, $record) {
                            if (! $get('is_active') || ! $get('market_product_id') || ! $get('starts_at')) {
                                return;
                            }

                            if (DiskonProdukService::adaTumpangTindih(
                                (int) $get('market_product_id'),
                                Carbon::parse($get('starts_at')),
                                Carbon::parse($value),
                                $record?->getKey(),
                            )) {
                                $fail('Periode bentrok dengan diskon aktif lain di produk ini. Ubah periodenya atau nonaktifkan diskon yang lama.');
                            }
                        },
                    ]),
            ]),

            Toggle::make('is_active')
                ->label('Aktif')
                ->default(true)
                ->live()
                ->helperText('Diskon nonaktif tidak tampil di katalog. Pesanan lama tetap menyimpan harga saat checkout.'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['marketProduct.product', 'marketProduct.market']))
            ->defaultSort('starts_at', 'desc')
            ->columns([
                TextColumn::make('marketProduct.product.name')->label('Produk')->searchable()->wrap(),
                TextColumn::make('marketProduct.market.name')->label('Pasar')->searchable(),
                TextColumn::make('harga_normal')
                    ->label('Harga normal')
                    ->state(fn (ProductDiscount $record) => static::rupiah($record->marketProduct->price)),
                TextColumn::make('nilai')
                    ->label('Potongan')
                    ->state(fn (ProductDiscount $record) => $record->type === ProductDiscount::TYPE_PERCENT
                        ? static::angka($record->value).'%'
                        : static::rupiah($record->value)),
                TextColumn::make('harga_akhir')
                    ->label('Harga setelah diskon')
                    ->state(function (ProductDiscount $record) {
                        $harga = (float) $record->marketProduct->price;

                        return static::rupiah($harga - $record->hitungPotongan($harga));
                    }),
                TextColumn::make('periode')
                    ->label('Periode')
                    ->state(fn (ProductDiscount $record) => $record->starts_at->format('d/m/Y H:i')
                        .' - '.$record->ends_at->format('d/m/Y H:i'))
                    ->sortable(query: fn (Builder $query, string $direction) => $query->orderBy('starts_at', $direction)),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->state(fn (ProductDiscount $record) => static::statusDiskon($record))
                    ->color(fn (string $state) => match ($state) {
                        'Berlaku' => 'success',
                        'Terjadwal' => 'info',
                        'Nonaktif' => 'danger',
                        default => 'gray',
                    }),
            ])
            ->filters([
                SelectFilter::make('market_id')
                    ->label('Pasar')
                    ->options(fn () => Market::orderBy('name')->pluck('name', 'market_id')->all())
                    ->query(fn (Builder $query, array $data) => $query->when(
                        $data['value'] ?? null,
                        fn (Builder $q, $marketId) => $q->whereHas('marketProduct', fn (Builder $mp) => $mp->where('market_id', $marketId)),
                    )),

                SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'berlaku' => 'Berlaku sekarang',
                        'terjadwal' => 'Terjadwal',
                        'berakhir' => 'Berakhir',
                        'nonaktif' => 'Nonaktif',
                    ])
                    ->query(fn (Builder $query, array $data) => match ($data['value'] ?? null) {
                        'berlaku' => $query->berlaku(),
                        'terjadwal' => $query->where('is_active', true)->where('starts_at', '>', now()),
                        'berakhir' => $query->where('is_active', true)->where('ends_at', '<', now()),
                        'nonaktif' => $query->where('is_active', false),
                        default => $query,
                    }),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()
                    ->disabled(fn (ProductDiscount $record) => $record->orderItems()->exists())
                    ->tooltip(fn (ProductDiscount $record) => $record->orderItems()->exists()
                        ? 'Sudah dipakai di pesanan. Nonaktifkan saja.'
                        : null)
                    ->before(function (DeleteAction $action, ProductDiscount $record) {
                        if ($record->orderItems()->exists()) {
                            Notification::make()
                                ->title('Diskon tidak bisa dihapus')
                                ->body('Sudah dipakai di pesanan. Nonaktifkan saja.')
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
            'index' => ManageDiskonProduk::route('/'),
        ];
    }

    protected static function statusDiskon(ProductDiscount $diskon): string
    {
        if (! $diskon->is_active) {
            return 'Nonaktif';
        }

        if (now()->lt($diskon->starts_at)) {
            return 'Terjadwal';
        }

        return now()->gt($diskon->ends_at) ? 'Berakhir' : 'Berlaku';
    }

    protected static function labelProduk(MarketProduct $mp): string
    {
        return $mp->product->name.' - '.$mp->market->name.' ('.static::rupiah($mp->price).')';
    }

    protected static function rupiah($nilai): string
    {
        return 'Rp'.number_format((float) $nilai, 0, ',', '.');
    }

    protected static function angka($nilai): string
    {
        return rtrim(rtrim(number_format((float) $nilai, 2, ',', '.'), '0'), ',');
    }
}
