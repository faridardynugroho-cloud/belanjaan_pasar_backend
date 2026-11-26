<?php

namespace App\Filament\Resources\Drivers\RiwayatPengiriman\RelationManagers;

use App\Filament\Resources\Drivers\RiwayatPengiriman\RiwayatPengirimanResource;
use App\Filament\Resources\Drivers\RiwayatSanksi\RiwayatSanksiResource;
use App\Models\DeliveryOrder;
use App\Models\DriverViolation;
use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

class OrderPengirimanRelationManager extends RelationManager
{
    protected static string $relationship = 'deliveryOrders';

    protected static ?string $title = 'Pesanan dalam Pengiriman';

    public function isReadOnly(): bool
    {
        return false;
    }

    public static function labelStatus(string $status): string
    {
        return match ($status) {
            DeliveryOrder::STATUS_PENDING => 'Ditawarkan',
            DeliveryOrder::STATUS_ACCEPTED => 'Diterima driver',
            DeliveryOrder::STATUS_REJECTED => 'Ditolak driver',
            DeliveryOrder::STATUS_ON_THE_WAY => 'Diantar',
            DeliveryOrder::STATUS_COMPLETED => 'Selesai',
            default => $status,
        };
    }

    protected static function urlFoto(string $path): string
    {
        /** @var FilesystemAdapter $disk */
        $disk = Storage::disk('public');

        return $disk->url($path);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query
                ->with(['order', 'review', 'violations.violationType'])
                ->withCount(['violations as komplain_count' => fn (Builder $q) => $q->where('source', DriverViolation::SOURCE_CUSTOMER)])
                ->orderBy('assigned_at'))
            ->paginated(false)
            ->columns([
                TextColumn::make('order.order_number')
                    ->label('No. Pesanan')
                    ->copyable()
                    ->description(fn (DeliveryOrder $record) => $record->order?->recipient_name),

                TextColumn::make('order.shipping_address')->label('Alamat tujuan')->limit(45)->wrap(),

                TextColumn::make('berat')
                    ->label('Berat')
                    ->state(fn (DeliveryOrder $record) => RiwayatPengirimanResource::labelBerat($record->order?->total_weight_grams)),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => static::labelStatus($state))
                    ->color(fn (string $state) => match ($state) {
                        DeliveryOrder::STATUS_COMPLETED => 'success',
                        DeliveryOrder::STATUS_REJECTED => 'danger',
                        DeliveryOrder::STATUS_ON_THE_WAY => 'primary',
                        DeliveryOrder::STATUS_ACCEPTED => 'info',
                        default => 'warning',
                    })
                    ->description(fn (DeliveryOrder $record) => $record->status === DeliveryOrder::STATUS_REJECTED
                        ? $record->rejection_reason
                        : null),

                TextColumn::make('completed_at')->label('Selesai')->dateTime('d M Y H:i')->placeholder('-'),

                TextColumn::make('review.rating')
                    ->label('Rating')
                    ->formatStateUsing(fn ($state) => '★ '.$state)
                    ->placeholder('-')
                    ->description(fn (DeliveryOrder $record) => $record->review?->comment
                        ? Str::limit($record->review->comment, 40)
                        .($record->review->is_visible ? '' : ' (disembunyikan)')
                        : null),

                TextColumn::make('komplain_count')
                    ->label('Komplain')
                    ->state(fn (DeliveryOrder $record) => (int) $record->komplain_count)
                    ->badge()
                    ->color(fn (int $state) => $state > 0 ? 'danger' : 'gray'),
            ])
            ->recordActions([
                Action::make('detail')
                    ->label('Detail')
                    ->icon('heroicon-o-eye')
                    ->color('gray')
                    ->modalHeading(fn (DeliveryOrder $record) => 'Pesanan '.$record->order?->order_number)
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Tutup')
                    ->modalContent(fn (DeliveryOrder $record) => RiwayatSanksiResource::htmlDetail([
                        'Penerima' => $record->order?->recipient_name.' ('.$record->order?->recipient_phone.')',
                        'Alamat' => $record->order?->shipping_address,
                        'Berat' => RiwayatPengirimanResource::labelBerat($record->order?->total_weight_grams),
                        'Status' => static::labelStatus($record->status),
                        'Ditawarkan' => RiwayatSanksiResource::tanggal($record->assigned_at),
                        'Diterima' => RiwayatSanksiResource::tanggal($record->accepted_at),
                        'Ditolak' => RiwayatSanksiResource::tanggal($record->rejected_at),
                        'Alasan penolakan' => $record->rejection_reason,
                        'Berangkat' => RiwayatSanksiResource::tanggal($record->started_at),
                        'Selesai' => RiwayatSanksiResource::tanggal($record->completed_at),
                        'Diterima oleh' => $record->labelPenerima(),
                        'Catatan penerima' => $record->received_note,
                        'Foto bukti' => $record->proof_photo
                            ? new HtmlString(
                                '<a href="'.e(static::urlFoto($record->proof_photo)).'" target="_blank" rel="noopener">'
                                    .'<img src="'.e(static::urlFoto($record->proof_photo)).'" alt="Foto bukti" style="max-width:240px;border-radius:8px">'
                                    .'</a>'
                            )
                            : null,
                        'Rating' => $record->review ? '★ '.$record->review->rating.($record->review->is_visible ? '' : ' (review disembunyikan)') : null,
                        'Review customer' => $record->review?->comment,
                        'Komplain' => $record->violations->isEmpty()
                            ? null
                            : $record->violations
                                ->map(fn (DriverViolation $v) => ($v->source === DriverViolation::SOURCE_CUSTOMER ? 'Customer' : 'Admin')
                                    .': '.$v->violationType?->name.' ('.match ($v->status) {
                                        DriverViolation::STATUS_REPORTED => 'menunggu validasi',
                                        DriverViolation::STATUS_VALIDATED => 'divalidasi',
                                        DriverViolation::STATUS_REJECTED => 'ditolak',
                                        default => 'dibatalkan',
                                    }.') '.$v->description)
                                ->implode("\n"),
                    ])),
            ]);
    }
}
