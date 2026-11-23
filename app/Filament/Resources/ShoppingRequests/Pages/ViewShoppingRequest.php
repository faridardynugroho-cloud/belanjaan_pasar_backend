<?php

namespace App\Filament\Resources\ShoppingRequests\Pages;

use App\Filament\Resources\Orders\OrderResource;
use App\Filament\Resources\ShoppingRequests\ShoppingRequestResource;
use App\Models\Helper;
use App\Models\Order;
use App\Models\Payment;
use App\Models\ShoppingRequest;
use App\Services\ReviewService;
use Filament\Actions\Action;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Enums\Width;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class ViewShoppingRequest extends ViewRecord
{
    protected static string $resource = ShoppingRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('moderasiRating')
                ->label(fn(ShoppingRequest $record) => $record->order?->shoppingRequestReview?->is_visible
                    ? 'Sembunyikan Rating'
                    : 'Tampilkan Rating')
                ->icon('heroicon-o-eye-slash')
                ->color('gray')
                ->visible(fn(ShoppingRequest $record) => $record->order?->shoppingRequestReview !== null)
                ->requiresConfirmation()
                ->action(function (ShoppingRequest $record) {
                    $review = $record->order->shoppingRequestReview;

                    ReviewService::setTampil($review, ! $review->is_visible);

                    Notification::make()->title('Rating diperbarui')->success()->send();
                }),
            // 1. Tugaskan helper
            Action::make('tugaskan')
                ->label('Tugaskan Helper')
                ->icon('heroicon-o-user-plus')
                ->color('info')
                ->visible(fn(ShoppingRequest $record) => $record->status === 'pending')
                ->schema([
                    Select::make('helper_id')
                        ->label('Helper')
                        ->options(fn() => Helper::where('is_active', true)->pluck('name', 'helper_id'))
                        ->default(fn() => Helper::where('is_active', true)->value('helper_id'))
                        ->required(),
                ])
                ->action(function (ShoppingRequest $record, array $data) {
                    DB::transaction(function () use ($record, $data) {
                        $record->assignments()->create([
                            'helper_id' => $data['helper_id'],
                            'assigned_by' => Auth::id(),
                            'assigned_at' => now(),
                        ]);
                        $record->update(['status' => 'assigned']);
                    });

                    Notification::make()->title('Helper ditugaskan')->success()->send();
                }),

            // 2. Mulai belanja
            Action::make('mulaiBelanja')
                ->label('Mulai Belanja')
                ->icon('heroicon-o-play')
                ->color('info')
                ->visible(fn(ShoppingRequest $record) => $record->status === 'assigned')
                ->requiresConfirmation()
                ->action(function (ShoppingRequest $record) {
                    $record->update(['status' => 'shopping']);

                    Notification::make()->title('Status: sedang belanja')->success()->send();
                }),

            // 3. Input hasil belanja (rincian / total saja)
            Action::make('inputHasil')
                ->label('Input Hasil Belanja')
                ->icon('heroicon-o-pencil-square')
                ->color('primary')
                ->visible(fn(ShoppingRequest $record) => $record->status === 'shopping' && ! $record->order()->exists())
                ->modalWidth(Width::FiveExtraLarge)
                ->fillForm(fn(ShoppingRequest $record) => $this->hasilDefaults($record))
                ->schema([
                    Radio::make('mode')
                        ->label('Metode input')
                        ->options([
                            'rincian' => 'Rincian barang',
                            'total' => 'Total saja',
                        ])
                        ->default('rincian')
                        ->live()
                        ->inline()
                        ->required(),

                    Repeater::make('items')
                        ->label('Rincian barang')
                        ->visible(fn(Get $get) => $get('mode') === 'rincian')
                        ->minItems(1)
                        ->columns(5)
                        ->addActionLabel('Tambah barang')
                        ->schema([
                            TextInput::make('name')->label('Nama barang')->required(),
                            TextInput::make('quantity')->label('Jumlah')->numeric()->minValue(0.01)->required(),
                            TextInput::make('unit')->label('Satuan')->required()->placeholder('kg, ikat, buah'),
                            TextInput::make('unit_price')->label('Harga satuan')->numeric()->prefix('Rp')->minValue(0)->required(),
                            TextInput::make('weight_grams')
                                ->label('Berat/satuan (gram)')
                                ->numeric()
                                ->default(0)
                                ->minValue(0)
                                ->helperText('Dipakai untuk batas 50 kg pengiriman'),
                        ]),

                    TextInput::make('goods_total')
                        ->label('Total belanja')
                        ->numeric()
                        ->prefix('Rp')
                        ->minValue(1)
                        ->visible(fn(Get $get) => $get('mode') === 'total')
                        ->required(),

                    TextInput::make('total_weight_kg')
                        ->label('Total berat (kg)')
                        ->numeric()
                        ->minValue(0.01)
                        ->visible(fn(Get $get) => $get('mode') === 'total')
                        ->required(),

                    TextInput::make('service_fee')
                        ->label('Biaya layanan')
                        ->numeric()
                        ->prefix('Rp')
                        ->minValue(0)
                        ->default(0)
                        ->required(),
                ])
                ->action(function (ShoppingRequest $record, array $data) {
                    DB::transaction(function () use ($record, $data) {
                        $record->items()->delete();

                        if ($data['mode'] === 'rincian') {
                            $goods = 0;
                            $grams = 0;

                            foreach ($data['items'] as $row) {
                                $qty = (float) $row['quantity'];
                                $price = (float) $row['unit_price'];
                                $unitWeight = (int) ($row['weight_grams'] ?? 0);
                                $subtotal = round($qty * $price, 2);
                                $totalGrams = (int) round($qty * $unitWeight);

                                $record->items()->create([
                                    'name' => $row['name'],
                                    'quantity' => $qty,
                                    'unit' => $row['unit'],
                                    'unit_price' => $price,
                                    'subtotal' => $subtotal,
                                    'weight_grams' => $unitWeight,
                                    'total_weight_grams' => $totalGrams,
                                ]);

                                $goods += $subtotal;
                                $grams += $totalGrams;
                            }
                        } else {
                            $goods = (float) $data['goods_total'];
                            $grams = (int) round(((float) $data['total_weight_kg']) * 1000);
                        }

                        $record->update([
                            'total' => $goods,
                            'service_fee' => (float) $data['service_fee'],
                            'total_weight_grams' => $grams,
                        ]);
                    });

                    Notification::make()->title('Hasil belanja tersimpan')->success()->send();
                }),

            // 4. Kirim harga -> buat order + payment
            Action::make('kirimHarga')
                ->label('Kirim Harga')
                ->icon('heroicon-o-paper-airplane')
                ->color('success')
                ->visible(fn(ShoppingRequest $record) => $record->status === 'shopping'
                    && $record->total > 0
                    && ! $record->order()->exists())
                ->requiresConfirmation()
                ->modalHeading('Kirim harga ke customer?')
                ->modalDescription(fn(ShoppingRequest $record) => 'Total tagihan Rp '
                    . number_format($record->total + $record->service_fee, 0, ',', '.')
                    . '. Sistem akan membuat pesanan dan tagihan pembayaran (menunggu pembayaran).')
                ->action(function (ShoppingRequest $record) {

                    $order = DB::transaction(function () use ($record) {
                        $req = ShoppingRequest::query()
                            ->whereKey($record->getKey())
                            ->lockForUpdate()
                            ->first();

                        if ($req->status !== 'shopping' || $req->order()->exists()) {
                            return null;
                        }

                        $items = $req->items;
                        $grandTotal = $req->total + $req->service_fee;
                        $grams = $items->isNotEmpty()
                            ? (int) $items->sum('total_weight_grams')
                            : (int) $req->total_weight_grams;

                        $order = Order::create([
                            'user_id' => $req->user_id,
                            'market_id' => $req->market_id,
                            'shopping_request_id' => $req->getKey(),
                            'order_number' => 'ORD-' . now()->format('Ymd') . '-' . Str::upper(Str::random(6)),
                            // snapshot alamat
                            'recipient_name' => $req->recipient_name,
                            'recipient_phone' => $req->recipient_phone,
                            'shipping_address' => $req->shipping_address,
                            'shipping_latitude' => $req->shipping_latitude,
                            'shipping_longitude' => $req->shipping_longitude,
                            'subtotal' => $req->total,
                            'delivery_fee' => $req->service_fee,
                            'grand_total' => $grandTotal,
                            'total_weight_grams' => $grams,
                            'status' => 'pending_payment',
                        ]);

                        if ($items->isNotEmpty()) {
                            foreach ($items as $item) {
                                $order->items()->create([
                                    'shopping_request_item_id' => $item->getKey(),
                                    'product_name' => $item->name,
                                    'unit' => $item->unit,
                                    'quantity' => $item->quantity,
                                    'original_price' => $item->unit_price,
                                    'unit_price' => $item->unit_price,
                                    'subtotal' => $item->subtotal,
                                    'weight_grams' => $item->weight_grams,
                                    'total_weight_grams' => $item->total_weight_grams,
                                ]);
                            }
                        } else {
                            // mode "total saja": satu baris ringkasan
                            $order->items()->create([
                                'product_name' => 'Belanjaan Titipan (Ngebelanjain)',
                                'unit' => 'paket',
                                'quantity' => 1,
                                'original_price' => $req->total,
                                'unit_price' => $req->total,
                                'subtotal' => $req->total,
                                'weight_grams' => $grams,
                                'total_weight_grams' => $grams,
                            ]);
                        }

                        Payment::create([
                            'order_id' => $order->getKey(),
                            'payment_number' => 'PAY-' . now()->format('Ymd') . '-' . Str::upper(Str::random(6)),
                            'amount' => $grandTotal,
                            'status' => 'pending',
                            'expired_at' => now()->addDay(),
                        ]);

                        $req->update(['status' => 'price_sent']);

                        return $order;
                    });

                    if (! $order) {
                        Notification::make()->title('Permintaan sudah diproses sebelumnya')->warning()->send();

                        return;
                    }

                    Notification::make()
                        ->title('Harga terkirim')
                        ->body("Pesanan {$order->order_number} dibuat, menunggu pembayaran.")
                        ->success()
                        ->send();

                    $this->redirect(OrderResource::getUrl('view', ['record' => $order]));
                }),

            // Batalkan
            Action::make('batalkan')
                ->label('Batalkan')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->visible(fn(ShoppingRequest $record) => in_array($record->status, ['pending', 'assigned', 'shopping']))
                ->requiresConfirmation()
                ->action(function (ShoppingRequest $record) {
                    $record->update(['status' => 'cancelled']);

                    Notification::make()->title('Permintaan dibatalkan')->success()->send();
                }),
        ];
    }

    private function hasilDefaults(ShoppingRequest $record): array
    {
        $items = $record->items;

        return [
            'mode' => $items->isEmpty() && $record->total > 0 ? 'total' : 'rincian',
            'items' => $items->map(fn($i) => [
                'name' => $i->name,
                'quantity' => (float) $i->quantity,
                'unit' => $i->unit,
                'unit_price' => (float) $i->unit_price,
                'weight_grams' => (int) $i->weight_grams,
            ])->all(),
            'goods_total' => $record->total > 0 ? (float) $record->total : null,
            'total_weight_kg' => $record->total_weight_grams > 0 ? round($record->total_weight_grams / 1000, 2) : null,
            'service_fee' => (float) $record->service_fee,
        ];
    }
}
