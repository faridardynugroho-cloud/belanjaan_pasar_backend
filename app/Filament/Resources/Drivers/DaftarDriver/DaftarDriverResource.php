<?php

namespace App\Filament\Resources\Drivers\DaftarDriver;

use App\Filament\Resources\Drivers\DaftarDriver\Pages\EditDaftarDriver;
use App\Filament\Resources\Drivers\DaftarDriver\Pages\ListDaftarDriver;
use App\Filament\Resources\Drivers\DaftarDriver\Pages\ViewDaftarDriver;
use App\Filament\Resources\Drivers\RiwayatSanksi\RiwayatSanksiResource;
use App\Models\Delivery;
use App\Models\DriverProfile;
use App\Models\DriverSanction;
use App\Services\DriverSanctionService;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use UnitEnum;

class DaftarDriverResource extends Resource
{
    protected static ?string $model = DriverProfile::class;

    protected static ?string $slug = 'driver/daftar-driver';

    protected static string|UnitEnum|null $navigationGroup = 'Driver';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Daftar Driver';

    protected static ?string $modelLabel = 'Driver';

    protected static ?string $pluralModelLabel = 'Daftar Driver';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function getRecordTitle(?Model $record): string|Htmlable|null
    {
        return $record?->user?->name;
    }

    /** Hanya driver yang sudah disetujui. Calon driver ada di menu Verifikasi Pendaftaran. */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('approval_status', 'approved')
            ->with(['user', 'vehicle', 'document', 'currentSanction'])
            ->withCount(['deliveries as total_pengiriman' => fn (Builder $q) => $q->whereNotNull('completed_at')])
            ->withExists(['deliveries as sedang_antar' => fn (Builder $q) => $q
                ->whereNull('completed_at')
                ->whereNull('cancelled_at')
                ->whereNotNull('started_at')])
            ->withSum(['violations as poin_aktif' => fn (Builder $q) => $q->withActivePoints()], 'points');
    }

    // ===== Status turunan =====

    /** @return array{0: string, 1: string, 2: ?string} label, warna, keterangan */
    public static function statusAkun(DriverProfile $driver): array
    {
        $sanksi = $driver->currentSanction;

        if (! $sanksi) {
            return ['Aktif', 'success', null];
        }

        return match (true) {
            $sanksi->status === DriverSanction::STATUS_WAITING => ['Suspend terjadwal', 'info', 'Mulai setelah pesanan selesai'],
            $sanksi->type === DriverSanction::TYPE_TERMINATION => ['Putus mitra', 'danger', null],
            $sanksi->type === DriverSanction::TYPE_PERMANENT => ['Suspend permanen', 'danger', null],
            default => ['Suspend', 'warning', 'Sampai '.RiwayatSanksiResource::tanggal($sanksi->expires_at)],
        };
    }

    public static function statusOperasional(DriverProfile $driver): string
    {
        $sedangAntar = $driver->sedang_antar
            ?? $driver->deliveries()->withStatus(Delivery::STATUS_ON_THE_WAY)->exists();

        return match (true) {
            (bool) $sedangAntar => 'On Delivery',
            (bool) $driver->is_available => 'Online',
            default => 'Offline',
        };
    }

    public static function warnaOperasional(string $status): string
    {
        return match ($status) {
            'On Delivery' => 'info',
            'Online' => 'success',
            default => 'gray',
        };
    }

    public static function labelRating(DriverProfile $driver): string
    {
        return $driver->rating_count > 0
            ? number_format((float) $driver->rating_avg, 1).' ★ ('.$driver->rating_count.')'
            : 'Belum ada rating';
    }

    // ===== Aksi sanksi (dipakai di tabel dan halaman detail) =====

    /** @return array<Action> */
    public static function aksiSanksi(): array
    {
        $sedangPutusMitra = fn (DriverProfile $r) => $r->currentSanction?->type === DriverSanction::TYPE_TERMINATION;

        return [
            Action::make('suspend')
                ->label('Suspend Driver')
                ->icon('heroicon-o-no-symbol')
                ->color('warning')
                ->visible(fn (DriverProfile $record) => ! $sedangPutusMitra($record))
                ->modalHeading(fn (DriverProfile $record) => 'Suspend '.$record->user?->name)
                ->modalDescription('Driver tidak akan ditawari order selama disuspend. Kalau masih membawa pesanan, suspend mulai setelah pesanan selesai.')
                ->schema([
                    Select::make('tipe')
                        ->label('Jenis suspend')
                        ->options([
                            DriverSanction::TYPE_TEMPORARY => 'Sementara',
                            DriverSanction::TYPE_PERMANENT => 'Permanen',
                        ])
                        ->default(DriverSanction::TYPE_TEMPORARY)
                        ->live()
                        ->required(),

                    Grid::make(2)
                        ->visible(fn (Get $get) => $get('tipe') === DriverSanction::TYPE_TEMPORARY)
                        ->schema([
                            TextInput::make('durasi')
                                ->label('Durasi')
                                ->numeric()
                                ->integer()
                                ->minValue(1)
                                ->required(fn (Get $get) => $get('tipe') === DriverSanction::TYPE_TEMPORARY)
                                ->helperText('Maksimal 60 hari.'),

                            Select::make('satuan')
                                ->label('Satuan')
                                ->options(['menit' => 'Menit', 'jam' => 'Jam', 'hari' => 'Hari'])
                                ->default('jam')
                                ->required(fn (Get $get) => $get('tipe') === DriverSanction::TYPE_TEMPORARY),
                        ]),

                    Textarea::make('alasan')->label('Alasan')->required()->rows(3),
                    Textarea::make('catatan')->label('Catatan internal')->rows(2),
                ])
                ->action(function (DriverProfile $record, array $data) {
                    $menit = null;

                    if ($data['tipe'] === DriverSanction::TYPE_TEMPORARY) {
                        $pengali = ['menit' => 1, 'jam' => 60, 'hari' => 1440][$data['satuan']] ?? 60;
                        $menit = (int) $data['durasi'] * $pengali;
                    }

                    RiwayatSanksiResource::jalankan(
                        fn () => DriverSanctionService::jatuhkanManual(
                            Auth::user(),
                            $record,
                            $data['tipe'],
                            $menit,
                            $data['alasan'],
                            $data['catatan'] ?? null,
                        ),
                        fn (DriverSanction $sanksi) => $sanksi->started_at
                            ? 'Driver disuspend'
                            : 'Suspend terjadwal, mulai setelah pesanan driver selesai',
                    );
                }),

            Action::make('aktifkanKembali')
                ->label('Aktifkan Kembali')
                ->icon('heroicon-o-arrow-uturn-left')
                ->color('success')
                ->visible(fn (DriverProfile $record) => $record->currentSanction !== null)
                ->modalHeading(fn (DriverProfile $record) => 'Aktifkan kembali '.$record->user?->name)
                ->modalDescription(fn (DriverProfile $record) => 'Sanksi yang dicabut: '.static::ringkasSanksi($record->currentSanction))
                ->schema([
                    Textarea::make('catatan')->label('Catatan pencabutan')->required()->rows(3),
                ])
                ->action(fn (DriverProfile $record, array $data) => RiwayatSanksiResource::jalankan(
                    fn () => DriverSanctionService::cabutSanksi(Auth::user(), $record->currentSanction, $data['catatan']),
                    'Driver diaktifkan kembali',
                )),

            Action::make('putusMitra')
                ->label('Putus Mitra')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->visible(fn (DriverProfile $record) => ! $sedangPutusMitra($record))
                ->requiresConfirmation()
                ->modalHeading(fn (DriverProfile $record) => 'Putus mitra '.$record->user?->name.'?')
                ->modalDescription('Driver tidak bisa menerima order lagi. Tindakan ini masih bisa dicabut lewat Aktifkan Kembali.')
                ->schema([
                    Textarea::make('alasan')->label('Alasan')->required()->rows(3),
                    Textarea::make('catatan')->label('Catatan internal')->rows(2),
                ])
                ->action(fn (DriverProfile $record, array $data) => RiwayatSanksiResource::jalankan(
                    fn () => DriverSanctionService::jatuhkanManual(
                        Auth::user(),
                        $record,
                        DriverSanction::TYPE_TERMINATION,
                        null,
                        $data['alasan'],
                        $data['catatan'] ?? null,
                    ),
                    'Driver diputus mitra',
                )),
        ];
    }

    protected static function ringkasSanksi(?DriverSanction $sanksi): string
    {
        if (! $sanksi) {
            return '-';
        }

        $jenis = match ($sanksi->type) {
            DriverSanction::TYPE_TEMPORARY => 'Suspend '.RiwayatSanksiResource::formatDurasi($sanksi->duration_minutes),
            DriverSanction::TYPE_PERMANENT => 'Suspend permanen',
            default => 'Putus mitra',
        };

        return $jenis.' ('.RiwayatSanksiResource::labelStatus($sanksi).')';
    }

    // ===== Form edit =====

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Data pribadi')
                ->relationship('user')
                ->columns(2)
                ->schema([
                    TextInput::make('name')->label('Nama lengkap')->required()->maxLength(255),

                    TextInput::make('email')
                        ->label('Email')
                        ->email()
                        ->required()
                        ->maxLength(255)
                        ->rules([
                            fn ($record) => Rule::unique('users', 'email')->ignore($record?->user_id, 'user_id'),
                        ]),

                    TextInput::make('phone')->label('Telepon')->tel()->maxLength(20),

                    Select::make('gender')
                        ->label('Jenis kelamin')
                        ->options(['male' => 'Laki-laki', 'female' => 'Perempuan'])
                        ->native(false),

                    DatePicker::make('birth_date')->label('Tanggal lahir')->maxDate(now()),
                ]),

            Section::make('Domisili')
                ->schema([
                    Textarea::make('domicile_address')->label('Alamat domisili')->required()->rows(3),
                ]),

            Section::make('Kendaraan')
                ->relationship('vehicle')
                ->columns(3)
                ->visible(fn (?DriverProfile $record) => $record?->vehicle !== null)
                ->schema([
                    TextInput::make('vehicle_type')->label('Jenis')->required()->maxLength(50),
                    TextInput::make('vehicle_brand')->label('Merek')->required()->maxLength(50),
                    TextInput::make('vehicle_model')->label('Model')->required()->maxLength(50),
                    TextInput::make('vehicle_plate')->label('Plat nomor')->required()->maxLength(20),
                    TextInput::make('vehicle_color')->label('Warna')->required()->maxLength(30),
                    TextInput::make('production_year')
                        ->label('Tahun produksi')
                        ->required()
                        ->numeric()
                        ->integer()
                        ->minValue(1990)
                        ->maxValue((int) now()->addYear()->format('Y')),
                ]),
        ]);
    }

    // ===== Detail =====

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Status')
                ->columns(5)
                ->schema([
                    TextEntry::make('status_akun')
                        ->label('Status akun')
                        ->state(fn (DriverProfile $record) => static::statusAkun($record)[0])
                        ->badge()
                        ->color(fn (DriverProfile $record) => static::statusAkun($record)[1])
                        ->helperText(fn (DriverProfile $record) => static::statusAkun($record)[2]),

                    TextEntry::make('status_operasional')
                        ->label('Status operasional')
                        ->state(fn (DriverProfile $record) => static::statusOperasional($record))
                        ->badge()
                        ->color(fn (string $state) => static::warnaOperasional($state)),

                    TextEntry::make('rating')
                        ->label('Rating')
                        ->state(fn (DriverProfile $record) => static::labelRating($record)),

                    TextEntry::make('total_pengiriman')
                        ->label('Total pengiriman selesai')
                        ->state(fn (DriverProfile $record) => $record->total_pengiriman ?? 0),

                    TextEntry::make('poin_aktif')
                        ->label('Poin pelanggaran aktif')
                        ->state(fn (DriverProfile $record) => (int) ($record->poin_aktif ?? 0)),
                ]),

            Section::make('Informasi pribadi')
                ->columns(3)
                ->schema([
                    TextEntry::make('user.name')->label('Nama lengkap'),
                    TextEntry::make('user.email')->label('Email'),
                    TextEntry::make('user.phone')->label('Telepon')->placeholder('-'),
                    TextEntry::make('user.gender')
                        ->label('Jenis kelamin')
                        ->formatStateUsing(fn ($state) => match ($state) {
                            'male' => 'Laki-laki',
                            'female' => 'Perempuan',
                            default => '-',
                        }),
                    TextEntry::make('user.birth_date')->label('Tanggal lahir')->date('d M Y')->placeholder('-'),
                    TextEntry::make('reviewed_at')->label('Disetujui pada')->dateTime('d M Y H:i')->placeholder('-'),
                    TextEntry::make('domicile_address')->label('Alamat domisili')->columnSpanFull(),
                ]),

            Section::make('Kendaraan')
                ->columns(3)
                ->schema([
                    TextEntry::make('vehicle.vehicle_type')->label('Jenis')->placeholder('-'),
                    TextEntry::make('vehicle.vehicle_brand')->label('Merek')->placeholder('-'),
                    TextEntry::make('vehicle.vehicle_model')->label('Model')->placeholder('-'),
                    TextEntry::make('vehicle.vehicle_plate')->label('Plat nomor')->placeholder('-'),
                    TextEntry::make('vehicle.vehicle_color')->label('Warna')->placeholder('-'),
                    TextEntry::make('vehicle.production_year')->label('Tahun produksi')->placeholder('-'),
                    ImageEntry::make('vehicle.vehicle_photo')->label('Foto kendaraan')->disk('public')->height(160),
                    ImageEntry::make('vehicle.stnk_photo')->label('Foto STNK')->disk('public')->height(160),
                ]),

            Section::make('Dokumen')
                ->columns(3)
                ->schema([
                    TextEntry::make('document.nik')->label('NIK')->placeholder('-'),
                    TextEntry::make('document.sim_number')->label('Nomor SIM')->placeholder('-'),
                    TextEntry::make('document.sim_expired_at')
                        ->label('SIM berlaku sampai')
                        ->date('d M Y')
                        ->placeholder('-')
                        ->color(fn (DriverProfile $record) => $record->document?->sim_expired_at?->isPast() ? 'danger' : null)
                        ->helperText(fn (DriverProfile $record) => $record->document?->sim_expired_at?->isPast() ? 'SIM sudah kedaluwarsa' : null),
                    ImageEntry::make('document.ktp_photo')->label('Foto KTP')->disk('public')->height(160),
                    ImageEntry::make('document.selfie_photo')->label('Foto selfie')->disk('public')->height(160),
                    ImageEntry::make('document.sim_photo')->label('Foto SIM')->disk('public')->height(160),
                ]),
        ]);
    }

    // ===== Tabel =====

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('driver_profile_id', 'desc')
            ->columns([
                TextColumn::make('user.name')
                    ->label('Driver')
                    ->searchable()
                    ->description(fn (DriverProfile $record) => 'ID Driver: '.$record->driver_profile_id),

                TextColumn::make('user.phone')->label('Telepon')->searchable()->placeholder('-'),

                TextColumn::make('vehicle.vehicle_plate')
                    ->label('Kendaraan')
                    ->placeholder('-')
                    ->description(fn (DriverProfile $record) => $record->vehicle
                        ? $record->vehicle->vehicle_brand.' '.$record->vehicle->vehicle_model
                        : null),

                TextColumn::make('verifikasi')
                    ->label('Verifikasi')
                    ->state('Terverifikasi')
                    ->badge()
                    ->color('success'),

                TextColumn::make('status_akun')
                    ->label('Status akun')
                    ->state(fn (DriverProfile $record) => static::statusAkun($record)[0])
                    ->badge()
                    ->color(fn (DriverProfile $record) => static::statusAkun($record)[1])
                    ->description(fn (DriverProfile $record) => static::statusAkun($record)[2]),

                TextColumn::make('status_operasional')
                    ->label('Operasional')
                    ->state(fn (DriverProfile $record) => static::statusOperasional($record))
                    ->badge()
                    ->color(fn (string $state) => static::warnaOperasional($state)),

                TextColumn::make('rating_avg')
                    ->label('Rating')
                    ->state(fn (DriverProfile $record) => static::labelRating($record))
                    ->sortable(),

                TextColumn::make('total_pengiriman')
                    ->label('Pengiriman')
                    ->numeric()
                    ->suffix(' selesai')
                    ->sortable(query: fn (Builder $query, string $direction) => $query->orderBy('total_pengiriman', $direction)),

                TextColumn::make('poin_aktif')
                    ->label('Poin')
                    ->state(fn (DriverProfile $record) => (int) ($record->poin_aktif ?? 0))
                    ->badge()
                    ->color(fn (int $state) => $state > 0 ? 'warning' : 'gray')
                    ->sortable(query: fn (Builder $query, string $direction) => $query->orderBy('poin_aktif', $direction)),
            ])
            ->filters([
                SelectFilter::make('operasional')
                    ->label('Status operasional')
                    ->options(['online' => 'Online', 'offline' => 'Offline', 'on_delivery' => 'On Delivery'])
                    ->query(function (Builder $query, array $data) {
                        $sedangAntar = fn (Builder $q) => $q->whereNull('completed_at')->whereNull('cancelled_at')->whereNotNull('started_at');

                        return match ($data['value'] ?? null) {
                            'on_delivery' => $query->whereHas('deliveries', $sedangAntar),
                            'online' => $query->where('is_available', true)->whereDoesntHave('deliveries', $sedangAntar),
                            'offline' => $query->where('is_available', false)->whereDoesntHave('deliveries', $sedangAntar),
                            default => $query,
                        };
                    }),

                SelectFilter::make('akun')
                    ->label('Status akun')
                    ->options(['aktif' => 'Aktif', 'suspend' => 'Suspend', 'putus_mitra' => 'Putus mitra'])
                    ->query(fn (Builder $query, array $data) => match ($data['value'] ?? null) {
                        'aktif' => $query->whereDoesntHave('sanctions', fn (Builder $q) => $q->open()),
                        'suspend' => $query->whereHas('sanctions', fn (Builder $q) => $q->open()
                            ->whereIn('type', [DriverSanction::TYPE_TEMPORARY, DriverSanction::TYPE_PERMANENT])),
                        'putus_mitra' => $query->whereHas('sanctions', fn (Builder $q) => $q->open()
                            ->where('type', DriverSanction::TYPE_TERMINATION)),
                        default => $query,
                    }),
            ])
            ->recordActions([
                ViewAction::make()->label('Detail'),
                EditAction::make(),
                ActionGroup::make(static::aksiSanksi())
                    ->label('Sanksi')
                    ->icon('heroicon-m-shield-exclamation')
                    ->color('gray'),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDaftarDriver::route('/'),
            'view' => ViewDaftarDriver::route('/{record}'),
            'edit' => EditDaftarDriver::route('/{record}/edit'),
        ];
    }
}
