<?php

namespace App\Filament\Resources\Drivers\VerifikasiPendaftaran\RelationManagers;

use App\Filament\Resources\Drivers\RiwayatSanksi\RiwayatSanksiResource;
use App\Models\DriverDocumentCheck;
use App\Models\DriverProfile;
use App\Services\DriverVerificationService;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class PemeriksaanDokumenRelationManager extends RelationManager
{
    protected static string $relationship = 'documentChecks';

    protected static ?string $title = 'Pemeriksaan Dokumen';

    /** Di halaman detail relation manager default-nya hanya baca, sedangkan di sini admin perlu menilai. */
    public function isReadOnly(): bool
    {
        return false;
    }

    public function table(Table $table): Table
    {
        $bisaDinilai = fn(DriverDocumentCheck $record) => in_array($record->driverProfile?->approval_status, [
            DriverProfile::APPROVAL_PENDING,
            DriverProfile::APPROVAL_REVISION,
        ], true);

        return $table
            ->modifyQueryUsing(fn(Builder $query) => $query
                ->with(['driverProfile.document', 'driverProfile.vehicle', 'checker'])
                ->orderByRaw("FIELD(item, 'ktp', 'selfie', 'sim', 'vehicle', 'stnk')"))
            ->paginated(false)
            ->columns([
                TextColumn::make('item')
                    ->label('Dokumen')
                    ->weight('bold')
                    ->formatStateUsing(fn(string $state) => DriverDocumentCheck::labelItem($state))
                    ->description(fn(DriverDocumentCheck $record) => $record->keterangan()),

                ImageColumn::make('foto')
                    ->label('Pratinjau')
                    ->state(fn(DriverDocumentCheck $record) => $record->pathFoto())
                    ->disk('public')
                    ->imageHeight(90)
                    ->url(fn(DriverDocumentCheck $record) => $record->urlFoto(), shouldOpenInNewTab: true),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn(string $state) => match ($state) {
                        DriverDocumentCheck::STATUS_VALID => 'Valid',
                        DriverDocumentCheck::STATUS_INVALID => 'Tidak valid',
                        default => 'Belum diperiksa',
                    })
                    ->color(fn(string $state) => match ($state) {
                        DriverDocumentCheck::STATUS_VALID => 'success',
                        DriverDocumentCheck::STATUS_INVALID => 'danger',
                        default => 'gray',
                    })
                    ->description(fn(DriverDocumentCheck $record) => $record->checked_at
                        ? $record->checker?->name . ', ' . $record->checked_at->format('d M Y H:i')
                        : null),

                TextColumn::make('note')->label('Catatan perbaikan')->wrap()->placeholder('-'),
            ])
            ->recordActions([
                Action::make('valid')
                    ->label('Valid')
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->visible(fn(DriverDocumentCheck $record) => $bisaDinilai($record) && $record->status !== DriverDocumentCheck::STATUS_VALID)
                    ->action(fn(DriverDocumentCheck $record) => RiwayatSanksiResource::jalankan(
                        fn() => DriverVerificationService::nilaiDokumen(Auth::user(), $record, DriverDocumentCheck::STATUS_VALID),
                        DriverDocumentCheck::labelItem($record->item) . ' ditandai valid',
                    )),

                Action::make('tidakValid')
                    ->label('Tidak Valid')
                    ->icon('heroicon-o-x-mark')
                    ->color('danger')
                    ->visible(fn(DriverDocumentCheck $record) => $bisaDinilai($record) && $record->status !== DriverDocumentCheck::STATUS_INVALID)
                    ->modalHeading(fn(DriverDocumentCheck $record) => DriverDocumentCheck::labelItem($record->item) . ' tidak valid')
                    ->schema([
                        Textarea::make('catatan')
                            ->label('Apa yang harus diperbaiki?')
                            ->required()
                            ->rows(3)
                            ->helperText('Catatan ini dilihat driver, tulis jelas. Contoh: foto buram, nama tidak terbaca.'),
                    ])
                    ->action(fn(DriverDocumentCheck $record, array $data) => RiwayatSanksiResource::jalankan(
                        fn() => DriverVerificationService::nilaiDokumen(Auth::user(), $record, DriverDocumentCheck::STATUS_INVALID, $data['catatan']),
                        DriverDocumentCheck::labelItem($record->item) . ' ditandai tidak valid',
                    )),
            ]);
    }
}