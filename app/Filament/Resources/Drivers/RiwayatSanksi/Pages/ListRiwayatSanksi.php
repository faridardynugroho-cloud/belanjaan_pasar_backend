<?php

namespace App\Filament\Resources\Drivers\RiwayatSanksi\Pages;

use App\Filament\Resources\Drivers\RiwayatSanksi\RiwayatSanksiResource;
use App\Filament\Resources\Drivers\RiwayatSanksi\Widgets\LaporanPelanggaranTable;
use App\Filament\Resources\Drivers\RiwayatSanksi\Widgets\RiwayatSanksiStats;
use App\Models\DriverProfile;
use App\Models\DriverSanction;
use App\Models\DriverViolation;
use App\Services\DriverSanctionService;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Livewire;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Components\Attributes\Unexposed;
use Illuminate\Support\Facades\Auth;

class ListRiwayatSanksi extends ListRecords
{
    protected static string $resource = RiwayatSanksiResource::class;

    public function getTitle(): string
    {
        return 'Riwayat Sanksi';
    }

    protected function getHeaderWidgets(): array
    {
        return [
            RiwayatSanksiStats::class,
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('jatuhkan')
                ->label('Jatuhkan Sanksi')
                ->icon('heroicon-o-no-symbol')
                ->color('danger')
                ->modalHeading('Jatuhkan sanksi manual')
                ->schema([
                    Select::make('driver_profile_id')
                        ->label('Driver')
                        ->options(fn() => RiwayatSanksiResource::opsiDriver())
                        ->searchable()
                        ->required(),

                    Select::make('tipe')
                        ->label('Jenis sanksi')
                        ->options([
                            DriverSanction::TYPE_TEMPORARY => 'Sementara (suspend)',
                            DriverSanction::TYPE_PERMANENT => 'Permanen',
                            DriverSanction::TYPE_TERMINATION => 'Putus mitra',
                        ])
                        ->default(DriverSanction::TYPE_TEMPORARY)
                        ->live()
                        ->required(),

                    Grid::make(2)
                        ->visible(fn(Get $get) => $get('tipe') === DriverSanction::TYPE_TEMPORARY)
                        ->schema([
                            TextInput::make('durasi')
                                ->label('Durasi')
                                ->numeric()
                                ->integer()
                                ->minValue(1)
                                ->required(fn(Get $get) => $get('tipe') === DriverSanction::TYPE_TEMPORARY)
                                ->helperText('Maksimal 60 hari. Lebih lama dari itu pakai sanksi permanen.'),

                            Select::make('satuan')
                                ->label('Satuan')
                                ->options(['menit' => 'Menit', 'jam' => 'Jam', 'hari' => 'Hari'])
                                ->default('jam')
                                ->required(fn(Get $get) => $get('tipe') === DriverSanction::TYPE_TEMPORARY),
                        ]),

                    Textarea::make('alasan')->label('Alasan')->required()->rows(3),
                    Textarea::make('catatan')->label('Catatan internal')->rows(2),
                ])
                ->action(function (array $data) {
                    $menit = null;

                    if ($data['tipe'] === DriverSanction::TYPE_TEMPORARY) {
                        $pengali = ['menit' => 1, 'jam' => 60, 'hari' => 1440][$data['satuan']] ?? 60;
                        $menit = (int) $data['durasi'] * $pengali;
                    }

                    RiwayatSanksiResource::jalankan(
                        fn() => DriverSanctionService::jatuhkanManual(
                            Auth::user(),
                            DriverProfile::findOrFail($data['driver_profile_id']),
                            $data['tipe'],
                            $menit,
                            $data['alasan'],
                            $data['catatan'] ?? null,
                        ),
                        'Sanksi dijatuhkan',
                    );
                }),
        ];
    }

    #[Unexposed]
    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Tabs::make()
                ->persistTabInQueryString()
                ->tabs([
                    Tab::make('Riwayat Sanksi')
                        ->icon('heroicon-o-clock')
                        ->schema([
                            EmbeddedTable::make(),
                        ]),

                    Tab::make('Laporan Pelanggaran')
                        ->icon('heroicon-o-flag')
                        ->badge(fn() => DriverViolation::where('status', DriverViolation::STATUS_REPORTED)->count() ?: null)
                        ->badgeColor('warning')
                        ->schema([
                            Livewire::make(LaporanPelanggaranTable::class),
                        ]),
                ]),
        ]);
    }
}