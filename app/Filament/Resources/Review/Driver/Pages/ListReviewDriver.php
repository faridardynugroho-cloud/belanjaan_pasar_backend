<?php

namespace App\Filament\Resources\Review\Driver\Pages;

use App\Filament\Resources\Review\Driver\ReviewDriverResource;
use App\Support\Concerns\MenampilkanTabReview;
use Filament\Resources\Pages\ListRecords;

class ListReviewDriver extends ListRecords
{
    use MenampilkanTabReview;

    protected static string $resource = ReviewDriverResource::class;
}