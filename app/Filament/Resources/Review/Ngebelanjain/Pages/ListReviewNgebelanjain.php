<?php

namespace App\Filament\Resources\Review\Ngebelanjain\Pages;

use App\Filament\Resources\Review\Ngebelanjain\ReviewNgebelanjainResource;
use App\Support\Concerns\MenampilkanTabReview;
use Filament\Resources\Pages\ListRecords;

class ListReviewNgebelanjain extends ListRecords
{
    use MenampilkanTabReview;

    protected static string $resource = ReviewNgebelanjainResource::class;
}
