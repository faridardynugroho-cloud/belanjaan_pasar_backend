# Ringkasan Fitur Driver - Tahap 1: Onboarding & Authentication

## Fitur yang Selesai Dikerjakan

### 1. Autentikasi API Driver (Laravel Sanctum)
- Instalasi dan konfigurasi `laravel/sanctum` untuk autentikasi API berbasis token.
- Integrasi trait `HasApiTokens` pada model `User`.
- Pembuatan tabel migrasi token personal (`personal_access_tokens`).
- Penambahan routing API di `routes/api.php` dan pendaftaran route API di `bootstrap/app.php`.

### 2. Registrasi & Kelengkapan Profil Driver
- **Endpoint**: `POST /api/v1/driver/register`
- **Controller**: `App\Http\Controllers\Api\Driver\AuthController@register`
- **Request Form**: `App\Http\Requests\Api\Driver\RegisterRequest`
- **Fungsi**: 
  - Registrasi user baru dengan role otomatis `driver`.
  - Pembuatan data `DriverProfile` terkait kendaraan (`vehicle_type`, `vehicle_plate`).
  - Status profil awal: `approval_status = pending`, `is_available = false`.
  - Mengembalikan access token autentikasi beserta data user dan profile.

### 3. Login Driver
- **Endpoint**: `POST /api/v1/driver/login`
- **Controller**: `App\Http\Controllers\Api\Driver\AuthController@login`
- **Request Form**: `App\Http\Requests\Api\Driver\LoginRequest`
- **Fungsi**:
  - Validasi kredensial email/password.
  - Memastikan hanya user dengan role `driver` yang dapat login via endpoint ini.
  - Mengembalikan access token baru beserta data user dan profile driver.

### 4. Info Profil & Logout
- **Endpoint Info**: `GET /api/v1/driver/me` (auth:sanctum)
- **Endpoint Logout**: `POST /api/v1/driver/logout` (auth:sanctum)
- **Fungsi**: Mengambil profil driver yang sedang login dan mencabut token saat logout.
