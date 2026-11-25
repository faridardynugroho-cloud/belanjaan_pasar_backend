<?php

use App\Enums\ApprovalStatus;
use App\Enums\UserRole;
use App\Models\DriverDocumentCheck;
use App\Models\DriverProfile;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

function fakePng(string $name): UploadedFile
{
    $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==');

    return UploadedFile::fake()->createWithContent($name, $png);
}

function validRegistrationPayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'Budi Santoso',
        'email' => 'budi.driver@example.test',
        'password' => 'password123',
        'phone' => '081234567899',
        'gender' => 'male',
        'birth_date' => '1995-05-15',
        'domicile_address' => 'Jl. Kebon Jeruk No. 10, Jakarta',
        'nik' => '3171234567890001',
        'ktp_photo' => fakePng('ktp.png'),
        'selfie_photo' => fakePng('selfie.png'),
        'sim_type' => 'C',
        'sim_number' => '950512345678',
        'sim_photo' => fakePng('sim.png'),
        'sim_expired_at' => now()->addYears(2)->toDateString(),
        'vehicle_type' => 'Motor',
        'vehicle_brand' => 'Honda',
        'vehicle_model' => 'Vario 125',
        'vehicle_plate' => 'B 1234 XYZ',
        'vehicle_color' => 'Hitam',
        'production_year' => 2022,
        'vehicle_photo' => fakePng('vehicle.png'),
        'stnk_photo' => fakePng('stnk.png'),
    ], $overrides);
}

it('mendaftarkan driver lengkap ke users, driver_profiles, driver_documents, driver_vehicles secara transaksional', function () {
    Storage::fake('public');

    $response = $this->postJson('/api/driver/register', validRegistrationPayload());

    $response->assertCreated()
        ->assertJsonStructure([
            'message',
            'token',
            'driver' => [
                'driver_profile_id',
                'approval_status',
                'user',
                'document',
                'vehicle',
                'document_checks',
            ],
        ])
        ->assertJsonPath('driver.approval_status', ApprovalStatus::Pending->value)
        ->assertJsonPath('driver.user.role', UserRole::Driver->value);

    $user = User::where('email', 'budi.driver@example.test')->firstOrFail();
    $profile = $user->driverProfile;

    expect($profile)->not->toBeNull()
        ->and($profile->document)->not->toBeNull()
        ->and($profile->vehicle)->not->toBeNull()
        ->and($profile->vehicle->vehicle_plate)->toBe('B 1234 XYZ')
        ->and(DriverDocumentCheck::where('driver_profile_id', $profile->driver_profile_id)->count())->toBe(5);

    Storage::disk('public')->assertExists($profile->document->ktp_photo);
    Storage::disk('public')->assertExists($profile->document->selfie_photo);
    Storage::disk('public')->assertExists($profile->document->sim_photo);
    Storage::disk('public')->assertExists($profile->vehicle->vehicle_photo);
    Storage::disk('public')->assertExists($profile->vehicle->stnk_photo);
});

it('gagal validasi pendaftaran tidak meninggalkan data parsial di database atau storage', function () {
    Storage::fake('public');

    $response = $this->postJson('/api/driver/register', validRegistrationPayload([
        'nik' => '123', // invalid: bukan 16 digit
        'sim_type' => 'A', // invalid: wajib SIM C
        'sim_expired_at' => now()->subDay()->toDateString(), // invalid: kedaluwarsa
    ]));

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['nik', 'sim_type', 'sim_expired_at']);

    expect(User::where('email', 'budi.driver@example.test')->exists())->toBeFalse()
        ->and(DriverProfile::count())->toBe(0)
        ->and(Storage::disk('public')->allFiles())->toBeEmpty();
});

it('admin dapat melihat daftar, detail, menyetujui, dan menolak pendaftar driver', function () {
    Storage::fake('public');

    $this->postJson('/api/driver/register', validRegistrationPayload())->assertCreated();
    $this->postJson('/api/driver/register', validRegistrationPayload([
        'email' => 'siti.driver@example.test',
        'phone' => '081234567898',
        'nik' => '3171234567890002',
        'vehicle_plate' => 'B 5678 ABC',
    ]))->assertCreated();

    $admin = User::factory()->create(['role' => UserRole::Admin->value]);
    $profiles = DriverProfile::orderBy('driver_profile_id')->get();

    $this->actingAs($admin, 'sanctum')
        ->getJson('/api/admin/drivers?approval_status=pending')
        ->assertOk()
        ->assertJsonCount(2, 'data');

    $this->actingAs($admin, 'sanctum')
        ->getJson('/api/admin/drivers/'.$profiles[0]->driver_profile_id)
        ->assertOk()
        ->assertJsonPath('data.driver_profile_id', $profiles[0]->driver_profile_id);

    $this->actingAs($admin, 'sanctum')
        ->postJson('/api/admin/drivers/'.$profiles[0]->driver_profile_id.'/approve')
        ->assertOk()
        ->assertJsonPath('driver.approval_status', ApprovalStatus::Approved->value);

    expect($profiles[0]->fresh())
        ->approval_status->toBe(ApprovalStatus::Approved->value)
        ->reviewed_by->toBe($admin->user_id)
        ->reviewed_at->not->toBeNull();

    // Reject wajib rejection_reason
    $this->actingAs($admin, 'sanctum')
        ->postJson('/api/admin/drivers/'.$profiles[1]->driver_profile_id.'/reject', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['rejection_reason']);

    $this->actingAs($admin, 'sanctum')
        ->postJson('/api/admin/drivers/'.$profiles[1]->driver_profile_id.'/reject', [
            'rejection_reason' => 'Foto KTP buram',
        ])
        ->assertOk()
        ->assertJsonPath('driver.approval_status', ApprovalStatus::Rejected->value)
        ->assertJsonPath('driver.rejection_reason', 'Foto KTP buram');
});

it('driver pending atau rejected ditolak mengakses endpoint operasional driver', function () {
    $pendingDriver = DriverProfile::factory()->create(['approval_status' => ApprovalStatus::Pending->value]);
    $rejectedDriver = DriverProfile::factory()->rejected()->create();

    $this->actingAs($pendingDriver->user, 'sanctum')
        ->getJson('/api/driver/me')
        ->assertOk()
        ->assertJsonPath('driver.approval_status', ApprovalStatus::Pending->value);

    $this->actingAs($pendingDriver->user, 'sanctum')
        ->patchJson('/api/driver/availability', ['is_available' => true])
        ->assertForbidden()
        ->assertJsonPath('error_code', 'DRIVER_NOT_APPROVED');

    $this->actingAs($rejectedDriver->user, 'sanctum')
        ->putJson('/api/driver/location', ['latitude' => -6.2, 'longitude' => 106.8])
        ->assertForbidden()
        ->assertJsonPath('error_code', 'DRIVER_NOT_APPROVED');
});

it('non-admin tidak boleh mengakses endpoint verifikasi admin', function () {
    $driver = DriverProfile::factory()->approved()->create();

    $this->actingAs($driver->user, 'sanctum')
        ->getJson('/api/admin/drivers')
        ->assertForbidden();
});
