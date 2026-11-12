<?php

namespace App\Filament\Resources\Payments\Pages;

use App\Filament\Resources\Payments\PaymentResource;
use App\Models\Order;
use App\Models\Payment;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\DB;


class ViewPayment extends ViewRecord
{
    protected static string $resource = PaymentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Konfirmasi manual (webhook telat / gagal)
            Action::make('konfirmasi')
                ->label('Konfirmasi Pembayaran')
                ->icon('heroicon-o-banknotes')
                ->color('success')
                 ->visible(fn (Payment $record) => $record->status === 'pending')
                ->modalDescription('Pakai ini kalau uang sudah benar-benar masuk tapi status belum berubah otomatis. Pastikan dana sudah diterima sebelum konfirmasi.')
                ->fillForm(fn (Payment $record) => [
                    'payment_method' => $record->payment_method,
                    'transaction_id' => $record->transaction_id,
                ])
                ->schema([
                    TextInput::make('payment_method')
                        ->label('Metode')
                        ->datalist(['Transfer Bank', 'QRIS', 'E-Wallet', 'Tunai'])
                        ->required()
                        ->maxLength(50),
                    TextInput::make('transaction_id')
                        ->label('Referensi / ID transaksi')
                        ->helperText('Opsional, misal nomor referensi transfer.')
                        ->maxLength(255),
                ])
                ->action(function (Payment $record, array $data) {
                    $error = DB::transaction(function () use ($record, $data) {
                        $order = Order::whereKey($record->order_id)->lockForUpdate()->first();
                        $payment = Payment::whereKey($record->getKey())->lockForUpdate()->first();

                        if ($payment->status !== 'pending') {
                            return 'Status pembayaran sudah berubah, muat ulang halaman.';
                        }

                        if ($order->status === 'cancelled') {
                            return 'Pesanan sudah dibatalkan. Refund/pengembalian dana diurus manual.';
                        }

                        if ($order->payments()->where('status', 'paid')->exists()) {
                            return 'Pesanan ini sudah punya pembayaran berhasil.';
                        }

                        $payment->update([
                            'status' => 'paid',
                            'paid_at' => now(),
                            'payment_method' => $data['payment_method'],
                            'transaction_id' => $data['transaction_id'] ?: $payment->transaction_id,
                        ]);

                        // Tutup tagihan pending lain di pesanan yang sama
                        $order->payments()
                            ->where('payment_id', '!=', $payment->getKey())
                            ->where('status', 'pending')
                            ->update(['status' => 'cancelled']);

                        if ($order->status === 'pending_payment') {
                            $order->update(['status' => 'paid']);
                        }

                        return null;
                    });

                    if ($error) {
                        Notification::make()->title('Gagal konfirmasi')->body($error)->danger()->send();

                        return;
                    }

                    $record->refresh();

                    Notification::make()->title('Pembayaran dikonfirmasi')->success()->send();
                }),
            
        ];
    }
}