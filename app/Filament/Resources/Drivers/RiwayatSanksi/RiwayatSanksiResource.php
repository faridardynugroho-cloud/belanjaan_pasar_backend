<?php

namespace App\Filament\Resources\Drivers\RiwayatSanksi;

use App\Filament\Resources\Drivers\RiwayatSanksi\Pages\ListRiwayatSanksi;
use App\Filament\Resources\Drivers\RiwayatSanksi\Widgets\LaporanPelanggaranTable;
use App\Filament\Resources\Drivers\RiwayatSanksi\Widgets\RiwayatSanksiStats;
use App\Models\DriverProfile;
use App\Models\DriverSanction;
use App\Models\DriverViolation;
use App\Services\DriverSanctionService;
use Closure;
use DomainException;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\HtmlString;
use UnitEnum;

class RiwayatSanksiResource extends Resource
{
    protected static ?string $model = DriverSanction::class;

    protected static ?string $slug = 'driver/riwayat-sanksi';

    protected static string|UnitEnum|null $navigationGroup = 'Driver';

    protected static ?int $navigationSort = 4;

    protected static ?string $navigationLabel = 'Riwayat Sanksi';

    protected static ?string $modelLabel = 'Sanksi';

    protected static ?string $pluralModelLabel = 'Riwayat Sanksi';

    public static function canCreate(): bool
    {
        return false;
    }

    /** Badge sidebar: jumlah laporan pelanggaran yang menunggu validasi admin. */
    public static function getNavigationBadge(): ?string
    {
        $jumlah = DriverViolation::where('status', DriverViolation::STATUS_REPORTED)->count();

        return $jumlah > 0 ? (string) $jumlah : null;
    }

    public static function getNavigationBadgeColor(): string|array|null
    {
        return 'warning';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Laporan pelanggaran menunggu validasi';
    }

    public static function getWidgets(): array
    {
        return [
            RiwayatSanksiStats::class,
            LaporanPelanggaranTable::class,
        ];
    }

    // ===== Helper bersama (dipakai juga oleh halaman dan widget laporan) =====

    /**
     * Jalankan aksi service, tampilkan notifikasi sukses atau pesan aturan bisnis yang dilanggar.
     * $pesanSukses boleh berupa Closure yang menerima hasil aksi (mis. untuk pesan yang bergantung pada hasilnya).
     */
    public static function jalankan(Closure $aksi, string|Closure $pesanSukses): mixed
    {
        try {
            $hasil = $aksi();

            Notification::make()
                ->title($pesanSukses instanceof Closure ? $pesanSukses($hasil) : $pesanSukses)
                ->success()
                ->send();

            return $hasil;
        } catch (DomainException $e) {
            Notification::make()->title('Tidak bisa diproses')->body($e->getMessage())->danger()->send();

            return null;
        }
    }

    /** Daftar driver yang sudah disetujui untuk dropdown: [driver_profile_id => nama]. */
    public static function opsiDriver(bool $semua = false): array
    {
        return DriverProfile::query()
            ->join('users', 'users.user_id', '=', 'driver_profiles.user_id')
            ->when(! $semua, fn (Builder $q) => $q->where('driver_profiles.approval_status', 'approved'))
            ->orderBy('users.name')
            ->pluck('users.name', 'driver_profiles.driver_profile_id')
            ->all();
    }

    public static function formatDurasi(?int $menit): string
    {
        if ($menit === null) {
            return '-';
        }

        if ($menit % 1440 === 0) {
            return ($menit / 1440).' hari';
        }

        if ($menit % 60 === 0) {
            return ($menit / 60).' jam';
        }

        return $menit.' menit';
    }

    public static function tanggal($waktu): ?string
    {
        return $waktu?->format('d M Y H:i');
    }

    /** Isi modal detail: pasangan label dan nilai, baris kosong dilewati. */
    public static function htmlDetail(array $baris): HtmlString
    {
        $html = '<dl style="display:grid;grid-template-columns:11rem 1fr;gap:.6rem 1rem;font-size:.875rem;margin:0">';

        foreach ($baris as $label => $nilai) {
            if ($nilai === null || $nilai === '') {
                continue;
            }

            $isi = $nilai instanceof Htmlable ? $nilai->toHtml() : e($nilai);

            $html .= '<dt style="color:var(--gray-500)">'.e($label).'</dt>'
                .'<dd style="margin:0;white-space:pre-line">'.$isi.'</dd>';
        }

        return new HtmlString($html.'</dl>');
    }

    public static function labelStatus(DriverSanction $sanksi): string
    {
        return match ($sanksi->status) {
            DriverSanction::STATUS_WAITING => 'Terjadwal',
            DriverSanction::STATUS_ACTIVE => $sanksi->type === DriverSanction::TYPE_PERMANENT ? 'Permanen' : 'Aktif',
            DriverSanction::STATUS_EXPIRED => 'Berakhir',
            DriverSanction::STATUS_LIFTED => 'Dicabut',
            DriverSanction::STATUS_REPLACED => 'Diganti',
            DriverSanction::STATUS_TERMINATED => 'Putus mitra',
            default => $sanksi->status,
        };
    }

    public static function warnaStatus(DriverSanction $sanksi): string
    {
        return match ($sanksi->status) {
            DriverSanction::STATUS_WAITING => 'info',
            DriverSanction::STATUS_ACTIVE => $sanksi->type === DriverSanction::TYPE_PERMANENT ? 'danger' : 'warning',
            DriverSanction::STATUS_TERMINATED => 'danger',
            DriverSanction::STATUS_LIFTED => 'success',
            default => 'gray',
        };
    }

    public static function labelPelaksana(DriverSanction $sanksi): string
    {
        return $sanksi->imposed_by === null ? 'Sistem' : ($sanksi->imposer?->name ?? 'Admin');
    }

    // ===== Tabel =====

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with([
                'driverProfile.user',
                'imposer',
                'lifter',
                'violation.violationType',
            ]))
            ->defaultSort('driver_sanction_id', 'desc')
            ->columns([
                TextColumn::make('driverProfile.user.name')
                    ->label('Driver')
                    ->searchable()
                    ->description(fn (DriverSanction $record) => 'ID Driver: '.$record->driver_profile_id),

                TextColumn::make('tindakan')
                    ->label('Tindakan')
                    ->state(fn (DriverSanction $record) => $record->type === DriverSanction::TYPE_TERMINATION ? 'Putus mitra' : 'Suspend')
                    ->badge()
                    ->color(fn (string $state) => $state === 'Putus mitra' ? 'danger' : 'warning'),

                TextColumn::make('type')
                    ->label('Jenis')
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        DriverSanction::TYPE_TEMPORARY => 'Sementara',
                        DriverSanction::TYPE_PERMANENT => 'Permanen',
                        DriverSanction::TYPE_TERMINATION => 'Putus mitra',
                        default => $state,
                    }),

                TextColumn::make('duration_minutes')
                    ->label('Durasi')
                    ->formatStateUsing(fn ($state) => static::formatDurasi($state !== null ? (int) $state : null)),

                TextColumn::make('started_at')
                    ->label('Mulai')
                    ->dateTime('d M Y H:i')
                    ->placeholder('Menunggu pesanan selesai')
                    ->sortable(),

                TextColumn::make('expires_at')
                    ->label('Berakhir')
                    ->state(fn (DriverSanction $record) => $record->expires_at
                        ? static::tanggal($record->expires_at)
                        : ($record->started_at ? 'Tidak terbatas' : '-'))
                    ->sortable(query: fn (Builder $query, string $direction) => $query->orderBy('expires_at', $direction)),

                TextColumn::make('pelaksana')
                    ->label('Pelaksana')
                    ->state(fn (DriverSanction $record) => static::labelPelaksana($record))
                    ->badge()
                    ->color(fn (string $state) => $state === 'Sistem' ? 'gray' : 'info'),

                TextColumn::make('reason')
                    ->label('Alasan')
                    ->limit(50)
                    ->wrap()
                    ->tooltip(fn (DriverSanction $record) => $record->reason)
                    ->searchable(),

                TextColumn::make('status')
                    ->label('Status')
                    ->state(fn (DriverSanction $record) => static::labelStatus($record))
                    ->badge()
                    ->color(fn (DriverSanction $record) => static::warnaStatus($record))
                    ->description(fn (DriverSanction $record) => $record->lifted_at && $record->lift_reason === DriverSanction::LIFT_MANUAL
                        ? 'Reaktivasi '.static::tanggal($record->lifted_at)
                        : null),
            ])
            ->filters([
                SelectFilter::make('driver_profile_id')
                    ->label('Driver')
                    ->options(fn () => static::opsiDriver(true))
                    ->searchable(),

                SelectFilter::make('type')
                    ->label('Jenis sanksi')
                    ->options([
                        DriverSanction::TYPE_TEMPORARY => 'Sementara',
                        DriverSanction::TYPE_PERMANENT => 'Permanen',
                        DriverSanction::TYPE_TERMINATION => 'Putus mitra',
                    ]),

                SelectFilter::make('status')
                    ->label('Status sanksi')
                    ->options([
                        'waiting' => 'Terjadwal',
                        'active' => 'Aktif (sementara)',
                        'permanent' => 'Permanen',
                        'terminated' => 'Putus mitra',
                        'expired' => 'Berakhir',
                        'lifted' => 'Dicabut',
                        'replaced' => 'Diganti',
                    ])
                    ->query(fn (Builder $query, array $data) => match ($data['value'] ?? null) {
                        'waiting' => $query->waiting(),
                        'active' => $query->inEffect()->where('type', DriverSanction::TYPE_TEMPORARY),
                        'permanent' => $query->inEffect()->where('type', DriverSanction::TYPE_PERMANENT),
                        'terminated' => $query->inEffect()->where('type', DriverSanction::TYPE_TERMINATION),
                        'expired' => $query->whereNull('lifted_at')
                            ->where('type', DriverSanction::TYPE_TEMPORARY)
                            ->whereNotNull('expires_at')
                            ->where('expires_at', '<=', now()),
                        'lifted' => $query->whereNotNull('lifted_at')->where('lift_reason', DriverSanction::LIFT_MANUAL),
                        'replaced' => $query->whereNotNull('lifted_at')->where('lift_reason', DriverSanction::LIFT_REPLACED),
                        default => $query,
                    }),

                SelectFilter::make('pelaksana')
                    ->label('Pelaksana')
                    ->options(['admin' => 'Admin', 'sistem' => 'Sistem'])
                    ->query(fn (Builder $query, array $data) => match ($data['value'] ?? null) {
                        'admin' => $query->whereNotNull('imposed_by'),
                        'sistem' => $query->whereNull('imposed_by'),
                        default => $query,
                    }),

                Filter::make('suspend_aktif')
                    ->label('Suspend aktif')
                    ->toggle()
                    ->query(fn (Builder $query) => $query->inEffect()->where('type', '!=', DriverSanction::TYPE_TERMINATION)),

                Filter::make('akan_berakhir')
                    ->label('Akan berakhir (24 jam)')
                    ->toggle()
                    ->query(fn (Builder $query) => $query->inEffect()
                        ->where('type', DriverSanction::TYPE_TEMPORARY)
                        ->whereBetween('expires_at', [now(), now()->addDay()])),

                Filter::make('tanggal')
                    ->label('Periode')
                    ->schema([
                        DatePicker::make('dari')->label('Dari tanggal'),
                        DatePicker::make('sampai')->label('Sampai tanggal'),
                    ])
                    ->columns(2)
                    ->columnSpan(2)
                    ->query(fn (Builder $query, array $data) => $query
                        ->when($data['dari'] ?? null, fn (Builder $q, $tanggal) => $q->whereDate('created_at', '>=', $tanggal))
                        ->when($data['sampai'] ?? null, fn (Builder $q, $tanggal) => $q->whereDate('created_at', '<=', $tanggal))),
            ], layout: FiltersLayout::AboveContentCollapsible)
            ->filtersFormColumns(4)
            ->recordActions([
                Action::make('detail')
                    ->label('Detail')
                    ->icon('heroicon-o-eye')
                    ->color('gray')
                    ->modalHeading('Detail Sanksi')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Tutup')
                    ->modalContent(fn (DriverSanction $record) => static::htmlDetail([
                        'Driver' => $record->driverProfile?->user?->name.' (ID '.$record->driver_profile_id.')',
                        'Jenis' => match ($record->type) {
                            DriverSanction::TYPE_TEMPORARY => 'Sementara',
                            DriverSanction::TYPE_PERMANENT => 'Permanen',
                            default => 'Putus mitra',
                        },
                        'Durasi' => $record->type === DriverSanction::TYPE_TEMPORARY ? static::formatDurasi($record->duration_minutes) : null,
                        'Status' => static::labelStatus($record),
                        'Pelaksana' => static::labelPelaksana($record),
                        'Alasan' => $record->reason,
                        'Catatan' => $record->notes,
                        'Dijatuhkan' => static::tanggal($record->created_at),
                        'Mulai' => $record->started_at ? static::tanggal($record->started_at) : 'Menunggu pesanan driver selesai',
                        'Berakhir' => static::tanggal($record->expires_at),
                        'Poin saat dijatuhkan' => $record->points_at_imposition,
                        'Laporan pemicu' => $record->violation
                            ? '#'.$record->violation->getKey().' - '.$record->violation->violationType?->name
                            : null,
                        'Dicabut pada' => static::tanggal($record->lifted_at),
                        'Dicabut oleh' => $record->lifted_at
                            ? ($record->lifted_by ? $record->lifter?->name : ($record->lift_reason === DriverSanction::LIFT_REPLACED ? 'Sistem (diganti sanksi baru)' : null))
                            : null,
                        'Catatan pencabutan' => $record->lift_note,
                    ])),

                Action::make('cabut')
                    ->label('Cabut / Aktifkan Kembali')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('warning')
                    ->visible(fn (DriverSanction $record) => in_array($record->status, [
                        DriverSanction::STATUS_WAITING,
                        DriverSanction::STATUS_ACTIVE,
                        DriverSanction::STATUS_TERMINATED,
                    ], true))
                    ->modalHeading('Cabut sanksi')
                    ->modalDescription('Driver bisa menerima order lagi setelah sanksi dicabut.')
                    ->schema([
                        Textarea::make('catatan')->label('Catatan pencabutan')->required()->rows(3),
                    ])
                    ->action(fn (DriverSanction $record, array $data) => static::jalankan(
                        fn () => DriverSanctionService::cabutSanksi(Auth::user(), $record, $data['catatan']),
                        'Sanksi dicabut',
                    )),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRiwayatSanksi::route('/'),
        ];
    }
}
