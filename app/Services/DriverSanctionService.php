<?php

namespace App\Services;

use App\Models\DeliveryOrder;
use App\Models\DriverProfile;
use App\Models\DriverSanction;
use App\Models\DriverViolation;
use App\Models\User;
use App\Models\ViolationType;
use DomainException;
use Illuminate\Support\Facades\DB;

class DriverSanctionService
{
    // ===== Laporan pelanggaran =====

    public static function laporkanDriver(
        User $customer,
        DeliveryOrder $deliveryOrder,
        ViolationType $jenis,
        string $deskripsi,
        ?string $buktiPath = null,
    ): DriverViolation {
        return DB::transaction(function () use ($customer, $deliveryOrder, $jenis, $deskripsi, $buktiPath) {
            $row = DeliveryOrder::with(['order', 'delivery'])->findOrFail($deliveryOrder->getKey());

            if ((int) $row->order->user_id !== (int) $customer->getKey()) {
                throw new DomainException('Pesanan ini bukan milik Anda.');
            }

            if (! in_array($row->status, [DeliveryOrder::STATUS_ON_THE_WAY, DeliveryOrder::STATUS_COMPLETED], true)) {
                throw new DomainException('Pengantaran ini belum berjalan, belum bisa dilaporkan.');
            }

            self::pastikanJenisAktif($jenis);

            $sudahAda = DriverViolation::where('delivery_order_id', $row->getKey())
                ->where('reported_by', $customer->getKey())
                ->where('violation_type_id', $jenis->getKey())
                ->exists();

            if ($sudahAda) {
                throw new DomainException('Anda sudah melaporkan hal yang sama untuk pengantaran ini.');
            }

            return DriverViolation::create([
                'driver_profile_id' => $row->delivery->driver_profile_id,
                'delivery_order_id' => $row->getKey(),
                'violation_type_id' => $jenis->getKey(),
                'source' => DriverViolation::SOURCE_CUSTOMER,
                'reported_by' => $customer->getKey(),
                'description' => $deskripsi,
                'evidence_photo' => $buktiPath,
                'status' => DriverViolation::STATUS_REPORTED,
            ]);
        });
    }

    public static function laporkanOlehAdmin(
        User $admin,
        DriverProfile $driver,
        ViolationType $jenis,
        string $deskripsi,
        ?DeliveryOrder $deliveryOrder = null,
        ?string $buktiPath = null,
    ): DriverViolation {
        self::pastikanJenisAktif($jenis);

        if ($deliveryOrder) {
            $milikDriver = DeliveryOrder::whereKey($deliveryOrder->getKey())
                ->whereHas('delivery', fn ($q) => $q->where('driver_profile_id', $driver->getKey()))
                ->exists();

            if (! $milikDriver) {
                throw new DomainException('Pengantaran itu bukan milik driver ini.');
            }
        }

        return DriverViolation::create([
            'driver_profile_id' => $driver->getKey(),
            'delivery_order_id' => $deliveryOrder?->getKey(),
            'violation_type_id' => $jenis->getKey(),
            'source' => DriverViolation::SOURCE_ADMIN,
            'reported_by' => $admin->getKey(),
            'description' => $deskripsi,
            'evidence_photo' => $buktiPath,
            'status' => DriverViolation::STATUS_REPORTED,
        ]);
    }

    // ===== Validasi / tolak / batalkan =====

    /** Validasi laporan: poin diberikan, lalu dicek apakah total poin memicu sanksi. */
    public static function validasiLaporan(User $admin, DriverViolation $laporan, ?int $poin = null): DriverViolation
    {
        return DB::transaction(function () use ($admin, $laporan, $poin) {
            $driver = self::kunciDriver($laporan->driver_profile_id);
            $row = DriverViolation::with('violationType')->findOrFail($laporan->getKey());

            if ($row->status !== DriverViolation::STATUS_REPORTED) {
                throw new DomainException('Laporan ini sudah diproses.');
            }

            $poin ??= $row->violationType->default_points;

            if (! $row->violationType->allowsPoints($poin)) {
                throw new DomainException(sprintf(
                    'Poin harus di antara %d sampai %d.',
                    $row->violationType->min_points,
                    $row->violationType->max_points,
                ));
            }

            $waktu = now();

            $row->update([
                'status' => DriverViolation::STATUS_VALIDATED,
                'points' => $poin,
                'handled_by' => $admin->getKey(),
                'handled_at' => $waktu,
                'points_expires_at' => $waktu->copy()->addMonths(config('driver_sanction.points_valid_months', 1)),
            ]);

            self::evaluasi($driver, $row);

            return $row->refresh();
        });
    }

    public static function tolakLaporan(User $admin, DriverViolation $laporan, string $catatan): DriverViolation
    {
        if (trim($catatan) === '') {
            throw new DomainException('Alasan penolakan wajib diisi.');
        }

        return DB::transaction(function () use ($admin, $laporan, $catatan) {
            self::kunciDriver($laporan->driver_profile_id);
            $row = DriverViolation::findOrFail($laporan->getKey());

            if ($row->status !== DriverViolation::STATUS_REPORTED) {
                throw new DomainException('Laporan ini sudah diproses.');
            }

            $row->update([
                'status' => DriverViolation::STATUS_REJECTED,
                'handled_by' => $admin->getKey(),
                'handled_at' => now(),
                'handle_note' => $catatan,
            ]);

            return $row->refresh();
        });
    }

    /**
     * Batalkan validasi yang salah. Poin hilang dan sanksi yang dipicu laporan ini ikut dicabut.
     * Poin dan sanksi dari laporan lain tidak disentuh. Sanksi lama yang sempat diganti oleh sanksi
     * ini dipulihkan selama belum berakhir dan laporan asalnya masih berlaku.
     */
    public static function batalkanValidasi(User $admin, DriverViolation $laporan, string $catatan): DriverViolation
    {
        if (trim($catatan) === '') {
            throw new DomainException('Alasan pembatalan wajib diisi.');
        }

        return DB::transaction(function () use ($admin, $laporan, $catatan) {
            $driver = self::kunciDriver($laporan->driver_profile_id);
            $row = DriverViolation::findOrFail($laporan->getKey());

            if ($row->status !== DriverViolation::STATUS_VALIDATED) {
                throw new DomainException('Hanya laporan yang sudah divalidasi yang bisa dibatalkan.');
            }

            $row->update([
                'status' => DriverViolation::STATUS_REVOKED,
                'revoked_by' => $admin->getKey(),
                'revoked_at' => now(),
                'revoke_note' => $catatan,
            ]);

            self::cabutSanksiDariLaporan($admin, $driver, $row, $catatan);

            return $row->refresh();
        });
    }

    // ===== Sanksi manual =====

    public static function jatuhkanManual(
        User $admin,
        DriverProfile $driver,
        string $tipe,
        ?int $durasiMenit,
        string $alasan,
        ?string $catatan = null,
    ): DriverSanction {
        if (trim($alasan) === '') {
            throw new DomainException('Alasan sanksi wajib diisi.');
        }

        if ($tipe === DriverSanction::TYPE_TEMPORARY) {
            $batas = config('driver_sanction.max_temporary_minutes');

            if ($durasiMenit === null || $durasiMenit < 1 || $durasiMenit > $batas) {
                throw new DomainException('Durasi suspend harus antara 1 menit sampai 60 hari. Lebih lama dari itu pakai sanksi permanen.');
            }
        } elseif (in_array($tipe, [DriverSanction::TYPE_PERMANENT, DriverSanction::TYPE_TERMINATION], true)) {
            $durasiMenit = null;
        } else {
            throw new DomainException('Tipe sanksi tidak dikenal.');
        }

        return DB::transaction(function () use ($admin, $driver, $tipe, $durasiMenit, $alasan, $catatan) {
            $driver = self::kunciDriver($driver->getKey());

            return self::buatSanksi($driver, [
                'type' => $tipe,
                'duration_minutes' => $durasiMenit,
                'reason' => $alasan,
                'notes' => $catatan,
                'imposed_by' => $admin->getKey(),
            ], self::sanksiTerbuka($driver));
        });
    }

    public static function cabutSanksi(User $admin, DriverSanction $sanksi, string $catatan): DriverSanction
    {
        if (trim($catatan) === '') {
            throw new DomainException('Catatan pencabutan wajib diisi.');
        }

        return DB::transaction(function () use ($admin, $sanksi, $catatan) {
            self::kunciDriver($sanksi->driver_profile_id);
            $row = DriverSanction::findOrFail($sanksi->getKey());

            $bisaDicabut = in_array($row->status, [
                DriverSanction::STATUS_WAITING,
                DriverSanction::STATUS_ACTIVE,
                DriverSanction::STATUS_TERMINATED,
            ], true);

            if (! $bisaDicabut) {
                throw new DomainException('Sanksi ini sudah tidak berlaku.');
            }

            $row->update([
                'lifted_at' => now(),
                'lift_reason' => DriverSanction::LIFT_MANUAL,
                'lifted_by' => $admin->getKey(),
                'lift_note' => $catatan,
            ]);

            return $row->refresh();
        });
    }

    // ===== Dipanggil dari alur pengiriman =====

    /**
     * Panggil setiap kali pesanan driver selesai (atau perjalanan dibatalkan).
     * Kalau sudah tidak ada pesanan aktif, sanksi yang menunggu mulai berjalan.
     */
    public static function mulaiSanksiMenunggu(DriverProfile $driver): ?DriverSanction
    {
        return DB::transaction(function () use ($driver) {
            $driver = self::kunciDriver($driver->getKey());

            if (self::masihPunyaPesananAktif($driver)) {
                return null;
            }

            $sanksi = DriverSanction::where('driver_profile_id', $driver->getKey())
                ->waiting()
                ->orderBy('driver_sanction_id')
                ->first();

            if (! $sanksi) {
                return null;
            }

            self::mulai($sanksi);

            return $sanksi->refresh();
        });
    }

    /** Dipakai saat sistem menawarkan order ke driver: false kalau ada sanksi berlaku atau menunggu mulai. */
    public static function bolehMenerimaOrder(DriverProfile $driver): bool
    {
        return ! DriverSanction::where('driver_profile_id', $driver->getKey())->open()->exists();
    }

    // ===== Inti: poin -> tier -> sanksi =====

    /** Cek total poin aktif, jatuhkan atau ganti sanksi kalau tier-nya lebih berat dari yang sedang berjalan. */
    public static function evaluasiSanksi(DriverProfile $driver): ?DriverSanction
    {
        return DB::transaction(function () use ($driver) {
            return self::evaluasi(self::kunciDriver($driver->getKey()), null);
        });
    }

    private static function evaluasi(DriverProfile $driver, ?DriverViolation $pemicu): ?DriverSanction
    {
        $total = $driver->activePoints();
        $tier = self::tierUntukPoin($total);

        if (! $tier) {
            return null;
        }

        $sekarang = self::sanksiTerbuka($driver);

        // Aturan "diganti, bukan diperpanjang": sanksi baru hanya menggantikan kalau lebih berat.
        if ($sekarang) {
            $bobotBaru = self::bobot($tier['type'], $tier['duration_minutes'] ?? null);
            $bobotLama = self::bobot($sekarang->type, $sekarang->duration_minutes);

            if ($bobotBaru <= $bobotLama) {
                return null;
            }
        }

        return self::buatSanksi($driver, [
            'type' => $tier['type'],
            'duration_minutes' => $tier['duration_minutes'] ?? null,
            'reason' => "Akumulasi {$total} poin pelanggaran aktif.",
            'imposed_by' => null, // sistem
            'driver_violation_id' => $pemicu?->getKey(),
        ], $sekarang);
    }

    // ===== Helper privat =====

    private static function kunciDriver(int $driverProfileId): DriverProfile
    {
        return DriverProfile::whereKey($driverProfileId)->lockForUpdate()->firstOrFail();
    }

    private static function sanksiTerbuka(DriverProfile $driver): ?DriverSanction
    {
        return DriverSanction::where('driver_profile_id', $driver->getKey())
            ->open()
            ->orderByDesc('driver_sanction_id')
            ->first();
    }

    private static function tierUntukPoin(int $total): ?array
    {
        return collect(config('driver_sanction.tiers', []))
            ->where('min_points', '<=', $total)
            ->sortByDesc('min_points')
            ->first();
    }

    /** Angka pembanding keparahan: suspend = durasinya, permanen dan putus mitra di atasnya. */
    private static function bobot(string $tipe, ?int $durasiMenit): int
    {
        return match ($tipe) {
            DriverSanction::TYPE_TERMINATION => PHP_INT_MAX,
            DriverSanction::TYPE_PERMANENT => PHP_INT_MAX - 1,
            default => $durasiMenit ?? 0,
        };
    }

    private static function buatSanksi(DriverProfile $driver, array $data, ?DriverSanction $lama): DriverSanction
    {
        $baru = DriverSanction::create($data + [
            'driver_profile_id' => $driver->getKey(),
            'points_at_imposition' => $driver->activePoints(),
            'started_at' => null,
            'expires_at' => null,
        ]);

        if ($lama) {
            $lama->update([
                'lifted_at' => now(),
                'lift_reason' => DriverSanction::LIFT_REPLACED,
                'lifted_by' => null, // diganti otomatis oleh sistem
                'replaced_by' => $baru->getKey(),
            ]);
        }

        // Mulai sekarang kalau driver lagi kosong, kalau tidak tunggu pesanannya selesai.
        if (! self::masihPunyaPesananAktif($driver)) {
            self::mulai($baru);
        }

        return $baru->refresh();
    }

    private static function mulai(DriverSanction $sanksi): void
    {
        $mulai = now();

        $sanksi->update([
            'started_at' => $mulai,
            'expires_at' => $sanksi->duration_minutes
                ? $mulai->copy()->addMinutes($sanksi->duration_minutes)
                : null,
        ]);
    }

    private static function masihPunyaPesananAktif(DriverProfile $driver): bool
    {
        return DeliveryOrder::whereIn('status', [DeliveryOrder::STATUS_ACCEPTED, DeliveryOrder::STATUS_ON_THE_WAY])
            ->whereHas('delivery', fn ($q) => $q->where('driver_profile_id', $driver->getKey()))
            ->exists();
    }

    /** Cabut sanksi yang dipicu laporan ini, lalu pulihkan sanksi lama yang sempat digantikannya. */
    private static function cabutSanksiDariLaporan(User $admin, DriverProfile $driver, DriverViolation $laporan, string $catatan): void
    {
        $sanksiList = DriverSanction::where('driver_violation_id', $laporan->getKey())
            ->open()
            ->get();

        foreach ($sanksiList as $sanksi) {
            $sanksi->update([
                'lifted_at' => now(),
                'lift_reason' => DriverSanction::LIFT_MANUAL,
                'lifted_by' => $admin->getKey(),
                'lift_note' => "Validasi pelanggaran #{$laporan->getKey()} dibatalkan: {$catatan}",
            ]);

            self::pulihkanSanksiYangDiganti($sanksi);
        }

        // Sanksi yang dipulihkan bisa saja masih menunggu, mulai kalau driver sudah tidak punya pesanan aktif.
        self::mulaiSanksiMenunggu($driver);
    }

    /** Hidupkan lagi sanksi lama yang digantikan sanksi ini, kalau masih layak berlaku. */
    private static function pulihkanSanksiYangDiganti(DriverSanction $sanksi): void
    {
        $lama = DriverSanction::with('violation')
            ->where('replaced_by', $sanksi->getKey())
            ->where('lift_reason', DriverSanction::LIFT_REPLACED)
            ->orderByDesc('driver_sanction_id')
            ->first();

        if (! $lama) {
            return;
        }

        // Sudah berakhir sendiri.
        $sudahBerakhir = $lama->started_at !== null
            && $lama->expires_at !== null
            && $lama->expires_at->isPast();

        // Laporan asalnya sendiri sudah tidak berlaku (ditolak atau dibatalkan). Sanksi manual (tanpa laporan) tetap boleh.
        $laporanAsal = $lama->violation;
        $laporanMasihBerlaku = $laporanAsal === null
            || $laporanAsal->status === DriverViolation::STATUS_VALIDATED;

        if ($sudahBerakhir || ! $laporanMasihBerlaku) {
            return;
        }

        $lama->update([
            'lifted_at' => null,
            'lift_reason' => null,
            'lifted_by' => null,
            'replaced_by' => null,
        ]);
    }

    private static function pastikanJenisAktif(ViolationType $jenis): void
    {
        if (! $jenis->is_active) {
            throw new DomainException('Jenis pelanggaran ini sudah tidak aktif.');
        }
    }
}