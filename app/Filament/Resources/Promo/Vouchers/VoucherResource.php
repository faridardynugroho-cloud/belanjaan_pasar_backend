<?php

namespace App\Filament\Resources\Promo\Vouchers;

use App\Filament\Resources\Promo\Vouchers\Pages\ManageVouchers;
use App\Models\Voucher;
use App\Models\VoucherUsage;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class VoucherResource extends Resource
{
    protected static ?string $model = Voucher::class;

    protected static ?string $slug = 'promo/voucher';

    protected static string | UnitEnum | null $navigationGroup = 'Promo';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Voucher';

    protected static ?string $modelLabel = 'Voucher';

    protected static ?string $pluralModelLabel = 'Voucher';

    protected static ?string $recordTitleAttribute = 'code';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Grid::make(2)->schema([
                TextInput::make('code')
                    ->label('Kode voucher')
                    ->required()
                    ->maxLength(50)
                    ->regex('/^[A-Za-z0-9_-]+$/')
                    ->validationMessages(['regex' => 'Kode hanya boleh huruf, angka, strip (-), dan garis bawah (_).'])
                    ->unique(ignoreRecord: true)
                    ->dehydrateStateUsing(fn(?string $state) => strtoupper(trim((string) $state)))
                    ->extraInputAttributes(['style' => 'text-transform: uppercase'])
                    ->helperText('Otomatis huruf besar, mis. HEMAT10.'),

                TextInput::make('name')
                    ->label('Nama voucher')
                    ->required()
                    ->maxLength(255),
            ]),

            Textarea::make('description')
                ->label('Deskripsi')
                ->rows(2)
                ->helperText('Syarat singkat yang tampil ke customer.'),

            Grid::make(2)->schema([
                Select::make('applies_to')
                    ->label('Potongan untuk')
                    ->options([
                        Voucher::APPLIES_SUBTOTAL => 'Belanja (subtotal)',
                        Voucher::APPLIES_DELIVERY_FEE => 'Ongkir (gratis ongkir)',
                    ])
                    ->default(Voucher::APPLIES_SUBTOTAL)
                    ->required()
                    ->helperText('Pesanan Ngebelanjain hanya bisa memakai voucher ongkir.'),

                Select::make('type')
                    ->label('Jenis potongan')
                    ->options([
                        Voucher::TYPE_PERCENT => 'Persen (%)',
                        Voucher::TYPE_FIXED => 'Nominal (Rp)',
                    ])
                    ->default(Voucher::TYPE_FIXED)
                    ->required()
                    ->live(),
            ]),

            Grid::make(3)->schema([
                TextInput::make('value')
                    ->label('Besar potongan')
                    ->required()
                    ->numeric()
                    ->minValue(1)
                    ->maxValue(fn(Get $get) => $get('type') === Voucher::TYPE_PERCENT ? 100 : null)
                    ->prefix(fn(Get $get) => $get('type') === Voucher::TYPE_FIXED ? 'Rp' : null)
                    ->suffix(fn(Get $get) => $get('type') === Voucher::TYPE_PERCENT ? '%' : null)
                    ->helperText('Gratis ongkir penuh: pilih Ongkir, Persen, 100.'),

                TextInput::make('max_discount')
                    ->label('Maks. potongan')
                    ->numeric()
                    ->minValue(1)
                    ->prefix('Rp')
                    ->helperText('Hanya dipakai untuk jenis persen. Kosongkan kalau tanpa batas.'),

                TextInput::make('min_purchase')
                    ->label('Minimal belanja')
                    ->required()
                    ->numeric()
                    ->minValue(0)
                    ->default(0)
                    ->prefix('Rp')
                    ->helperText('Dihitung dari subtotal setelah diskon produk.'),
            ]),

            Grid::make(2)->schema([
                TextInput::make('quota')
                    ->label('Kuota total')
                    ->numeric()
                    ->integer()
                    ->minValue(1)
                    ->helperText('Kosongkan kalau tanpa batas.'),

                TextInput::make('per_user_limit')
                    ->label('Batas per customer')
                    ->required()
                    ->numeric()
                    ->integer()
                    ->minValue(1)
                    ->default(1),
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
                    ->after('starts_at'),
            ]),

            Toggle::make('is_active')
                ->label('Aktif')
                ->default(true)
                ->helperText('Voucher nonaktif tidak bisa dipakai lagi. Pesanan yang sudah memakainya tidak berubah.'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn(Builder $query) => $query->withCount([
                'usages as terpakai_count' => fn(Builder $q) => $q->where('status', VoucherUsage::STATUS_APPLIED),
                'usages as semua_pemakaian_count',
            ]))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('code')->label('Kode')->searchable()->copyable()->weight('bold'),
                TextColumn::make('name')->label('Nama')->searchable()->wrap(),
                TextColumn::make('applies_to')
                    ->label('Untuk')
                    ->badge()
                    ->formatStateUsing(fn(string $state) => $state === Voucher::APPLIES_SUBTOTAL ? 'Belanja' : 'Ongkir')
                    ->color(fn(string $state) => $state === Voucher::APPLIES_SUBTOTAL ? 'info' : 'warning'),
                TextColumn::make('nilai')
                    ->label('Potongan')
                    ->state(function (Voucher $record) {
                        if ($record->type === Voucher::TYPE_FIXED) {
                            return static::rupiah($record->value);
                        }

                        $teks = static::angka($record->value) . '%';

                        return $record->max_discount !== null
                            ? $teks . ' (maks ' . static::rupiah($record->max_discount) . ')'
                            : $teks;
                    }),
                TextColumn::make('min_purchase')
                    ->label('Min. belanja')
                    ->state(fn(Voucher $record) => (float) $record->min_purchase > 0 ? static::rupiah($record->min_purchase) : '-'),
                TextColumn::make('pemakaian')
                    ->label('Terpakai')
                    ->state(fn(Voucher $record) => $record->terpakai_count . ' / ' . ($record->quota ?? '∞')),
                TextColumn::make('periode')
                    ->label('Periode')
                    ->state(fn(Voucher $record) => $record->starts_at->format('d/m/Y H:i')
                        . ' - ' . $record->ends_at->format('d/m/Y H:i'))
                    ->sortable(query: fn(Builder $query, string $direction) => $query->orderBy('starts_at', $direction)),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->state(fn(Voucher $record) => static::statusVoucher($record))
                    ->color(fn(string $state) => match ($state) {
                        'Berlaku' => 'success',
                        'Terjadwal' => 'info',
                        'Habis' => 'warning',
                        'Nonaktif' => 'danger',
                        default => 'gray',
                    }),
                ToggleColumn::make('is_active')->label('Aktif'),
            ])
            ->filters([
                SelectFilter::make('applies_to')
                    ->label('Untuk')
                    ->options([
                        Voucher::APPLIES_SUBTOTAL => 'Belanja',
                        Voucher::APPLIES_DELIVERY_FEE => 'Ongkir',
                    ]),

                TernaryFilter::make('is_active')
                    ->label('Status aktif')
                    ->placeholder('Semua')
                    ->trueLabel('Aktif')
                    ->falseLabel('Nonaktif'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()
                    ->disabled(fn(Voucher $record) => $record->semua_pemakaian_count > 0)
                    ->tooltip(fn(Voucher $record) => $record->semua_pemakaian_count > 0
                        ? 'Sudah pernah dipakai di pesanan. Nonaktifkan saja.'
                        : null)
                    ->before(function (DeleteAction $action, Voucher $record) {
                        if ($record->usages()->exists()) {
                            Notification::make()
                                ->title('Voucher tidak bisa dihapus')
                                ->body('Sudah pernah dipakai di pesanan. Nonaktifkan saja.')
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
            'index' => ManageVouchers::route('/'),
        ];
    }

    protected static function statusVoucher(Voucher $voucher): string
    {
        if (! $voucher->is_active) {
            return 'Nonaktif';
        }

        if (now()->lt($voucher->starts_at)) {
            return 'Terjadwal';
        }

        if (now()->gt($voucher->ends_at)) {
            return 'Berakhir';
        }

        if ($voucher->quota !== null && $voucher->terpakai_count >= $voucher->quota) {
            return 'Habis';
        }

        return 'Berlaku';
    }

    protected static function rupiah($nilai): string
    {
        return 'Rp' . number_format((float) $nilai, 0, ',', '.');
    }

    protected static function angka($nilai): string
    {
        return rtrim(rtrim(number_format((float) $nilai, 2, ',', '.'), '0'), ',');
    }
}