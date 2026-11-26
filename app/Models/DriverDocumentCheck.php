<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;

class DriverDocumentCheck extends Model
{
    public const ITEM_KTP = 'ktp';

    public const ITEM_SELFIE = 'selfie';

    public const ITEM_SIM = 'sim';

    public const ITEM_VEHICLE = 'vehicle';

    public const ITEM_STNK = 'stnk';

    /** Urutan tampil. */
    public const ITEMS = [
        self::ITEM_KTP,
        self::ITEM_SELFIE,
        self::ITEM_SIM,
        self::ITEM_VEHICLE,
        self::ITEM_STNK,
    ];

    public const STATUS_PENDING = 'pending';

    public const STATUS_VALID = 'valid';

    public const STATUS_INVALID = 'invalid';

    protected $primaryKey = 'driver_document_check_id';

    protected $fillable = [
        'driver_profile_id',
        'item',
        'status',
        'note',
        'checked_by',
        'checked_at',
    ];

    protected function casts(): array
    {
        return [
            'checked_at' => 'datetime',
        ];
    }

    public static function labelItem(string $item): string
    {
        return match ($item) {
            self::ITEM_KTP => 'KTP',
            self::ITEM_SELFIE => 'Selfie Wajah',
            self::ITEM_SIM => 'SIM',
            self::ITEM_VEHICLE => 'Foto kendaraan',
            self::ITEM_STNK => 'STNK',
            default => $item,
        };
    }

    public function driverProfile(): BelongsTo
    {
        return $this->belongsTo(DriverProfile::class, 'driver_profile_id', 'driver_profile_id');
    }

    public function checker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'checked_by', 'user_id');
    }

    /** Path file yang diunggah driver untuk dokumen ini, null kalau belum ada. */
    public function pathFoto(): ?string
    {
        $driver = $this->driverProfile;

        return match ($this->item) {
            self::ITEM_KTP => $driver?->document?->ktp_photo,
            self::ITEM_SELFIE => $driver?->document?->selfie_photo,
            self::ITEM_SIM => $driver?->document?->sim_photo,
            self::ITEM_VEHICLE => $driver?->vehicle?->vehicle_photo,
            self::ITEM_STNK => $driver?->vehicle?->stnk_photo,
            default => null,
        };
    }

    public function urlFoto(): ?string
    {
        $path = $this->pathFoto();

        if (! $path) {
            return null;
        }

        /** @var FilesystemAdapter $disk */
        $disk = Storage::disk('public');

        return $disk->url($path);
    }

    /** Data teks yang perlu dicocokkan admin dengan fotonya. */
    public function keterangan(): ?string
    {
        $driver = $this->driverProfile;

        return match ($this->item) {
            self::ITEM_KTP => $driver?->document ? 'NIK '.$driver->document->nik : null,
            self::ITEM_SIM => $driver?->document
                ? 'No. SIM '.$driver->document->sim_number
                    .', berlaku sampai '.$driver->document->sim_expired_at?->format('d M Y')
                    .($driver->document->sim_expired_at?->isPast() ? ' (KEDALUWARSA)' : '')
                : null,
            self::ITEM_VEHICLE => $driver?->vehicle
                ? $driver->vehicle->vehicle_brand.' '.$driver->vehicle->vehicle_model
                    .', '.$driver->vehicle->vehicle_color.', '.$driver->vehicle->production_year
                : null,
            self::ITEM_STNK => $driver?->vehicle ? 'Plat '.$driver->vehicle->vehicle_plate : null,
            default => null,
        };
    }
}
