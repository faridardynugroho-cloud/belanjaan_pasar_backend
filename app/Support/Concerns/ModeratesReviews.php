<?php

namespace App\Support\Concerns;

use App\Services\ReviewService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Model;

trait ModeratesReviews
{
    protected static function aksiModerasi(): Action
    {
        return Action::make('moderasi')
            ->label(fn (Model $record) => $record->is_visible ? 'Sembunyikan' : 'Tampilkan')
            ->icon(fn (Model $record) => $record->is_visible ? 'heroicon-o-eye-slash' : 'heroicon-o-eye')
            ->color(fn (Model $record) => $record->is_visible ? 'danger' : 'success')
            ->requiresConfirmation()
            ->modalDescription('Ulasan yang disembunyikan tidak tampil ke customer dan tidak dihitung di rata-rata rating.')
            ->action(function (Model $record) {
                ReviewService::setTampil($record, ! $record->is_visible);

                Notification::make()
                    ->title($record->is_visible ? 'Ulasan ditampilkan' : 'Ulasan disembunyikan')
                    ->success()
                    ->send();
            });
    }
}