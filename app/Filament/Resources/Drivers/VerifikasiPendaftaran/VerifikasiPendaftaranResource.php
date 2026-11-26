<?php

namespace App\Filament\Resources\Drivers\VerifikasiPendaftaran;

use App\Filament\Resources\Drivers\VerifikasiPendaftaran\Pages\ListVerifikasiPendaftaran;
use App\Filament\Resources\Drivers\VerifikasiPendaftaran\Pages\ViewVerifikasiPendaftaran;
use App\Filament\Resources\Drivers\VerifikasiPendaftaran\RelationManagers\PemeriksaanDokumenRelationManager;
use App\Models\DriverDocumentCheck;
use App\Models\DriverProfile;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class VerifikasiPendaftaranResource extends Resource
{
    protected static ?string $model = DriverProfile::class;

    protected static ?string $slug = 'driver/verifikasi-pendaftaran';

    protected static string|UnitEnum|null $navigationGroup = 'Driver';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Verifikasi Pendaftaran';

    protected static ?string $modelLabel = 'Pendaftar';

    protected static ?string $pluralModelLabel = 'Verifikasi Pendaftaran';

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

    public static function getRecordTitle(?Model $record): string|Htmlable|null
    {
        return $record?->user?->name;
    }

    /** Badge sidebar: pendaftar yang menunggu diperiksa. */
    public static function getNavigationBadge(): ?string
    {
        $jumlah = DriverProfile::where('approval_status', DriverProfile::APPROVAL_PENDING)->count();

        return $jumlah > 0 ? (string) $jumlah : null;
    }

    public static function getNavigationBadgeColor(): string|array|null
    {
        return 'warning';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Pendaftar menunggu diperiksa';
    }

    /** Semua yang belum disetujui. Yang sudah disetujui pindah ke Daftar Driver. */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('approval_status', '!=', DriverProfile::APPROVAL_APPROVED)
            ->with(['user', 'document', 'vehicle', 'reviewer'])
            ->withCount(['documentChecks as dokumen_valid' => fn (Builder $q) => $q->where('status', DriverDocumentCheck::STATUS_VALID)]);
    }

    public static function getRelations(): array
    {
        return [
            PemeriksaanDokumenRelationManager::class,
        ];
    }

    // ===== Helper =====

    public static function labelStatus(string $status): string
    {
        return match ($status) {
            DriverProfile::APPROVAL_PENDING => 'Menunggu',
            DriverProfile::APPROVAL_REVISION => 'Perlu perbaikan',
            DriverProfile::APPROVAL_REJECTED => 'Ditolak',
            DriverProfile::APPROVAL_APPROVED => 'Disetujui',
            default => $status,
        };
    }

    public static function warnaStatus(string $status): string
    {
        return match ($status) {
            DriverProfile::APPROVAL_PENDING => 'warning',
            DriverProfile::APPROVAL_REVISION => 'info',
            DriverProfile::APPROVAL_REJECTED => 'danger',
            DriverProfile::APPROVAL_APPROVED => 'success',
            default => 'gray',
        };
    }

    /** Jumlah dokumen yang sudah diunggah dari 5 yang dibutuhkan. */
    public static function jumlahDokumenTerunggah(DriverProfile $driver): int
    {
        return collect([
            $driver->document?->ktp_photo,
            $driver->document?->selfie_photo,
            $driver->document?->sim_photo,
            $driver->vehicle?->vehicle_photo,
            $driver->vehicle?->stnk_photo,
        ])->filter()->count();
    }

    // ===== Detail =====

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Status pendaftaran')
                ->columns(4)
                ->schema([
                    TextEntry::make('approval_status')
                        ->label('Status')
                        ->formatStateUsing(fn (string $state) => static::labelStatus($state))
                        ->badge()
                        ->color(fn (string $state) => static::warnaStatus($state)),

                    TextEntry::make('created_at')->label('Tanggal daftar')->dateTime('d M Y H:i'),

                    TextEntry::make('reviewed_at')
                        ->label('Terakhir ditinjau')
                        ->dateTime('d M Y H:i')
                        ->placeholder('Belum ditinjau')
                        ->helperText(fn (DriverProfile $record) => $record->reviewer ? 'oleh '.$record->reviewer->name : null),

                    TextEntry::make('dokumen')
                        ->label('Dokumen valid')
                        ->state(fn (DriverProfile $record) => ($record->dokumen_valid ?? 0).' dari '.count(DriverDocumentCheck::ITEMS)),

                    TextEntry::make('rejection_reason')
                        ->label('Alasan penolakan')
                        ->columnSpanFull()
                        ->visible(fn (DriverProfile $record) => $record->approval_status === DriverProfile::APPROVAL_REJECTED),
                ]),

            Section::make('Data pendaftar')
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
                    TextEntry::make('document.nik')->label('NIK')->placeholder('Belum diisi'),
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
                ]),
        ]);
    }

    // ===== Tabel =====

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'asc')
            ->columns([
                TextColumn::make('user.name')
                    ->label('Pendaftar')
                    ->searchable()
                    ->description(fn (DriverProfile $record) => $record->user?->email),

                TextColumn::make('user.phone')->label('Telepon')->searchable()->placeholder('-'),

                TextColumn::make('created_at')
                    ->label('Tanggal daftar')
                    ->dateTime('d M Y H:i')
                    ->sortable(),

                TextColumn::make('kelengkapan')
                    ->label('Kelengkapan')
                    ->state(fn (DriverProfile $record) => static::jumlahDokumenTerunggah($record).'/'.count(DriverDocumentCheck::ITEMS).' diunggah')
                    ->badge()
                    ->color(fn (DriverProfile $record) => static::jumlahDokumenTerunggah($record) === count(DriverDocumentCheck::ITEMS) ? 'success' : 'warning'),

                TextColumn::make('dokumen_valid')
                    ->label('Validasi')
                    ->state(fn (DriverProfile $record) => ($record->dokumen_valid ?? 0).'/'.count(DriverDocumentCheck::ITEMS).' valid')
                    ->badge()
                    ->color(fn (DriverProfile $record) => ($record->dokumen_valid ?? 0) === count(DriverDocumentCheck::ITEMS) ? 'success' : 'gray'),

                TextColumn::make('approval_status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => static::labelStatus($state))
                    ->color(fn (string $state) => static::warnaStatus($state)),
            ])
            ->recordActions([
                ViewAction::make()->label('Periksa')->icon('heroicon-o-clipboard-document-check'),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListVerifikasiPendaftaran::route('/'),
            'view' => ViewVerifikasiPendaftaran::route('/{record}'),
        ];
    }
}
