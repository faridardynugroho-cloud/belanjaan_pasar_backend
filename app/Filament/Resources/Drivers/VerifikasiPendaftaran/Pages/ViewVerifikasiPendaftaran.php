<?php

namespace App\Filament\Resources\Drivers\VerifikasiPendaftaran\Pages;

use App\Filament\Resources\Drivers\DaftarDriver\DaftarDriverResource;
use App\Filament\Resources\Drivers\RiwayatSanksi\RiwayatSanksiResource;
use App\Filament\Resources\Drivers\VerifikasiPendaftaran\VerifikasiPendaftaranResource;
use App\Models\DriverProfile;
use App\Services\DriverVerificationService;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\Auth;

class ViewVerifikasiPendaftaran extends ViewRecord
{
    protected static string $resource = VerifikasiPendaftaranResource::class;

    public function mount(int|string $record): void
    {
        parent::mount($record);

        // Pendaftar lama atau yang dibuat tanpa baris pemeriksaan tetap bisa diperiksa.
        DriverVerificationService::pastikanPemeriksaan($this->getRecord());
    }

    protected function getHeaderActions(): array
    {
        $bisaDinilai = fn (DriverProfile $record) => in_array($record->approval_status, [
            DriverProfile::APPROVAL_PENDING,
            DriverProfile::APPROVAL_REVISION,
        ], true);

        return [
            Action::make('setujui')
                ->label('Setujui')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->visible($bisaDinilai)
                ->requiresConfirmation()
                ->modalHeading('Setujui pendaftaran driver?')
                ->modalDescription('Semua dokumen harus sudah ditandai valid. Setelah disetujui, driver pindah ke Daftar Driver.')
                ->action(function (DriverProfile $record, $livewire) {
                    $hasil = RiwayatSanksiResource::jalankan(
                        fn () => DriverVerificationService::setujui(Auth::user(), $record),
                        'Pendaftaran disetujui, driver masuk ke Daftar Driver',
                    );

                    // Record sudah tidak masuk query halaman ini, jadi pindah ke detail di Daftar Driver.
                    if ($hasil) {
                        $livewire->redirect(DaftarDriverResource::getUrl('view', ['record' => $record]));
                    }
                }),

            Action::make('mintaPerbaikan')
                ->label('Minta Perbaikan')
                ->icon('heroicon-o-arrow-path')
                ->color('info')
                ->visible(fn (DriverProfile $record) => $record->approval_status === DriverProfile::APPROVAL_PENDING)
                ->requiresConfirmation()
                ->modalHeading('Minta driver memperbaiki dokumen?')
                ->modalDescription('Dokumen yang ditandai tidak valid beserta catatannya akan dikirim ke driver. Minimal satu dokumen harus tidak valid.')
                ->action(fn (DriverProfile $record) => RiwayatSanksiResource::jalankan(
                    fn () => DriverVerificationService::mintaPerbaikan(Auth::user(), $record),
                    'Permintaan perbaikan dikirim ke driver',
                )),

            Action::make('tolak')
                ->label('Tolak')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->visible($bisaDinilai)
                ->modalHeading('Tolak pendaftaran driver')
                ->schema([
                    Textarea::make('alasan')
                        ->label('Alasan penolakan')
                        ->required()
                        ->rows(3)
                        ->helperText('Alasan ini dilihat driver.'),
                ])
                ->action(fn (DriverProfile $record, array $data) => RiwayatSanksiResource::jalankan(
                    fn () => DriverVerificationService::tolak(Auth::user(), $record, $data['alasan']),
                    'Pendaftaran ditolak',
                )),
        ];
    }
}
