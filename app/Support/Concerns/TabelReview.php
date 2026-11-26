<?php

namespace App\Support\Concerns;

use App\Filament\Resources\Drivers\RiwayatSanksi\RiwayatSanksiResource;
use App\Support\Rating;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\Column;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\BaseFilter;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Bagian tabel yang sama untuk semua jenis ulasan (produk, Ngebelanjain, driver):
 * kolom, filter, aksi detail, dan moderasi tampil/sembunyikan.
 */
trait TabelReview
{
    use ModeratesReviews;

    protected static function kolomRating(): TextColumn
    {
        return TextColumn::make('rating')
            ->label('Rating')
            ->formatStateUsing(fn ($state) => Rating::bintang((int) $state))
            ->sortable();
    }

    /** @return array<Column> */
    protected static function kolomUmum(): array
    {
        return [
            TextColumn::make('comment')
                ->label('Komentar')
                ->limit(70)
                ->wrap()
                ->placeholder('-')
                ->searchable()
                ->tooltip(fn (Model $record) => $record->comment),

            TextColumn::make('user.name')->label('Customer')->searchable(),

            TextColumn::make('created_at')->label('Dikirim')->dateTime('d M Y H:i')->sortable(),

            IconColumn::make('is_visible')->label('Tampil')->boolean(),
        ];
    }

    /** @return array<BaseFilter> */
    protected static function filterUmum(): array
    {
        return [
            SelectFilter::make('rating')
                ->label('Rating')
                ->options(collect([5, 4, 3, 2, 1])->mapWithKeys(fn (int $n) => [$n => Rating::bintang($n)])->all()),

            TernaryFilter::make('is_visible')
                ->label('Status')
                ->placeholder('Semua')
                ->trueLabel('Tampil')
                ->falseLabel('Disembunyikan'),

            Filter::make('ada_komentar')
                ->label('Ada komentar')
                ->toggle()
                ->query(fn (Builder $query) => $query->whereNotNull('comment')->where('comment', '!=', '')),

            Filter::make('periode')
                ->label('Periode')
                ->schema([
                    DatePicker::make('dari')->label('Dari tanggal'),
                    DatePicker::make('sampai')->label('Sampai tanggal'),
                ])
                ->columns(2)
                ->columnSpan(2)
                ->query(fn (Builder $query, array $data) => $query
                    ->when($data['dari'] ?? null, fn (Builder $q, $tanggal) => $q->whereDate($q->getModel()->getTable().'.created_at', '>=', $tanggal))
                    ->when($data['sampai'] ?? null, fn (Builder $q, $tanggal) => $q->whereDate($q->getModel()->getTable().'.created_at', '<=', $tanggal))),
        ];
    }

    /** Baris dasar untuk modal detail; resource menambah baris khususnya di depan. */
    protected static function barisDasar(Model $review): array
    {
        return [
            'Customer' => $review->user?->name,
            'Rating' => Rating::bintang((int) $review->rating),
            'Komentar' => $review->comment,
            'Dikirim' => RiwayatSanksiResource::tanggal($review->created_at),
            'Status' => $review->is_visible ? 'Tampil' : 'Disembunyikan admin',
            'Masih bisa diubah customer' => $review->isEditable() ? 'Ya' : 'Tidak',
        ];
    }

    /** @param Closure(Model): array $barisKhusus baris tambahan di atas baris dasar */
    protected static function aksiDetail(Closure $barisKhusus): Action
    {
        return Action::make('detail')
            ->label('Detail')
            ->icon('heroicon-o-eye')
            ->color('gray')
            ->modalHeading('Detail ulasan')
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Tutup')
            ->modalContent(fn (Model $record) => RiwayatSanksiResource::htmlDetail(
                $barisKhusus($record) + static::barisDasar($record)
            ));
    }
}
