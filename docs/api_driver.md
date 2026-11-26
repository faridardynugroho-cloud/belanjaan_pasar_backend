# Dokumentasi API Driver — BelanjaanPasar

> **Base URL**: `/api`
> **Auth**: Laravel Sanctum — sertakan header `Authorization: Bearer {token}` pada setiap request yang memerlukan autentikasi.
> **Content-Type**: `application/json` (kecuali endpoint upload foto yang menggunakan `multipart/form-data`)
> **Format response error**: `{"error_code": "...", "message": "..."}`

---

## Daftar Isi

1. [Alur Lengkap Driver](#1-alur-lengkap-driver)
2. [Autentikasi Driver](#2-autentikasi-driver)
3. [Profil & Status Approval](#3-profil--status-approval)
4. [Verifikasi Admin](#4-verifikasi-admin)
5. [Lokasi & Ketersediaan](#5-lokasi--ketersediaan)
6. [Delivery Aktif & Riwayat](#6-delivery-aktif--riwayat)
7. [Menerima & Menolak Order](#7-menerima--menolak-order)
8. [Memulai & Menyelesaikan Pengantaran](#8-memulai--menyelesaikan-pengantaran)
9. [Realtime WebSocket](#9-realtime-websocket)
10. [Referensi Error Code](#10-referensi-error-code)
11. [Panduan Penggunaan & Pengujian Postman](#11-panduan-penggunaan--pengujian-postman)

---

## 1. Alur Lengkap Driver

```
[Daftar] → [Admin Approve] → [Online / Kirim Lokasi] → [Dapat Order (Dispatch Otomatis)]
                                                                      ↓
                                                        [Terima / Tolak Order]
                                                                      ↓ (terima)
                                                        [Mulai Pengantaran]
                                                                      ↓
                                                        [Selesaikan tiap Order]
                                                                      ↓
                                                        [Delivery Auto-Complete]
```

### Status Delivery

Status delivery diturunkan dari timestamp (tidak ada kolom `status`):

| Kondisi | Status | Keterangan |
|---|---|---|
| `started_at` NULL, `completed_at` NULL | `preparing` | Masih mengambil pesanan di pasar |
| `started_at` terisi, `completed_at` NULL | `delivering` | Sedang mengantar |
| `completed_at` terisi | `completed` | Semua order selesai |
| `cancelled_at` terisi | `cancelled` | Semua order ditolak driver |

### Status per Order (`delivery_orders.status`)

| Status | Keterangan | Transisi berikutnya |
|---|---|---|
| `pending` | Ditawarkan ke driver, belum dijawab | `accepted` atau `rejected` |
| `accepted` | Driver terima, belum berangkat | `on_the_way` (saat delivery di-start) |
| `on_the_way` | Sedang diantar | `completed` |
| `completed` | Sudah diterima customer | — (final) |
| `rejected` | Driver tolak | — (final, re-dispatch ke driver lain) |

### Aturan Kapasitas

- Maksimal **3 order** per satu delivery
- Total berat seluruh order ≤ **50 kg (50.000 gram)**
- Order tambahan hanya bisa diterima **sebelum** delivery dimulai

---

## 2. Autentikasi Driver

### `POST /api/driver/register`

Mendaftarkan akun driver baru. Data dikirim sebagai **`multipart/form-data`**.

**Request Body:**

| Field | Tipe | Wajib | Validasi |
|---|---|---|---|
| `name` | string | ✅ | Maks 255 karakter |
| `email` | string | ✅ | Format email, unik |
| `password` | string | ✅ | Min 8 karakter |
| `phone` | string | ✅ | Unik, maks 20 karakter |
| `gender` | string | ✅ | `male` atau `female` |
| `birth_date` | date | ✅ | Format `YYYY-MM-DD`, sebelum hari ini |
| `domicile_address` | string | ✅ | Maks 1000 karakter |
| `nik` | string | ✅ | Tepat **16 digit**, unik |
| `ktp_photo` | file | ✅ | jpg/jpeg/png, maks **2 MB** |
| `selfie_photo` | file | ✅ | jpg/jpeg/png, maks **2 MB** |
| `sim_type` | string | ✅ | Harus `C` |
| `sim_number` | string | ✅ | Maks 20 karakter |
| `sim_photo` | file | ✅ | jpg/jpeg/png, maks **2 MB** |
| `sim_expired_at` | date | ✅ | Format `YYYY-MM-DD`, harus **setelah** hari ini |
| `vehicle_type` | string | ✅ | Contoh: `Motor` |
| `vehicle_brand` | string | ✅ | Contoh: `Honda` |
| `vehicle_model` | string | ✅ | Contoh: `Vario 125` |
| `vehicle_plate` | string | ✅ | Unik, otomatis diubah ke **uppercase** |
| `vehicle_color` | string | ✅ | Maks 30 karakter |
| `production_year` | integer | ✅ | Min 1990, maks tahun depan |
| `vehicle_photo` | file | ✅ | jpg/jpeg/png, maks **2 MB** |
| `stnk_photo` | file | ✅ | jpg/jpeg/png, maks **2 MB** |
| `device_name` | string | ❌ | Nama perangkat untuk token (default: `driver-app`) |

**Response `201 Created`:**

```json
{
  "message": "Driver registered successfully",
  "token": "1|AbCdEfGhIjKlMnOpQrStUvWxYz...",
  "driver": {
    "driver_profile_id": 1,
    "user_id": 1,
    "domicile_address": "Jl. Pasar Minggu No. 5",
    "is_available": false,
    "approval_status": "pending",
    "rejection_reason": null,
    "reviewed_at": null,
    "rating_avg": 0,
    "rating_count": 0,
    "user": {
      "user_id": 1,
      "name": "Andi Driver",
      "email": "andi@example.com",
      "phone": "081399990001",
      "gender": "male",
      "birth_date": "1994-03-10",
      "role": "driver"
    },
    "document": {
      "driver_document_id": 1,
      "nik": "3174010101940001",
      "ktp_photo_url": "/storage/driver-documents/ktp/xxxx.png",
      "selfie_photo_url": "/storage/driver-documents/selfie/xxxx.png",
      "sim_number": "940312345678",
      "sim_photo_url": "/storage/driver-documents/sim/xxxx.png",
      "sim_expired_at": "2028-11-25"
    },
    "vehicle": {
      "driver_vehicle_id": 1,
      "vehicle_type": "Motor",
      "vehicle_brand": "Yamaha",
      "vehicle_model": "NMAX",
      "vehicle_plate": "B 9001 XYZ",
      "vehicle_color": "Putih",
      "production_year": 2023,
      "vehicle_photo_url": "/storage/driver-vehicles/vehicle/xxxx.png",
      "stnk_photo_url": "/storage/driver-vehicles/stnk/xxxx.png"
    },
    "document_checks": [
      {"item": "ktp",     "status": "pending", "note": null, "checked_at": null},
      {"item": "selfie",  "status": "pending", "note": null, "checked_at": null},
      {"item": "sim",     "status": "pending", "note": null, "checked_at": null},
      {"item": "vehicle", "status": "pending", "note": null, "checked_at": null},
      {"item": "stnk",    "status": "pending", "note": null, "checked_at": null}
    ]
  }
}
```

> ⚠️ **Simpan `token`** untuk request selanjutnya. Setelah daftar, `approval_status = pending` — driver belum bisa mengakses endpoint operasional sampai admin menyetujui.

---

### `POST /api/driver/login`

Login driver yang sudah terdaftar.

**Request Body (`application/json`):**

```json
{
  "email": "andi@example.com",
  "password": "password123",
  "device_name": "driver-app-android"
}
```

**Response `200 OK`:**

```json
{
  "message": "Login successful",
  "token": "2|AbCdEfGhIjKlMnOpQrStUvWxYz...",
  "driver": { ... }
}
```

**Error `422`** jika email/password salah:
```json
{
  "message": "The given data was invalid.",
  "errors": { "email": ["Invalid credentials"] }
}
```

---

### `POST /api/driver/logout`

**Header**: `Authorization: Bearer {token}`

Mencabut token aktif driver.

**Response `200 OK`:**

```json
{
  "message": "Logged out successfully"
}
```

---

## 3. Profil & Status Approval

### `GET /api/driver/me`

**Header**: `Authorization: Bearer {token}`

Mengambil profil lengkap driver. **Dapat diakses driver dengan status `pending` atau `rejected`** sekalipun (tidak memerlukan approved).

**Response `200 OK`:**

```json
{
  "driver": {
    "driver_profile_id": 1,
    "approval_status": "pending",
    "rejection_reason": null,
    "is_available": false,
    "rating_avg": 4.8,
    "rating_count": 32,
    "reviewed_at": null,
    "user": { ... },
    "document": { ... },
    "vehicle": { ... },
    "document_checks": [
      {"item": "ktp",     "status": "valid",   "note": null, "checked_at": "2026-11-25T..."},
      {"item": "selfie",  "status": "pending",  "note": null, "checked_at": null},
      {"item": "sim",     "status": "invalid",  "note": "Foto buram, silakan upload ulang", "checked_at": "2026-11-25T..."},
      {"item": "vehicle", "status": "pending",  "note": null, "checked_at": null},
      {"item": "stnk",    "status": "pending",  "note": null, "checked_at": null}
    ]
  }
}
```

**Nilai `approval_status`:**

| Nilai | Arti | Dapat akses endpoint operasional? |
|---|---|---|
| `pending` | Menunggu review admin | ❌ |
| `revision` | Admin meminta perbaikan dokumen | ❌ |
| `approved` | Disetujui | ✅ |
| `rejected` | Ditolak permanen | ❌ |

**Nilai `document_checks[].item`:** `ktp` · `selfie` · `sim` · `vehicle` · `stnk`

**Nilai `document_checks[].status`:** `pending` · `valid` · `invalid`

---

## 4. Verifikasi Admin

> Semua endpoint `/api/admin/*` memerlukan `Authorization: Bearer {token}` dengan akun role **`admin`**.

### `GET /api/admin/drivers`

Daftar semua pendaftar driver (paginated, 15 per halaman).

**Query Params:**

| Param | Keterangan |
|---|---|
| `approval_status` | Filter: `pending` / `revision` / `approved` / `rejected` |
| `page` | Nomor halaman (default: 1) |

**Response `200 OK`:**

```json
{
  "data": [
    {
      "driver_profile_id": 1,
      "approval_status": "pending",
      "is_available": false,
      "user": { "name": "Andi Driver", "email": "andi@example.com", ... },
      "document": { "nik": "3174010101940001", ... },
      "vehicle": { "vehicle_plate": "B 9001 XYZ", ... },
      "document_checks": [ ... ]
    }
  ],
  "links": { "first": "...", "last": "...", "prev": null, "next": "..." },
  "meta": { "current_page": 1, "last_page": 3, "per_page": 15, "total": 42 }
}
```

---

### `GET /api/admin/drivers/{driver_profile_id}`

Detail satu pendaftar termasuk semua dokumen.

**Response `200 OK`:**

```json
{
  "data": { /* DriverProfile lengkap */ }
}
```

---

### `POST /api/admin/drivers/{driver_profile_id}/approve`

Menyetujui pendaftaran driver. Endpoint ini secara otomatis menandai semua dokumen yang sudah diunggah sebagai `valid`, lalu mengubah `approval_status` menjadi `approved`.

**Response `200 OK`:**

```json
{
  "message": "Driver approved successfully",
  "driver": {
    "approval_status": "approved",
    "reviewed_at": "2026-11-25T07:00:00+00:00",
    "document_checks": [
      {"item": "ktp", "status": "valid", "checked_at": "2026-11-25T..."},
      ...
    ]
  }
}
```

**Error `422`** jika SIM sudah kedaluwarsa:
```json
{
  "error_code": "VERIFICATION_FAILED",
  "message": "SIM driver sudah kedaluwarsa, minta driver memperbaruinya dulu."
}
```

---

### `POST /api/admin/drivers/{driver_profile_id}/reject`

Menolak pendaftaran. `rejection_reason` **wajib** diisi.

**Request Body:**

```json
{
  "rejection_reason": "Foto KTP buram dan tidak terbaca dengan jelas."
}
```

**Response `200 OK`:**

```json
{
  "message": "Driver rejected successfully",
  "driver": {
    "approval_status": "rejected",
    "rejection_reason": "Foto KTP buram dan tidak terbaca dengan jelas.",
    "reviewed_at": "2026-11-25T07:00:00+00:00"
  }
}
```

**Error `422`** jika `rejection_reason` kosong:
```json
{
  "message": "The given data was invalid.",
  "errors": { "rejection_reason": ["The rejection reason field is required."] }
}
```

---

## 5. Lokasi & Ketersediaan

> Endpoint berikut memerlukan `Authorization: Bearer {token}` **dan** driver sudah berstatus **`approved`**.
> Jika belum approved, response `403`: `{"error_code": "DRIVER_NOT_APPROVED", ...}`

---

### `PATCH /api/driver/availability`

Mengubah status online/offline driver.

**Request Body:**

```json
{
  "is_available": true
}
```

**Response `200 OK`:**

```json
{
  "message": "Availability updated",
  "driver": {
    "driver_profile_id": 1,
    "is_available": true,
    ...
  }
}
```

> ℹ️ Driver harus `is_available = true` **dan** sudah mengirim koordinat GPS agar bisa ditemukan oleh sistem dispatch.

---

### `PUT /api/driver/location`

Mengirim koordinat GPS terbaru. Dipanggil **berkala** dari aplikasi mobile (disarankan setiap **5–10 detik** saat aktif). Hanya **satu baris** per driver yang disimpan (upsert berdasarkan `user_id`).

**Request Body:**

```json
{
  "latitude": -6.2005000,
  "longitude": 106.8005000
}
```

**Validasi:**
- `latitude`: numeric, antara `-90` dan `90`
- `longitude`: numeric, antara `-180` dan `180`

**Response `200 OK`:**

```json
{
  "message": "Location updated",
  "location": {
    "driver_location_id": 1,
    "user_id": 1,
    "latitude": -6.2005,
    "longitude": 106.8005,
    "recorded_at": "2026-11-25T07:00:00+00:00"
  }
}
```

> ℹ️ Setiap update lokasi secara otomatis mem-broadcast event ke WebSocket (lihat [Bagian 9](#9-realtime-websocket)).

---

## 6. Delivery Aktif & Riwayat

### `GET /api/driver/deliveries/current`

Mengambil delivery yang sedang aktif (belum selesai/dibatalkan).

**Response `200 OK`** — saat ada delivery aktif:

```json
{
  "delivery": {
    "delivery_id": 10,
    "driver_profile_id": 1,
    "delivery_number": "DLV-20261125-ABCDEF",
    "status": "preparing",
    "started_at": null,
    "completed_at": null,
    "cancelled_at": null,
    "active_orders_count": 2,
    "active_weight_grams": 25000,
    "delivery_orders": [
      {
        "delivery_order_id": 5,
        "delivery_id": 10,
        "order_id": 42,
        "status": "pending",
        "rejection_reason": null,
        "assigned_at": "2026-11-25T07:00:00+00:00",
        "accepted_at": null,
        "rejected_at": null,
        "started_at": null,
        "completed_at": null,
        "proof_photo_url": null,
        "received_by": null,
        "received_note": null,
        "order": {
          "order_id": 42,
          "order_number": "ORD-20261125-XYZ",
          "recipient_name": "Budi Santoso",
          "recipient_phone": "08123456789",
          "shipping_address": "Jl. Mangga No. 5, Jakarta Selatan",
          "shipping_latitude": -6.2100,
          "shipping_longitude": 106.8100,
          "subtotal": 150000,
          "delivery_fee": 10000,
          "grand_total": 160000,
          "total_weight_grams": 15000,
          "status": "ready_for_delivery",
          "market": {
            "market_id": 1,
            "name": "Pasar Minggu",
            "address": "Jl. Pasar Minggu Raya No. 1",
            "latitude": -6.2000,
            "longitude": 106.8000
          }
        }
      }
    ]
  }
}
```

**Response `200 OK`** — tidak ada delivery aktif:
```json
{
  "delivery": null
}
```

---

### `GET /api/driver/deliveries/history`

Riwayat delivery yang sudah selesai atau dibatalkan. Paginated 15 per halaman.

**Query Params**: `?page=1`

**Response `200 OK`:**

```json
{
  "data": [
    {
      "delivery_id": 9,
      "delivery_number": "DLV-20261124-GHIJKL",
      "status": "completed",
      "started_at": "2026-11-24T08:00:00+00:00",
      "completed_at": "2026-11-24T09:30:00+00:00",
      "cancelled_at": null,
      "active_orders_count": 0,
      "active_weight_grams": 0,
      "delivery_orders": [ ... ]
    }
  ],
  "links": { "first": "...", "last": "...", "prev": null, "next": "..." },
  "meta": { "current_page": 1, "last_page": 5, "per_page": 15, "total": 68 }
}
```

---

## 7. Menerima & Menolak Order

> Order ditawarkan ke driver secara otomatis oleh sistem (dispatch). Driver memeriksa order baru lewat `GET /deliveries/current` atau notifikasi WebSocket.

---

### `POST /api/driver/delivery-orders/{delivery_order_id}/accept`

Menerima order yang berstatus `pending`.

**Aturan:**
- Order harus berstatus `pending`.
- Delivery harus belum dimulai (`started_at IS NULL`).
- Kapasitas delivery belum penuh: jumlah order aktif < 3 **dan** total berat + berat order baru ≤ 50.000 g.
- Menggunakan `lockForUpdate()` untuk mencegah race condition.

**Response `200 OK`:**

```json
{
  "message": "Order diterima",
  "delivery_order": {
    "delivery_order_id": 5,
    "status": "accepted",
    "accepted_at": "2026-11-25T07:05:00+00:00",
    ...
  }
}
```

**Kemungkinan error:**

| Error Code | Keterangan |
|---|---|
| `INVALID_STATUS` | Order bukan `pending` |
| `CAPACITY_EXCEEDED` | Sudah 3 order dalam delivery ini |
| `WEIGHT_EXCEEDED` | Total berat melebihi 50 kg |
| `DELIVERY_ALREADY_STARTED` | Delivery sudah dimulai |
| `NOT_OWNER` | Order ini bukan milik delivery driver ini |

---

### `POST /api/driver/delivery-orders/{delivery_order_id}/reject`

Menolak order yang berstatus `pending`. `rejection_reason` **wajib** diisi.

**Aturan:**
- Hanya order berstatus `pending` yang bisa ditolak.
- Delivery harus belum dimulai.
- Menolak satu order **tidak** membatalkan order lain dalam delivery yang sama.
- Riwayat penolakan **tidak pernah dihapus** dari database.
- Setelah ditolak → sistem otomatis mencari driver lain (**re-dispatch**).
- Jika **semua** order dalam delivery ditolak → `deliveries.cancelled_at` terisi otomatis.

**Request Body:**

```json
{
  "rejection_reason": "Lokasi terlalu jauh dari posisi saya saat ini."
}
```

**Response `200 OK`:**

```json
{
  "message": "Order ditolak",
  "delivery_order": {
    "delivery_order_id": 5,
    "status": "rejected",
    "rejected_at": "2026-11-25T07:06:00+00:00",
    "rejection_reason": "Lokasi terlalu jauh dari posisi saya saat ini.",
    ...
  }
}
```

**Error `422`** jika order sudah `on_the_way`:
```json
{
  "error_code": "INVALID_STATUS",
  "message": "Hanya order berstatus pending yang dapat ditolak."
}
```

**Error `422`** jika delivery sudah dimulai:
```json
{
  "error_code": "DELIVERY_ALREADY_STARTED",
  "message": "Pengantaran sudah dimulai, order tidak dapat ditolak."
}
```

---

## 8. Memulai & Menyelesaikan Pengantaran

### `POST /api/driver/deliveries/{delivery_id}/start`

Memulai perjalanan pengantaran. Semua order yang sudah `accepted` berubah menjadi `on_the_way`.

**Aturan:**
- Minimal ada **1 order berstatus `accepted`**.
- Tidak boleh ada order yang masih `pending` (harus diterima atau ditolak dulu).
- Setelah mulai, driver **tidak bisa menerima order tambahan** di delivery ini.

**Response `200 OK`:**

```json
{
  "message": "Pengantaran dimulai",
  "delivery": {
    "delivery_id": 10,
    "status": "delivering",
    "started_at": "2026-11-25T07:10:00+00:00",
    "delivery_orders": [
      {
        "status": "on_the_way",
        "started_at": "2026-11-25T07:10:00+00:00",
        ...
      }
    ]
  }
}
```

**Error `422`** jika masih ada order `pending`:
```json
{
  "error_code": "PENDING_ORDERS_EXIST",
  "message": "Masih ada order berstatus pending. Terima atau tolak semua order sebelum memulai pengantaran."
}
```

**Error `422`** jika tidak ada order `accepted`:
```json
{
  "error_code": "NO_ACCEPTED_ORDERS",
  "message": "Tidak ada order yang diterima. Terima minimal satu order sebelum memulai pengantaran."
}
```

---

### `POST /api/driver/delivery-orders/{delivery_order_id}/complete`

Menandai satu order selesai diantar. Jika menyertakan foto bukti, kirim sebagai **`multipart/form-data`**.

**Aturan:**
- Order harus berstatus `on_the_way`.
- Setelah order ini selesai, jika tidak ada order lain yang masih aktif, `deliveries.completed_at` terisi otomatis.

**Request Body (semua opsional):**

| Field | Tipe | Keterangan |
|---|---|---|
| `proof_photo` | file | Foto bukti pengantaran (jpg/jpeg/png, maks 2 MB) |
| `received_by` | string | Siapa yang menerima (lihat tabel di bawah) |
| `received_note` | string | Catatan tambahan, maks 255 karakter |

**Nilai `received_by`:**

| Nilai | Keterangan |
|---|---|
| `customer` | Diterima langsung oleh customer |
| `family` | Diterima keluarga/kerabat |
| `neighbor` | Diterima tetangga |
| `front_porch` | Diletakkan di depan teras |
| `fence` | Digantung di pagar |
| `shoe_rack` | Diletakkan di atas rak sepatu |

**Response `200 OK`:**

```json
{
  "message": "Order selesai diantar",
  "delivery_order": {
    "delivery_order_id": 5,
    "status": "completed",
    "completed_at": "2026-11-25T08:00:00+00:00",
    "proof_photo_url": "/storage/delivery-proofs/xxxx.png",
    "received_by": "customer",
    "received_note": "Diterima di depan pintu oleh Pak Budi",
    ...
  }
}
```

> ✅ Jika ini order terakhir yang aktif dalam delivery, `deliveries.completed_at` terisi otomatis dan delivery berstatus `completed`. Driver bisa menerima order baru setelahnya.

---

## 9. Realtime WebSocket

Menggunakan **Laravel Reverb** (kompatibel protokol Pusher).

### Setup Koneksi (Laravel Echo)

```javascript
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

const echo = new Echo({
  broadcaster: 'reverb',
  key: process.env.VITE_REVERB_APP_KEY,
  wsHost: process.env.VITE_REVERB_HOST,
  wsPort: process.env.VITE_REVERB_PORT ?? 8080,
  wssPort: process.env.VITE_REVERB_PORT ?? 443,
  forceTLS: (process.env.VITE_REVERB_SCHEME ?? 'https') === 'https',
  enabledTransports: ['ws', 'wss'],
  authEndpoint: '/broadcasting/auth',
  auth: {
    headers: {
      Authorization: `Bearer ${userToken}`,
      Accept: 'application/json',
    },
  },
});
```

### Channel Lokasi Driver Realtime

**Channel**: `private-driver-location.{driver_user_id}`

**Siapa yang bisa subscribe:**
- Driver itu sendiri
- Admin
- Customer yang memiliki order dengan status `on_the_way` dari driver tersebut

**Subscribe & dengarkan event:**

```javascript
// Contoh untuk customer yang sedang memantau driver-nya
echo.private(`driver-location.${driverUserId}`)
  .listen('.driver.location.updated', (payload) => {
    console.log('Lokasi driver diperbarui:', payload);
    // Contoh payload:
    // {
    //   "driver_user_id": 1,
    //   "driver_profile_id": 1,
    //   "latitude": -6.20050,
    //   "longitude": 106.80050,
    //   "recorded_at": "2026-11-25T07:00:00+00:00"
    // }
    updateMarkerOnMap(payload.latitude, payload.longitude);
  });
```

---

## 10. Referensi Error Code

| `error_code` | HTTP Status | Keterangan |
|---|---|---|
| `UNAUTHORIZED_ROLE` | 403 | User bukan driver (atau bukan admin untuk endpoint admin) |
| `DRIVER_NOT_APPROVED` | 403 | Driver belum `approved` oleh admin |
| `NOT_OWNER` | 403 | Delivery/order bukan milik driver ini |
| `CAPACITY_EXCEEDED` | 422 | Maks 3 order per delivery sudah tercapai |
| `WEIGHT_EXCEEDED` | 422 | Total berat melebihi 50.000 gram |
| `DELIVERY_ALREADY_STARTED` | 422 | Delivery sudah dimulai, tidak bisa ubah lagi |
| `INVALID_STATUS` | 422 | Transisi status tidak diizinkan |
| `PENDING_ORDERS_EXIST` | 422 | Masih ada order `pending` saat mencoba start delivery |
| `NO_ACCEPTED_ORDERS` | 422 | Tidak ada order `accepted` saat mencoba start delivery |
| `DISTANCE_EXCEEDED` | 422 | Jarak pengantaran > 7 km |
| `VERIFICATION_FAILED` | 422 | Verifikasi dokumen driver gagal (endpoint admin) |

---

## 11. Panduan Penggunaan & Pengujian Postman

Untuk mempermudah pengujian API oleh tim backend, mobile developer (React Native), dan QA, telah disediakan file Postman Collection resmi:
📁 **File Koleksi**: `docs/BelanjaanPasar_Driver_API.postman_collection.json`

---

### 11.1 Cara Import Koleksi ke Postman

1. Buka aplikasi **Postman**.
2. Klik tombol **Import** di pojok kiri atas (atau tekan `Ctrl + O` / `Cmd + O`).
3. Pilih opsi **Files** dan arahkan ke file:
   `docs/BelanjaanPasar_Driver_API.postman_collection.json`
4. Klik **Import**. Koleksi dengan nama **"BelanjaanPasar - Driver & Delivery API"** akan muncul di sidebar kiri dengan 4 folder:
   - `1. Auth & Profil Driver`
   - `2. Verifikasi Admin`
   - `3. Lokasi & Operasional Driver`
   - `4. Pengelolaan Order & Perjalanan`

---

### 11.2 Variabel Koleksi (Variables)

Koleksi ini menggunakan variabel untuk mempermudah pergantian URL dan pengelolaan token otomatis:

| Variabel | Default Value | Keterangan |
|---|---|---|
| `baseUrl` | `http://localhost:8000/api` | Alamat host backend API |
| `driverToken` | *(kosong)* | Bearer token milik driver (terisi otomatis setelah login/register) |
| `adminToken` | *(kosong)* | Bearer token milik admin untuk aksi approve/reject pendaftar |

> 💡 **Tips**: Anda dapat mengecek atau mengubah nilai variabel ini dengan cara klik nama koleksi **BelanjaanPasar - Driver & Delivery API** → buka tab **Variables**.

---

### 11.3 Skrip Otomatisasi Token & ID (Postman Tests)

Agar pengujian berjalan mulus tanpa perlu copy-paste token manual:

#### A. Auto-save `driverToken` pada request `Register Driver` & `Login Driver`
Buka request **Register Driver** atau **Login Driver**, masuk ke tab **Scripts** → **Post-response (Tests)**, dan pastikan terdapat kode berikut:
```javascript
if (pm.response.code === 200 || pm.response.code === 201) {
    const res = pm.response.json();
    if (res.token) {
        pm.collectionVariables.set("driverToken", res.token);
        console.log("driverToken tersimpan:", res.token);
    }
    if (res.driver && res.driver.driver_profile_id) {
        pm.collectionVariables.set("driverProfileId", res.driver.driver_profile_id);
    }
}
```

#### B. Auto-save `deliveryId` & `deliveryOrderId` pada `Ambil Delivery Aktif (/current)`
Buka request **Ambil Delivery Aktif (/current)** → tab **Scripts** → **Post-response (Tests)**:
```javascript
if (pm.response.code === 200) {
    const res = pm.response.json();
    if (res.delivery) {
        pm.collectionVariables.set("deliveryId", res.delivery.delivery_id);
        if (res.delivery.delivery_orders && res.delivery.delivery_orders.length > 0) {
            pm.collectionVariables.set("deliveryOrderId", res.delivery.delivery_orders[0].delivery_order_id);
            console.log("IDs tersimpan: deliveryId=" + res.delivery.delivery_id + ", deliveryOrderId=" + res.delivery.delivery_orders[0].delivery_order_id);
        }
    }
}
```

---

### 11.4 Urutan Skenario Pengujian Lengkap (Happy Path)

Jalankan request secara berurutan sesuai skenario operasional berikut:

```
[1. Register] ➔ [2. Cek /me] ➔ [3. Admin Approve] ➔ [4. Online & Lokasi]
                                                               ↓
[8. Riwayat] 🠔 [7. Complete Order] 🠔 [6. Start Delivery] 🠔 [5. Accept Order]
```

#### Langkah 1: Registrasi Driver
- Buka folder `1. Auth & Profil Driver` → pilih **Register Driver (Multipart)**.
- Pada tab **Body** (`form-data`):
  - Isi data diri driver (nama, email, password, NIK, plat kendaraan, dsb).
  - Pada key bertipe file (`ktp_photo`, `selfie_photo`, `sim_photo`, `vehicle_photo`, `stnk_photo`), klik dan pilih gambar dummy (format `.png` atau `.jpg`, ukuran < 2MB).
- Klik **Send**.
- **Ekspektasi**: Status `201 Created`. Token driver otomatis tersimpan ke variabel `{{driverToken}}`.

#### Langkah 2: Cek Profil Sebelum Disetujui
- Pilih request **Get Profile / Status Approval (/me)** → Klik **Send**.
- **Ekspektasi**: Status `200 OK`. Nilai `approval_status` adalah `"pending"`. Driver belum bisa mengakses endpoint operasional.

#### Langkah 3: Verifikasi & Persetujuan oleh Admin
1. Siapkan token admin. Buat token admin lewat terminal jika belum ada:
   ```bash
   php artisan tinker --execute 'echo App\Models\User::where("role", "admin")->first()?->createToken("admin-postman")->plainTextToken;'
   ```
2. Salin token tersebut ke variabel koleksi `adminToken`.
3. Buka folder `2. Verifikasi Admin` → pilih **Daftar Pendaftar Driver** → Klik **Send** (memeriksa pendaftar baru).
4. Pilih request **Approve Driver** → set `driverProfileId` ke ID driver yang baru didaftarkan → Klik **Send**.
- **Ekspektasi**: Status `200 OK`. `approval_status` berubah menjadi `"approved"`.

#### Langkah 4: Aktifkan Ketersediaan & Kirim Lokasi GPS
- Buka folder `3. Lokasi & Operasional Driver`:
  1. Pilih **Update Ketersediaan (Online/Offline)**:
     - Body: `{"is_available": true}`
     - Klik **Send**. Ekspektasi `200 OK`.
  2. Pilih **Kirim Koordinat GPS Terbaru**:
     - Body: `{"latitude": -6.2005000, "longitude": 106.8005000}`
     - Klik **Send**. Ekspektasi `200 OK`.

#### Langkah 5: Penyiapan Order & Dispatching
Agar driver memiliki pesanan yang ditawarkan, jalankan trigger dispatch di terminal:
```bash
php artisan tinker --execute '
$market = App\Models\Market::first() ?? App\Models\Market::factory()->create(["latitude" => -6.2000000, "longitude" => 106.8000000]);
$order = App\Models\Order::factory()->weightGrams(15000)->create(["market_id" => $market->market_id, "status" => App\Models\Order::STATUS_READY_FOR_DELIVERY]);
app(App\Services\DispatchService::class)->dispatch($order);
'
```

#### Langkah 6: Periksa Pesanan yang Masuk
- Pilih request **Ambil Delivery Aktif (/current)** → Klik **Send**.
- **Ekspektasi**: Status `200 OK`. Muncul objek `delivery` dengan status `"preparing"` dan satu order dengan status `"pending"`. Variabel `{{deliveryOrderId}}` dan `{{deliveryId}}` otomatis terisi.

#### Langkah 7: Terima Pesanan
- Buka folder `4. Pengelolaan Order & Perjalanan` → pilih **Terima Order (Accept)**.
- Pastikan URL menggunakan `:deliveryOrderId` yang valid → Klik **Send**.
- **Ekspektasi**: Status `200 OK`. Nilai `delivery_order.status` menjadi `"accepted"`.

#### Langkah 8: Mulai Pengantaran
- Pilih request **Mulai Pengantaran (Start Delivery)**.
- Pastikan URL menggunakan `:deliveryId` yang valid → Klik **Send**.
- **Ekspektasi**: Status `200 OK`. Status delivery berubah menjadi `"delivering"`, dan status order menjadi `"on_the_way"`.

#### Langkah 9: Selesaikan Pengantaran
- Pilih request **Selesaikan Satu Order (Complete)**:
  - Tab **Body** (`form-data`):
    - `received_by`: `customer`
    - `received_note`: `Diterima langsung di teras rumah`
    - `proof_photo`: (opsional) upload file foto bukti
- Klik **Send**.
- **Ekspektasi**: Status `200 OK`. Order berstatus `"completed"`. Karena seluruh order telah selesai, delivery otomatis selesai (`completed_at` terisi).

#### Langkah 10: Cek Riwayat Pengantaran
- Buka folder `3. Lokasi & Operasional Driver` → pilih **Riwayat Delivery (/history)** → Klik **Send**.
- **Ekspektasi**: Status `200 OK`. Delivery yang baru diselesaikan tercatat pada daftar riwayat.

---

### 11.5 Skenario Pengujian Negatif (Negative / Edge Cases)

Gunakan pengujian berikut untuk memverifikasi proteksi aturan bisnis:

| Skenario Uji | Endpoint & Aksi | Ekspektasi | Error Code |
|---|---|---|---|
| **Driver Pending Akses Operasional** | Jalankan `PATCH /driver/availability` saat status driver masih `pending` | `403 Forbidden` | `DRIVER_NOT_APPROVED` |
| **User Non-Driver** | Login dengan akun customer/admin lalu akses endpoint driver | `403 Forbidden` | `UNAUTHORIZED_ROLE` |
| **Bukan Pemilik Order** | Driver A mencoba accept order milik Driver B | `403 Forbidden` | `NOT_OWNER` |
| **Start Tanpa Order Accepted** | Panggil `POST /deliveries/:id/start` saat masih ada order berstatus `pending` | `422 Unprocessable` | `PENDING_ORDERS_EXIST` |
| **Tolak Order Saat On The Way** | Panggil `POST /delivery-orders/:id/reject` saat pengantaran sudah dimulai | `422 Unprocessable` | `INVALID_STATUS` / `DELIVERY_ALREADY_STARTED` |
| **Kelebihan Beban Kapasitas** | Tambah order ke-4 atau beban total > 50 kg | `422 Unprocessable` | `CAPACITY_EXCEEDED` / `WEIGHT_EXCEEDED` |
