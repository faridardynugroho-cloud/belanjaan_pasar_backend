<?php

namespace App\Filament\Resources\Review\Produk\Pages;

use App\Filament\Resources\Review\Produk\ReviewProdukResource;
use App\Support\Concerns\MenampilkanTabReview;
use Filament\Resources\Pages\ListRecords;

class ListReviewProduk extends ListRecords
{
    use MenampilkanTabReview;

    protected static string $resource = ReviewProdukResource::class;
}
