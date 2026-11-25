<?php

namespace App\Support\Concerns;

use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

/** Tab Semua, Tampil, Disembunyikan, Rating rendah, dan ringkasan rata-rata di halaman daftar ulasan. */
trait MenampilkanTabReview
{
    // Sudah disediakan oleh Filament\Resources\Pages\Page. Dideklarasikan di sini supaya trait ini
    // jelas hanya boleh dipakai di halaman resource (dan IDE tidak menandainya sebagai method tidak ada).
    abstract public static function getResource(): string;

    public function getTabs(): array
    {
        $model = static::getResource()::getModel();

        return [
            'semua' => Tab::make('Semua')
                ->badge($model::count()),

            'tampil' => Tab::make('Tampil')
                ->badge($model::where('is_visible', true)->count())
                ->modifyQueryUsing(fn(Builder $query) => $query->where('is_visible', true)),

            'disembunyikan' => Tab::make('Disembunyikan')
                ->badge($model::where('is_visible', false)->count())
                ->badgeColor('gray')
                ->modifyQueryUsing(fn(Builder $query) => $query->where('is_visible', false)),

            'rendah' => Tab::make('Rating rendah (1-2)')
                ->badge($model::where('rating', '<=', 2)->count())
                ->badgeColor('danger')
                ->modifyQueryUsing(fn(Builder $query) => $query->where('rating', '<=', 2)),
        ];
    }

    public function getSubheading(): ?string
    {
        $model = static::getResource()::getModel();

        $stat = $model::where('is_visible', true)
            ->selectRaw('COUNT(*) AS jumlah, COALESCE(AVG(rating), 0) AS rata')
            ->first();

        return $stat->jumlah > 0
            ? 'Rata-rata ★ ' . number_format((float) $stat->rata, 1, ',', '.') . ' dari ' . $stat->jumlah . ' ulasan yang tampil'
            : 'Belum ada ulasan';
    }
}