<?php

namespace App\Services;

use App\Models\MarketProduct;
use App\Models\ProductDiscount;
use Carbon\CarbonInterface;

class DiskonProdukService
{
    /**
     * Snapshot harga satu item untuk order_items. Panggil saat checkout,
     * lalu gabungkan hasilnya ke $order->items()->create([...]).
     */
    public static function snapshotItem(MarketProduct $marketProduct, float $quantity): array
    {
        $marketProduct->loadMissing('activeDiscount');

        $harga = (float) $marketProduct->price;
        $diskon = $marketProduct->activeDiscount;
        $potongan = $diskon ? $diskon->hitungPotongan($harga) : 0.0;
        $hargaAkhir = $harga - $potongan;

        return [
            'product_discount_id' => $potongan > 0 ? $diskon->getKey() : null,
            'original_price' => $harga,
            'discount_amount' => $potongan,
            'unit_price' => $hargaAkhir,
            'subtotal' => round($hargaAkhir * $quantity, 2),
        ];
    }

    /**
     * Dipakai validasi di admin: apakah periode ini bentrok dengan diskon aktif lain
     * di produk pasar yang sama? (aturan: satu diskon aktif per waktu)
     */
    public static function adaTumpangTindih(
        int $marketProductId,
        CarbonInterface $mulai,
        CarbonInterface $selesai,
        ?int $kecualiId = null,
    ): bool {
        return ProductDiscount::query()
            ->where('market_product_id', $marketProductId)
            ->where('is_active', true)
            ->when($kecualiId, fn ($q) => $q->where('product_discount_id', '!=', $kecualiId))
            ->where('starts_at', '<=', $selesai)
            ->where('ends_at', '>=', $mulai)
            ->exists();
    }
}