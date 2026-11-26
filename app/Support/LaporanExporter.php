<?php

namespace App\Support;

use Barryvdh\DomPDF\Facade\Pdf;
use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Color;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Options;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LaporanExporter
{
    /**
     * @param  array  $kolom  [['label' => 'Total', 'tipe' => 'rupiah', 'lebar' => 16], ...]  tipe: teks|angka|desimal|rupiah
     * @param  array  $rows  array of array, urutan nilai sama dengan $kolom (nilai mentah)
     * @param  array  $ringkasan  [['label' => 'Total', 'nilai' => 1000, 'tipe' => 'rupiah'], ...]
     */
    public static function xlsx(string $filename, string $judul, string $periode, array $kolom, array $rows, array $ringkasan = []): StreamedResponse
    {
        $path = tempnam(sys_get_temp_dir(), 'laporan_');

        $options = new Options;
        foreach ($kolom as $i => $k) {
            $options->setColumnWidth($k['lebar'] ?? 18, $i + 1);
        }

        $writer = new Writer($options);
        $writer->openToFile($path);

        $tebal = (new Style)->setFontBold();
        $header = (new Style)->setFontBold()->setBackgroundColor(Color::rgb(245, 158, 11));

        $writer->addRow(Row::fromValues([$judul], (new Style)->setFontBold()->setFontSize(14)));
        $writer->addRow(Row::fromValues([$periode]));
        $writer->addRow(Row::fromValues(['']));

        foreach ($ringkasan as $item) {
            $tipe = $item['tipe'] ?? 'teks';
            $writer->addRow(new Row([
                Cell::fromValue($item['label'], $tebal),
                Cell::fromValue(self::nilaiExcel($item['nilai'], $tipe), self::gaya($tipe)),
            ]));
        }

        if ($ringkasan) {
            $writer->addRow(Row::fromValues(['']));
        }

        $writer->addRow(Row::fromValues(array_column($kolom, 'label'), $header));

        foreach ($rows as $row) {
            $cells = [];

            foreach ($kolom as $i => $k) {
                $tipe = $k['tipe'] ?? 'teks';
                $cells[] = Cell::fromValue(self::nilaiExcel($row[$i] ?? null, $tipe), self::gaya($tipe));
            }

            $writer->addRow(new Row($cells));
        }

        $writer->close();

        return response()->streamDownload(function () use ($path) {
            readfile($path);
            @unlink($path);
        }, $filename, ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }

    public static function pdf(string $filename, string $judul, string $periode, array $kolom, array $rows, array $ringkasan = []): StreamedResponse
    {
        $pdf = Pdf::loadView('laporan.pdf', compact('judul', 'periode', 'kolom', 'rows', 'ringkasan'))
            ->setPaper('a4', count($kolom) > 6 ? 'landscape' : 'portrait');

        return response()->streamDownload(function () use ($pdf) {
            echo $pdf->output();
        }, $filename, ['Content-Type' => 'application/pdf']);
    }

    // Dipakai di PDF
    public static function format(mixed $nilai, string $tipe = 'teks'): string
    {
        if ($nilai === null || $nilai === '') {
            return '-';
        }

        return match ($tipe) {
            'angka' => number_format((float) $nilai, 0, ',', '.'),
            'desimal' => number_format((float) $nilai, 2, ',', '.'),
            'rupiah' => 'Rp '.number_format((float) $nilai, 0, ',', '.'),
            default => (string) $nilai,
        };
    }

    private static function nilaiExcel(mixed $nilai, string $tipe): mixed
    {
        if ($nilai === null || $nilai === '') {
            return '';
        }

        return in_array($tipe, ['angka', 'desimal', 'rupiah'], true) ? (float) $nilai : (string) $nilai;
    }

    private static function gaya(string $tipe): ?Style
    {
        $format = match ($tipe) {
            'angka' => '#,##0',
            'desimal' => '#,##0.00',
            'rupiah' => '"Rp" #,##0',
            default => null,
        };

        return $format ? (new Style)->setFormat($format) : null;
    }
}
