<?php

namespace App\Support\Concerns;

use Carbon\Carbon;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Filters\Filter;
use Illuminate\Database\Eloquent\Builder;

trait HasRentangTanggal
{
    // Kalau tanggal dikosongkan, otomatis bulan ini (supaya label periode di file selalu sesuai isinya)
    public static function rentangTanggal(?array $filter): array
    {
        $start = filled($filter['dari'] ?? null)
            ? Carbon::parse($filter['dari'])->startOfDay()
            : now()->startOfMonth();

        $end = filled($filter['sampai'] ?? null)
            ? Carbon::parse($filter['sampai'])->endOfDay()
            : now()->endOfDay();

        return [$start, $end];
    }

    // $kolom diisi kalau filter ini yang membatasi query (mis. 'orders.created_at').
    // Dikosongkan untuk laporan yang menghitung periodenya sendiri (Performa Driver).
    public static function filterTanggal(?string $kolom = null): Filter
    {
        return Filter::make('tanggal')
            ->schema([
                DatePicker::make('dari')->label('Dari tanggal')->default(now()->startOfMonth()),
                DatePicker::make('sampai')->label('Sampai tanggal')->default(now()),
            ])
            ->columns(2)
            ->columnSpan(2)
            ->query(function (Builder $query, array $data) use ($kolom) {
                if (! $kolom) {
                    return $query;
                }

                [$start, $end] = static::rentangTanggal($data);

                return $query->whereBetween($kolom, [$start, $end]);
            });
    }
}