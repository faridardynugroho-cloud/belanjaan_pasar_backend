<?php

namespace App\Services;

use App\Models\DriverDocumentCheck;
use App\Models\DriverProfile;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Alur verifikasi pendaftaran driver:
 * pending -> (admin menilai tiap dokumen) -> approved | revision | rejected
 * revision -> (driver unggah ulang, ajukanUlang) -> pending
 *
 * Semua perubahan lewat service ini supaya aturan tidak tersebar di UI atau API.
 * Pelanggaran aturan dilempar sebagai DomainException dengan pesan yang siap ditampilkan.
 */
class DriverVerificationService
{
    /** Status pendaftaran yang masih boleh dinilai admin. */
    private const BISA_DINILAI = [DriverProfile::APPROVAL_PENDING, DriverProfile::APPROVAL_REVISION];

    /** Pastikan kelima baris pemeriksaan ada. Dipanggil saat driver mendaftar dan saat halaman periksa dibuka. */
    public static function pastikanPemeriksaan(DriverProfile $driver): void
    {
        foreach (DriverDocumentCheck::ITEMS as $item) {
            DriverDocumentCheck::firstOrCreate([
                'driver_profile_id' => $driver->getKey(),
                'item' => $item,
            ]);
        }
    }

    /** Nilai satu dokumen: valid atau tidak valid (tidak valid wajib disertai catatan perbaikan). */
    public static function nilaiDokumen(User $admin, DriverDocumentCheck $pemeriksaan, string $status, ?string $catatan = null): DriverDocumentCheck
    {
        if (! in_array($status, [DriverDocumentCheck::STATUS_VALID, DriverDocumentCheck::STATUS_INVALID], true)) {
            throw new DomainException('Penilaian dokumen tidak dikenal.');
        }

        $catatan = $catatan !== null ? trim($catatan) : null;

        if ($status === DriverDocumentCheck::STATUS_INVALID && ($catatan === null || $catatan === '')) {
            throw new DomainException('Catatan wajib diisi supaya driver tahu apa yang harus diperbaiki.');
        }

        return DB::transaction(function () use ($admin, $pemeriksaan, $status, $catatan) {
            $driver = DriverProfile::query()->lockForUpdate()->findOrFail($pemeriksaan->driver_profile_id);

            if (! in_array($driver->approval_status, self::BISA_DINILAI, true)) {
                throw new DomainException('Pendaftaran ini sudah diputuskan, dokumennya tidak bisa dinilai lagi.');
            }

            $row = DriverDocumentCheck::findOrFail($pemeriksaan->getKey());

            if ($status === DriverDocumentCheck::STATUS_VALID && $row->pathFoto() === null) {
                throw new DomainException('Dokumen ' . DriverDocumentCheck::labelItem($row->item) . ' belum diunggah driver.');
            }

            $row->update([
                'status' => $status,
                'note' => $status === DriverDocumentCheck::STATUS_INVALID ? $catatan : null,
                'checked_by' => $admin->getKey(),
                'checked_at' => now(),
            ]);

            return $row->refresh();
        });
    }

    /** Setujui pendaftaran. Semua dokumen harus valid dan SIM belum kedaluwarsa. */
    public static function setujui(User $admin, DriverProfile $driver): DriverProfile
    {
        return DB::transaction(function () use ($admin, $driver) {
            $row = DriverProfile::query()->lockForUpdate()->with('document')->findOrFail($driver->getKey());

            if (! in_array($row->approval_status, self::BISA_DINILAI, true)) {
                throw new DomainException('Pendaftaran ini sudah diputuskan.');
            }

            self::pastikanPemeriksaan($row);

            $belumValid = DriverDocumentCheck::where('driver_profile_id', $row->getKey())
                ->where('status', '!=', DriverDocumentCheck::STATUS_VALID)
                ->pluck('item')
                ->map(fn(string $item) => DriverDocumentCheck::labelItem($item))
                ->all();

            if ($belumValid !== []) {
                throw new DomainException('Masih ada dokumen yang belum valid: ' . implode(', ', $belumValid) . '.');
            }

            if ($row->document?->sim_expired_at?->isPast()) {
                throw new DomainException('SIM driver sudah kedaluwarsa, minta driver memperbaruinya dulu.');
            }

            $row->update([
                'approval_status' => DriverProfile::APPROVAL_APPROVED,
                'rejection_reason' => null,
                'reviewed_by' => $admin->getKey(),
                'reviewed_at' => now(),
            ]);

            return $row->refresh();
        });
    }

    /** Minta driver memperbaiki dokumen. Perlu minimal satu dokumen yang dinilai tidak valid. */
    public static function mintaPerbaikan(User $admin, DriverProfile $driver): DriverProfile
    {
        return DB::transaction(function () use ($admin, $driver) {
            $row = DriverProfile::query()->lockForUpdate()->findOrFail($driver->getKey());

            if ($row->approval_status !== DriverProfile::APPROVAL_PENDING) {
                throw new DomainException('Permintaan perbaikan hanya bisa dikirim untuk pendaftaran yang sedang menunggu.');
            }

            $adaTidakValid = DriverDocumentCheck::where('driver_profile_id', $row->getKey())
                ->where('status', DriverDocumentCheck::STATUS_INVALID)
                ->exists();

            if (! $adaTidakValid) {
                throw new DomainException('Tandai dulu minimal satu dokumen sebagai tidak valid beserta catatannya.');
            }

            $row->update([
                'approval_status' => DriverProfile::APPROVAL_REVISION,
                'rejection_reason' => null,
                'reviewed_by' => $admin->getKey(),
                'reviewed_at' => now(),
            ]);

            return $row->refresh();
        });
    }

    /** Tolak pendaftaran dengan alasan. */
    public static function tolak(User $admin, DriverProfile $driver, string $alasan): DriverProfile
    {
        $alasan = trim($alasan);

        if ($alasan === '') {
            throw new DomainException('Alasan penolakan wajib diisi.');
        }

        return DB::transaction(function () use ($admin, $driver, $alasan) {
            $row = DriverProfile::query()->lockForUpdate()->findOrFail($driver->getKey());

            if (! in_array($row->approval_status, self::BISA_DINILAI, true)) {
                throw new DomainException('Pendaftaran ini sudah diputuskan.');
            }

            $row->update([
                'approval_status' => DriverProfile::APPROVAL_REJECTED,
                'rejection_reason' => $alasan,
                'reviewed_by' => $admin->getKey(),
                'reviewed_at' => now(),
            ]);

            return $row->refresh();
        });
    }

    /**
     * Dipanggil API saat driver selesai memperbaiki dokumen: status kembali ke pending,
     * dokumen yang tadinya tidak valid direset supaya diperiksa ulang.
     */
    public static function ajukanUlang(DriverProfile $driver): DriverProfile
    {
        return DB::transaction(function () use ($driver) {
            $row = DriverProfile::query()->lockForUpdate()->findOrFail($driver->getKey());

            if ($row->approval_status !== DriverProfile::APPROVAL_REVISION) {
                throw new DomainException('Hanya pendaftaran yang diminta perbaikan yang bisa diajukan ulang.');
            }

            DriverDocumentCheck::where('driver_profile_id', $row->getKey())
                ->where('status', DriverDocumentCheck::STATUS_INVALID)
                ->update([
                    'status' => DriverDocumentCheck::STATUS_PENDING,
                    'note' => null,
                    'checked_by' => null,
                    'checked_at' => null,
                ]);

            $row->update(['approval_status' => DriverProfile::APPROVAL_PENDING]);

            return $row->refresh();
        });
    }
}