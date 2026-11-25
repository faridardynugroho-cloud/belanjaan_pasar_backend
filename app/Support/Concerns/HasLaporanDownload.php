<?php

namespace App\Support\Concerns;

use App\Support\LaporanExporter;
use Filament\Actions\Action;
use Illuminate\Support\Str;

trait HasLaporanDownload
{
    protected function getHeaderActions(): array
    {
        return [
            Action::make('excel')
                ->label('Download Excel')
                ->icon('heroicon-o-table-cells')
                ->color('success')
                ->action(fn () => $this->unduhLaporan('xlsx')),

            Action::make('pdf')
                ->label('Download PDF')
                ->icon('heroicon-o-document-arrow-down')
                ->color('danger')
                ->action(fn () => $this->unduhLaporan('pdf')),
        ];
    }

    protected function unduhLaporan(string $format)
    {
        $resource = static::getResource();

        [$start, $end] = $resource::rentangTanggal($this->tableFilters['tanggal'] ?? []);

        // Semua baris sesuai filter/pencarian/urutan aktif, bukan cuma halaman yang tampil
        $records = $this->getFilteredSortedTableQuery()->get();

        $judul = $resource::judulLaporan();
        $periode = 'Periode: '.$start->format('d/m/Y').' - '.$end->format('d/m/Y');
        $kolom = $resource::exportKolom();
        $rows = $records->map(fn ($record) => $resource::exportBaris($record))->all();
        $ringkasan = $resource::exportRingkasan($records);
        $nama = Str::slug($judul).'-'.$start->format('Ymd').'-'.$end->format('Ymd');

        return $format === 'xlsx'
            ? LaporanExporter::xlsx("{$nama}.xlsx", $judul, $periode, $kolom, $rows, $ringkasan)
            : LaporanExporter::pdf("{$nama}.pdf", $judul, $periode, $kolom, $rows, $ringkasan);
    }
}
