<?php

namespace App\Filament\Resources\Drivers\RiwayatSanksi\Widgets;

use App\Filament\Resources\Drivers\RiwayatSanksi\RiwayatSanksiResource;
use App\Models\DriverProfile;
use App\Models\DriverViolation;
use App\Models\ViolationType;
use App\Services\DriverSanctionService;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;

class LaporanPelanggaranTable extends TableWidget
{
    protected static ?string $heading = 'Laporan Pelanggaran Driver';

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => DriverViolation::query()->with([
                'driverProfile.user',
                'violationType',
                'reporter',
                'handler',
                'revoker',
            ]))
            ->defaultSort('driver_violation_id', 'desc')
            ->columns([
                TextColumn::make('created_at')
                    ->label('Dilaporkan')
                    ->dateTime('d M Y H:i')
                    ->sortable(),

                TextColumn::make('driverProfile.user.name')
                    ->label('Driver')
                    ->searchable()
                    ->description(fn (DriverViolation $record) => 'ID Driver: '.$record->driver_profile_id),

                TextColumn::make('violationType.name')
                    ->label('Pelanggaran')
                    ->searchable()
                    ->wrap(),

                TextColumn::make('source')
                    ->label('Sumber')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => $state === DriverViolation::SOURCE_CUSTOMER ? 'Customer' : 'Admin')
                    ->color(fn (string $state) => $state === DriverViolation::SOURCE_CUSTOMER ? 'info' : 'gray'),

                TextColumn::make('reporter.name')->label('Pelapor')->placeholder('-'),

                TextColumn::make('points')
                    ->label('Poin')
                    ->placeholder('-')
                    ->suffix(' poin'),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => static::labelStatus($state))
                    ->color(fn (string $state) => match ($state) {
                        DriverViolation::STATUS_REPORTED => 'warning',
                        DriverViolation::STATUS_VALIDATED => 'success',
                        DriverViolation::STATUS_REJECTED => 'danger',
                        default => 'gray',
                    }),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        DriverViolation::STATUS_REPORTED => 'Menunggu validasi',
                        DriverViolation::STATUS_VALIDATED => 'Divalidasi',
                        DriverViolation::STATUS_REJECTED => 'Ditolak',
                        DriverViolation::STATUS_REVOKED => 'Dibatalkan',
                    ])
                    ->default(DriverViolation::STATUS_REPORTED),

                SelectFilter::make('source')
                    ->label('Sumber')
                    ->options([
                        DriverViolation::SOURCE_CUSTOMER => 'Customer',
                        DriverViolation::SOURCE_ADMIN => 'Admin',
                    ]),

                SelectFilter::make('violation_type_id')
                    ->label('Jenis pelanggaran')
                    ->options(fn () => ViolationType::orderBy('name')->pluck('name', 'violation_type_id')->all()),

                SelectFilter::make('driver_profile_id')
                    ->label('Driver')
                    ->options(fn () => RiwayatSanksiResource::opsiDriver(true))
                    ->searchable(),
            ])
            ->headerActions([
                Action::make('laporAdmin')
                    ->label('Input Laporan')
                    ->icon('heroicon-o-plus')
                    ->modalHeading('Input laporan pelanggaran (admin)')
                    ->schema([
                        Select::make('driver_profile_id')
                            ->label('Driver')
                            ->options(fn () => RiwayatSanksiResource::opsiDriver())
                            ->searchable()
                            ->required(),

                        Select::make('violation_type_id')
                            ->label('Jenis pelanggaran')
                            ->options(fn () => ViolationType::active()->orderBy('name')->pluck('name', 'violation_type_id')->all())
                            ->searchable()
                            ->required(),

                        Textarea::make('deskripsi')->label('Deskripsi kejadian')->required()->rows(3),

                        FileUpload::make('bukti')
                            ->label('Bukti (opsional)')
                            ->disk('public')
                            ->directory('bukti-pelanggaran')
                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'application/pdf'])
                            ->maxSize(5120),
                    ])
                    ->action(fn (array $data) => RiwayatSanksiResource::jalankan(
                        fn () => DriverSanctionService::laporkanOlehAdmin(
                            Auth::user(),
                            DriverProfile::findOrFail($data['driver_profile_id']),
                            ViolationType::findOrFail($data['violation_type_id']),
                            $data['deskripsi'],
                            null,
                            $data['bukti'] ?? null,
                        ),
                        'Laporan dicatat, menunggu validasi',
                    )),
            ])
            ->recordActions([
                Action::make('detail')
                    ->label('Detail')
                    ->icon('heroicon-o-eye')
                    ->color('gray')
                    ->modalHeading(fn (DriverViolation $record) => 'Laporan #'.$record->getKey())
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Tutup')
                    ->modalContent(fn (DriverViolation $record) => RiwayatSanksiResource::htmlDetail([
                        'Driver' => $record->driverProfile?->user?->name.' (ID '.$record->driver_profile_id.')',
                        'Pelanggaran' => $record->violationType?->name,
                        'Sumber' => $record->source === DriverViolation::SOURCE_CUSTOMER ? 'Customer' : 'Admin',
                        'Pelapor' => $record->reporter?->name,
                        'Pengantaran' => $record->delivery_order_id ? '#'.$record->delivery_order_id : null,
                        'Deskripsi' => $record->description,
                        'Bukti' => $record->evidence_photo
                            ? new HtmlString('<a href="'.e(static::urlBukti($record->evidence_photo)).'" target="_blank" rel="noopener" style="color:var(--primary-600);text-decoration:underline">Buka bukti</a>')
                            : null,
                        'Status' => static::labelStatus($record->status),
                        'Poin' => $record->points,
                        'Poin berlaku sampai' => RiwayatSanksiResource::tanggal($record->points_expires_at),
                        'Diproses oleh' => $record->handler?->name,
                        'Diproses pada' => RiwayatSanksiResource::tanggal($record->handled_at),
                        'Alasan penolakan' => $record->handle_note,
                        'Dibatalkan oleh' => $record->revoker?->name,
                        'Dibatalkan pada' => RiwayatSanksiResource::tanggal($record->revoked_at),
                        'Alasan pembatalan' => $record->revoke_note,
                    ])),

                Action::make('validasi')
                    ->label('Validasi')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (DriverViolation $record) => $record->status === DriverViolation::STATUS_REPORTED)
                    ->modalHeading('Validasi laporan')
                    ->modalDescription(fn (DriverViolation $record) => $record->violationType->name
                        .': poin harus di antara '.$record->violationType->min_points
                        .' dan '.$record->violationType->max_points.'.')
                    ->fillForm(fn (DriverViolation $record) => ['poin' => $record->violationType->default_points])
                    ->schema([
                        TextInput::make('poin')->label('Poin diberikan')->numeric()->integer()->minValue(1)->required(),
                    ])
                    ->action(fn (DriverViolation $record, array $data) => RiwayatSanksiResource::jalankan(
                        fn () => DriverSanctionService::validasiLaporan(Auth::user(), $record, (int) $data['poin']),
                        'Laporan divalidasi, poin diberikan',
                    )),

                Action::make('tolak')
                    ->label('Tolak')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (DriverViolation $record) => $record->status === DriverViolation::STATUS_REPORTED)
                    ->modalHeading('Tolak laporan')
                    ->schema([
                        Textarea::make('catatan')->label('Alasan penolakan')->required()->rows(3),
                    ])
                    ->action(fn (DriverViolation $record, array $data) => RiwayatSanksiResource::jalankan(
                        fn () => DriverSanctionService::tolakLaporan(Auth::user(), $record, $data['catatan']),
                        'Laporan ditolak',
                    )),

                Action::make('batalkan')
                    ->label('Batalkan Validasi')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('warning')
                    ->visible(fn (DriverViolation $record) => $record->status === DriverViolation::STATUS_VALIDATED)
                    ->modalHeading('Batalkan validasi')
                    ->modalDescription('Poin laporan ini dihapus dan sanksi yang dipicunya ikut dicabut. Poin dan sanksi dari laporan lain tidak berubah.')
                    ->schema([
                        Textarea::make('catatan')->label('Alasan pembatalan')->required()->rows(3),
                    ])
                    ->action(fn (DriverViolation $record, array $data) => RiwayatSanksiResource::jalankan(
                        fn () => DriverSanctionService::batalkanValidasi(Auth::user(), $record, $data['catatan']),
                        'Validasi dibatalkan',
                    )),
            ]);
    }

    /** URL publik file bukti di disk `public`. */
    protected static function urlBukti(string $path): string
    {
        /** @var FilesystemAdapter $disk */
        $disk = Storage::disk('public');

        return $disk->url($path);
    }

    public static function labelStatus(string $status): string
    {
        return match ($status) {
            DriverViolation::STATUS_REPORTED => 'Menunggu',
            DriverViolation::STATUS_VALIDATED => 'Divalidasi',
            DriverViolation::STATUS_REJECTED => 'Ditolak',
            DriverViolation::STATUS_REVOKED => 'Dibatalkan',
            default => $status,
        };
    }
}
