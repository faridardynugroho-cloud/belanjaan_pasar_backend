<?php

namespace App\Filament\Resources\Products\RelationManagers;

use App\Support\Concerns\ModeratesReviews;
use App\Support\Rating;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ReviewsRelationManager extends RelationManager
{
    use ModeratesReviews;

    protected static string $relationship = 'reviews';

    protected static ?string $title = 'Ulasan';

    public function isReadOnly(): bool
    {
        return false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('user:user_id,name'))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('rating')
                    ->formatStateUsing(fn ($state) => Rating::bintang($state))
                    ->sortable(),
                TextColumn::make('comment')->label('Komentar')->limit(80)->wrap()->placeholder('-'),
                TextColumn::make('user.name')->label('Customer'),
                TextColumn::make('created_at')->label('Dikirim')->dateTime('d M Y H:i')->sortable(),
                IconColumn::make('is_visible')->label('Tampil')->boolean(),
            ])
            ->filters([
                TernaryFilter::make('is_visible')
                    ->label('Status')
                    ->placeholder('Semua')
                    ->trueLabel('Tampil')
                    ->falseLabel('Disembunyikan'),
            ])
            ->recordActions([
                $this->aksiModerasi(),
            ]);
    }
}
