<?php

namespace App\Services;

use App\Models\Order;
use App\Models\User;
use App\Models\Voucher;
use App\Models\VoucherUsage;
use DomainException;
use Illuminate\Support\Facades\DB;

class VoucherService
{
    /**
     * Cek voucher tanpa menyimpan apa pun (untuk tombol "Pakai" sebelum bayar).
     * Mengembalikan potongan yang akan didapat, atau melempar DomainException.
     */
    public static function periksa(
        string $kode,
        User $user,
        float $subtotal,
        float $ongkir,
        bool $dariNgebelanjain = false,
    ): array {
        $voucher = Voucher::where('code', self::normalisasiKode($kode))->first();

        if (! $voucher) {
            throw new DomainException('Kode voucher tidak ditemukan.');
        }

        $potongan = self::validasi($voucher, $user, $subtotal, $ongkir, $dariNgebelanjain);

        return [
            'voucher' => $voucher,
            'applies_to' => $voucher->applies_to,
            'potongan' => $potongan,
        ];
    }

    /**
     * Pasang voucher ke pesanan yang belum dibayar: catat pemakaian,
     * isi snapshot di order, hitung ulang grand_total, dan sesuaikan tagihan pending.
     */
    public static function pasangKeOrder(Order $order, string $kode): VoucherUsage
    {
        return DB::transaction(function () use ($order, $kode) {
            $row = Order::whereKey($order->getKey())->lockForUpdate()->firstOrFail();

            if ($row->status !== Order::STATUS_PENDING_PAYMENT) {
                throw new DomainException('Voucher hanya bisa dipakai pada pesanan yang belum dibayar.');
            }

            if ($row->voucher_id !== null || VoucherUsage::where('order_id', $row->getKey())->exists()) {
                throw new DomainException('Pesanan ini sudah memakai voucher.');
            }

            // Kunci baris voucher: semua pemakaian voucher yang sama jadi antre,
            // jadi kuota terakhir dan batas per user tidak bisa diterobos bersamaan.
            $voucher = Voucher::where('code', self::normalisasiKode($kode))->lockForUpdate()->first();

            if (! $voucher) {
                throw new DomainException('Kode voucher tidak ditemukan.');
            }

            $user = User::findOrFail($row->user_id);

            $potongan = self::validasi(
                $voucher,
                $user,
                (float) $row->subtotal,
                (float) $row->delivery_fee,
                $row->shopping_request_id !== null,
            );

            $usage = VoucherUsage::create([
                'voucher_id' => $voucher->getKey(),
                'user_id' => $user->getKey(),
                'order_id' => $row->getKey(),
                'discount_amount' => $potongan,
                'status' => VoucherUsage::STATUS_APPLIED,
            ]);

            $voucherDiscount = $voucher->applies_to === Voucher::APPLIES_SUBTOTAL ? $potongan : 0;
            $deliveryDiscount = $voucher->applies_to === Voucher::APPLIES_DELIVERY_FEE ? $potongan : 0;

            $grandTotal = max(
                0,
                (float) $row->subtotal - $voucherDiscount + (float) $row->delivery_fee - $deliveryDiscount,
            );

            $row->update([
                'voucher_id' => $voucher->getKey(),
                'voucher_code' => $voucher->code,
                'voucher_discount' => $voucherDiscount,
                'delivery_discount' => $deliveryDiscount,
                'grand_total' => $grandTotal,
            ]);

            // Tagihan yang masih menunggu ikut turun, supaya nominal bayar = grand_total
            $row->payments()->where('status', 'pending')->update(['amount' => $grandTotal]);

            return $usage;
        });
    }

    /**
     * Kembalikan kuota dan jatah user. Panggil saat pesanan dibatalkan
     * atau tagihannya kedaluwarsa. Snapshot di order dibiarkan sebagai riwayat.
     */
    public static function lepaskanDariOrder(Order $order): void
    {
        DB::transaction(function () use ($order) {
            VoucherUsage::where('order_id', $order->getKey())
                ->where('status', VoucherUsage::STATUS_APPLIED)
                ->lockForUpdate()
                ->first()
                ?->update(['status' => VoucherUsage::STATUS_RELEASED]);
        });
    }

    /** Semua aturan voucher. Mengembalikan nominal potongan atau melempar DomainException. */
    private static function validasi(
        Voucher $voucher,
        User $user,
        float $subtotal,
        float $ongkir,
        bool $dariNgebelanjain,
    ): float {
        $sekarang = now();

        if (! $voucher->is_active || $sekarang->lt($voucher->starts_at) || $sekarang->gt($voucher->ends_at)) {
            throw new DomainException('Voucher tidak berlaku atau sudah kedaluwarsa.');
        }

        // Ngebelanjain: harga dari harga pasar asli, hanya voucher gratis ongkir yang boleh
        if ($dariNgebelanjain && $voucher->applies_to === Voucher::APPLIES_SUBTOTAL) {
            throw new DomainException('Voucher ini tidak berlaku untuk pesanan Ngebelanjain.');
        }

        if ($subtotal < (float) $voucher->min_purchase) {
            throw new DomainException(
                'Minimal belanja Rp' . number_format((float) $voucher->min_purchase, 0, ',', '.') . ' belum terpenuhi.',
            );
        }

        $dipakai = VoucherUsage::where('voucher_id', $voucher->getKey())
            ->where('status', VoucherUsage::STATUS_APPLIED);

        if ($voucher->quota !== null && (clone $dipakai)->count() >= $voucher->quota) {
            throw new DomainException('Kuota voucher sudah habis.');
        }

        if ((clone $dipakai)->where('user_id', $user->getKey())->count() >= $voucher->per_user_limit) {
            throw new DomainException('Anda sudah mencapai batas pemakaian voucher ini.');
        }

        $basis = $voucher->applies_to === Voucher::APPLIES_SUBTOTAL ? $subtotal : $ongkir;
        $potongan = $voucher->hitungPotongan($basis);

        if ($potongan <= 0) {
            throw new DomainException('Voucher ini tidak memberi potongan pada pesanan Anda.');
        }

        return $potongan;
    }

    private static function normalisasiKode(string $kode): string
    {
        return strtoupper(trim($kode));
    }
}