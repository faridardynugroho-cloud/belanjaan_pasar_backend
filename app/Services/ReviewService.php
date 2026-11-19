<?php

namespace App\Services;

use App\Models\DeliveryOrder;
use App\Models\DriverProfile;
use App\Models\DriverReview;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductReview;
use App\Models\ShoppingRequestReview;
use App\Models\User;
use Carbon\Carbon;
use DomainException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class ReviewService
{
    public static function nilaiProduk(User $user, OrderItem $item, int $rating, ?string $komentar = null): ProductReview
    {
        self::pastikanRatingValid($rating);

        return DB::transaction(function () use ($user, $item, $rating, $komentar) {
            $item = OrderItem::with(['order', 'marketProduct'])->findOrFail($item->getKey());

            self::pastikanPemilik($user, $item->order);
            self::pastikanBolehMenilai($item->order);

            if ($item->order->shopping_request_id) {
                throw new DomainException('Pesanan Ngebelanjain dinilai secara keseluruhan, bukan per barang.');
            }

            if (! $item->marketProduct) {
                throw new DomainException('Barang ini tidak bisa dinilai.');
            }

            if (ProductReview::where('order_item_id', $item->getKey())->exists()) {
                throw new DomainException('Barang ini sudah dinilai.');
            }

            $review = ProductReview::create([
                'order_item_id' => $item->getKey(),
                'user_id' => $user->getKey(),
                'product_id' => $item->marketProduct->product_id,
                'rating' => $rating,
                'comment' => $komentar,
            ]);

            self::hitungUlangProduk($review->product_id);

            return $review;
        });
    }

    public static function nilaiDriver(User $user, DeliveryOrder $deliveryOrder, int $rating, ?string $komentar = null): DriverReview
    {
        self::pastikanRatingValid($rating);

        return DB::transaction(function () use ($user, $deliveryOrder, $rating, $komentar) {
            $row = DeliveryOrder::with(['order', 'delivery'])->findOrFail($deliveryOrder->getKey());

            self::pastikanPemilik($user, $row->order);

            if ($row->status !== DeliveryOrder::STATUS_COMPLETED) {
                throw new DomainException('Pengantaran pesanan ini belum selesai.');
            }

            self::pastikanMasihDalamBatas($row->completed_at);

            if (DriverReview::where('delivery_order_id', $row->getKey())->exists()) {
                throw new DomainException('Pengantaran ini sudah dinilai.');
            }

            $review = DriverReview::create([
                'delivery_order_id' => $row->getKey(),
                'user_id' => $user->getKey(),
                'driver_profile_id' => $row->delivery->driver_profile_id,
                'rating' => $rating,
                'comment' => $komentar,
            ]);

            self::hitungUlangDriver($review->driver_profile_id);

            return $review;
        });
    }

    // Rating keseluruhan pesanan Ngebelanjain
    public static function nilaiNgebelanjain(User $user, Order $order, int $rating, ?string $komentar = null): ShoppingRequestReview
    {
        self::pastikanRatingValid($rating);

        return DB::transaction(function () use ($user, $order, $rating, $komentar) {
            $order = Order::findOrFail($order->getKey());

            self::pastikanPemilik($user, $order);

            if (! $order->shopping_request_id) {
                throw new DomainException('Rating ini khusus pesanan Ngebelanjain.');
            }

            self::pastikanBolehMenilai($order);

            if (ShoppingRequestReview::where('order_id', $order->getKey())->exists()) {
                throw new DomainException('Pesanan ini sudah dinilai.');
            }

            return ShoppingRequestReview::create([
                'order_id' => $order->getKey(),
                'user_id' => $user->getKey(),
                'rating' => $rating,
                'comment' => $komentar,
            ]);
        });
    }

    // Customer mengubah rating miliknya, hanya dalam jendela waktu edit
    public static function ubah(Model $review, User $user, int $rating, ?string $komentar = null): void
    {
        self::pastikanRatingValid($rating);

        DB::transaction(function () use ($review, $user, $rating, $komentar) {
            $review = $review::whereKey($review->getKey())->lockForUpdate()->firstOrFail();

            if ((int) $review->user_id !== (int) $user->getKey()) {
                throw new DomainException('Rating ini bukan milik Anda.');
            }

            if (! $review->isEditable()) {
                throw new DomainException('Rating hanya bisa diubah dalam ' . config('review.edit_hours') . ' jam setelah dikirim.');
            }

            $review->update(['rating' => $rating, 'comment' => $komentar]);

            self::hitungUlang($review);
        });
    }

    // Moderasi admin: sembunyikan / tampilkan. Yang disembunyikan tidak masuk rata-rata.
    public static function setTampil(Model $review, bool $tampil): void
    {
        DB::transaction(function () use ($review, $tampil) {
            $review->update(['is_visible' => $tampil]);

            self::hitungUlang($review);
        });
    }

    private static function hitungUlang(Model $review): void
    {
        match (true) {
            $review instanceof ProductReview => self::hitungUlangProduk($review->product_id),
            $review instanceof DriverReview => self::hitungUlangDriver($review->driver_profile_id),
            default => null, // rating Ngebelanjain tidak punya agregat
        };
    }

    private static function hitungUlangProduk(int $productId): void
    {
        $stat = ProductReview::where('product_id', $productId)->visible()
            ->selectRaw('COUNT(*) AS jumlah, COALESCE(AVG(rating), 0) AS rata')
            ->first();

        Product::whereKey($productId)->update([
            'rating_count' => (int) $stat->jumlah,
            'rating_avg' => round((float) $stat->rata, 2),
        ]);
    }

    private static function hitungUlangDriver(int $driverProfileId): void
    {
        $stat = DriverReview::where('driver_profile_id', $driverProfileId)->visible()
            ->selectRaw('COUNT(*) AS jumlah, COALESCE(AVG(rating), 0) AS rata')
            ->first();

        DriverProfile::whereKey($driverProfileId)->update([
            'rating_count' => (int) $stat->jumlah,
            'rating_avg' => round((float) $stat->rata, 2),
        ]);
    }

    private static function pastikanRatingValid(int $rating): void
    {
        if ($rating < 1 || $rating > 5) {
            throw new DomainException('Rating harus antara 1 sampai 5.');
        }
    }

    private static function pastikanPemilik(User $user, Order $order): void
    {
        if ((int) $order->user_id !== (int) $user->getKey()) {
            throw new DomainException('Pesanan ini bukan milik Anda.');
        }
    }

    private static function pastikanBolehMenilai(Order $order): void
    {
        if ($order->status !== Order::STATUS_COMPLETED) {
            throw new DomainException('Pesanan belum selesai.');
        }

        self::pastikanMasihDalamBatas(self::waktuSelesai($order));
    }

    // orders tidak punya completed_at, jadi diambil dari pengantaran yang selesai
    private static function waktuSelesai(Order $order): ?Carbon
    {
        return DeliveryOrder::where('order_id', $order->getKey())
            ->where('status', DeliveryOrder::STATUS_COMPLETED)
            ->latest('completed_at')
            ->first()?->completed_at;
    }

    private static function pastikanMasihDalamBatas(?Carbon $selesai): void
    {
        if ($selesai && $selesai->copy()->addDays((int) config('review.submit_days'))->isPast()) {
            throw new DomainException('Batas waktu memberi rating (' . config('review.submit_days') . ' hari) sudah lewat.');
        }
    }
}