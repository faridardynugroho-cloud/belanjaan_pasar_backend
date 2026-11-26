# Fitur Driver — BelanjaanPasar

> Dokumen ini adalah sumber acuan untuk AI agent. Sumber kebenaran: **PRD "Peraturan Pengiriman Driver BelanjaanPasar"** dan **LRS (ERD) database**.
> Jika ada konflik antara dokumen ini dan PRD, PRD yang menang. Bagian bertanda **[ASUMSI]** atau **[TANYA TIM]** belum disepakati; jangan diam-diam memilih, ikuti default yang tertulis dan beri tanda `// TODO(confirm)` di kode.

## 0. Cara memakai dokumen ini

1. Kerjakan checklist (Bagian 9) **berurutan per fase**. Centang `[x]` setelah fitur selesai **dan** test-nya lolos.
2. Satu fase = satu PR/commit set yang bisa dijalankan sendiri.
3. Jangan mengubah tabel yang sudah ada di LRS tanpa persetujuan. Perubahan skema yang dibutuhkan ada di Bagian 3.3 dan harus lewat migration baru, bukan edit migration lama.
4. Semua aturan angka (kapasitas, berat, tarif) ditaruh di **satu tempat konfigurasi** (`config/delivery.php`), bukan di-hardcode tersebar.

## 1. Stack & Infrastruktur

| Lapisan | Teknologi | Tugas |
|---|---|---|
| Mobile driver | React Native + GPS | Ambil `latitude` & `longitude` dari HP driver, kirim ke API |
| Backend | Laravel API | Terima koordinat, update lokasi ke MySQL, seluruh logika bisnis |
| Realtime | Laravel Reverb (WebSocket) | Distribusi pembaruan koordinat |
| Database | MySQL | **Satu** koordinat terbaru per driver (bukan riwayat) |
| Storage gambar | Storage project Laravel (sementara) | Foto KTP, selfie, SIM, kendaraan, STNK |

Catatan: fokus dokumen ini adalah **sisi backend Laravel + kontrak API untuk React Native**. Pekerjaan UI React Native hanya ditulis sebagai kebutuhan endpoint.

## 2. Glosarium

- **Order**: pesanan customer (`orders`).
- **Delivery**: satu perjalanan driver, bisa membawa 1–3 order (`deliveries`).
- **Delivery order**: relasi order ke delivery beserta status per order (`delivery_orders`).
- **Pending**: order sudah ditawarkan ke driver tetapi belum diterima/ditolak.
- **Tahap mengambil pesanan**: delivery sudah ada, `deliveries.started_at` masih `NULL`.
- **Tahap pengantaran**: `deliveries.started_at` sudah terisi; order berstatus `on_the_way`.
- **Potongan aplikasi**: nominal tetap (Rupiah) yang diambil aplikasi. **Bukan persentase.**

## 3. Skema Database (dari LRS)

### 3.1 Tabel yang dipakai fitur ini

**users**: `user_id` PK, `name`, `email`, `password`, `phone`, `role` enum, `gender` enum, `birth_date`, `created_at`, `updated_at`

**driver_profiles**: `driver_profile_id` PK, `user_id` FK, `domicile_address` text, `is_available` boolean, `rating_avg` decimal, `rating_count` int, `approval_status` enum, `rejection_reason` text, `reviewed_at` datetime, `reviewed_by` FK(users), `created_at`, `updated_at`

**driver_documents**: `driver_document_id` PK, `driver_profile_id` FK, `nik`, `ktp_photo`, `selfie_photo`, `sim_number`, `sim_photo`, `sim_expired_at` (string di LRS), `created_at`, `updated_at`

**driver_vehicles**: `driver_vehicle_id` PK, `driver_profile_id` FK, `vehicle_type`, `vehicle_brand`, `vehicle_model`, `vehicle_plate`, `vehicle_color`, `production_year` (string), `vehicle_photo`, `stnk_photo`, `created_at`, `updated_at`

**driver_locations**: `driver_location_id` PK, `user_id` FK, `latitude` decimal, `longitude` decimal, `recorded_at` datetime
→ PRD: hanya **satu baris terbaru per driver**. Gunakan upsert berdasarkan `user_id` (lihat 3.3).

**deliveries**: `delivery_id` PK, `driver_profile_id` FK, `delivery_number` string, `cancelled_at`, `started_at`, `completed_at`, `created_at`, `updated_at`
→ Tidak ada kolom `status`; **status delivery diturunkan dari kolom timestamp** (lihat 5.3).

**delivery_orders** (di diagram tertulis `Deliveriy_Orders`, pakai nama `delivery_orders` sesuai PRD): `delivery_order_id` PK, `delivery_id` FK, `order_id` FK, `status` enum, `rejection_reason` text, `assigned_at`, `accepted_at`, `rejected_at`, `started_at`, `completed_at`, `created_at`, `updated_at`

**orders** (hanya kolom relevan): `order_id` PK, `user_id`, `market_id` FK, `shopping_request_id` FK, `order_number`, `recipient_name`, `recipient_phone`, `shipping_address`, `shipping_latitude`, `shipping_longitude`, `subtotal`, `delivery_fee`, `grand_total`, `total_weight_grams` int, `status` enum

**markets**: `market_id` PK, `name`, `address`, `latitude`, `longitude`, `is_active`

**order_items**: memuat `weight_grams` (di LRS tertulis typo `wight_grams`) dan `total_weight_grams` per item. Berat order = `orders.total_weight_grams`.

### 3.2 Relasi penting

- `driver_profiles.user_id` → `users.user_id`; `driver_documents`, `driver_vehicles`, `deliveries` → `driver_profiles`.
- `deliveries` 1—N `delivery_orders` N—1 `orders`.
- `orders.market_id` → `markets` (titik ambil). Tujuan = `orders.shipping_latitude/longitude`.
- `driver_locations` terkait lewat `user_id`, **bukan** `driver_profile_id`. Hati-hati saat join.
- `driver_review` (bukan cakupan fitur ini) mengacu ke `delivery_orders` dan `driver_profiles`.

### 3.3 Perubahan skema yang diusulkan (migration baru, **[TANYA TIM]**)

- [ ] `driver_locations.user_id` → tambah **UNIQUE index** agar upsert menjamin satu koordinat per driver.
- [ ] Index: `delivery_orders(order_id, status)`, `delivery_orders(delivery_id, status)`, `deliveries(driver_profile_id, completed_at)`.
- [ ] Nilai enum `delivery_orders.status` dan `driver_profiles.approval_status` belum tercantum di LRS. Usulan di Bagian 5.1 dan 6.
- [ ] **Penyimpanan potongan aplikasi & jarak**: LRS hanya punya `orders.delivery_fee`. Tidak ada kolom jarak (km), potongan aplikasi, atau penghasilan driver. Usulan kolom di `orders` (atau `delivery_orders`): `distance_km` decimal(5,2), `app_fee` decimal, `driver_earning` decimal. Jika tim menolak, hitung saat dibutuhkan lewat `FeeCalculator` (Bagian 5.5) tanpa menyimpan.
- [ ] `driver_documents.sim_expired_at` bertipe string; usulan ubah ke `date`. Jika tidak diubah, validasi format `Y-m-d`.

## 4. Peran & Hak Akses

- **Driver** (`users.role = driver`): hanya boleh mengakses delivery/order miliknya.
- **Admin**: verifikasi pendaftaran driver.
- **Sistem**: dispatch otomatis (job/service), bukan endpoint driver.
- Setiap endpoint driver wajib memverifikasi: user login, role driver, `approval_status = approved`.

## 5. Aturan Bisnis

### 5.1 Status

`delivery_orders.status` **[ASUMSI]**: `pending` → `accepted` → `on_the_way` → `completed`, ditambah `rejected` (dari `pending`).

| Transisi | Syarat | Kolom yang diisi |
|---|---|---|
| (dibuat) → `pending` | sistem menawarkan order ke driver | `assigned_at` |
| `pending` → `accepted` | driver terima, delivery belum dimulai, lolos cek kapasitas & berat | `accepted_at` |
| `pending` → `rejected` | driver tolak, delivery belum dimulai | `rejected_at`, `rejection_reason` |
| `accepted` → `on_the_way` | driver memulai pengantaran | `started_at` |
| `on_the_way` → `completed` | driver selesaikan order | `completed_at` |

Larangan: status tidak boleh mundur; `on_the_way` tidak boleh dibatalkan/ditolak.

### 5.2 R-01 Penentuan driver (dispatch)

Driver **kandidat** harus memenuhi semua:
1. `approval_status = approved` dan `is_available = true`.
2. Punya baris `driver_locations`.
3. Delivery aktifnya (jika ada) **belum dimulai** (`started_at IS NULL`), atau tidak punya delivery aktif.
4. Jumlah order aktif < 3 (lihat R-02) dan total berat + berat order baru ≤ 50.000 g (lihat R-03).
5. Belum pernah menolak order yang sama (ada baris `delivery_orders` `rejected` untuk `order_id` itu).

Pengurutan kandidat (urutan sesuai PRD):
1. **Jarak terdekat ke pasar/toko** (`markets.latitude/longitude` vs `driver_locations`).
2. **Prioritas yang belum dapat order**: driver tanpa order dalam waktu terakhir didahulukan.
3. Jika semua pernah dapat order: **yang paling lama menunggu** sejak pengiriman sebelumnya.
   - Acuan menunggu = `deliveries.completed_at` terbaru milik driver; jika driver belum pernah punya delivery, pakai `driver_locations.recorded_at` (sesuai catatan PRD bahwa poin 2 & 3 diambil dari kedua kolom ini). **[ASUMSI]**
4. Pertimbangkan arah perjalanan dan jarak antar lokasi lewat rute peta (R-01c, opsional di Fase 8).

**[TANYA TIM]** PRD tidak menjelaskan cara menggabungkan kriteria 1 dengan 2–3. Default: filter radius maksimum (config `delivery.dispatch_radius_km`, nilai awal **[ASUMSI]**), lalu urut kriteria 2–3 di dalam radius, dan jarak sebagai tie-break. Bungkus dalam satu kelas `DriverRanker` agar mudah diganti.

**R-01b** Penggabungan order: sistem *dapat* menggabungkan order yang berdekatan/searah ke driver yang sama. Opsional, dikerjakan setelah dispatch dasar stabil.

### 5.3 R-02 Kapasitas order

- Maksimal **3 order per delivery**.
- Driver boleh menerima order tambahan **hanya selama** `deliveries.started_at IS NULL` (masih mengambil pesanan / belum berangkat).
- Hitungan muatan = jumlah `delivery_orders` berstatus `pending`, `accepted`, `on_the_way` dalam delivery itu. **Order `rejected` tidak dihitung.** (Menghitung `pending` agar tidak over-assign **[ASUMSI]**.)
- **Dispatch dua tahap** untuk order ketiga:
  1. Cari driver kandidat yang punya < 2 order lebih dulu.
  2. Hanya jika tidak ada, boleh berikan ke driver yang sudah punya 2 order (selama masih ≤ 3 dan berat ≤ 50 kg).

Status delivery (diturunkan, tanpa kolom status):
- `cancelled_at` terisi → cancelled
- `completed_at` terisi → completed
- `started_at` terisi → delivering
- selain itu → preparing (mengambil pesanan)

### 5.4 R-03 Berat

Semua perhitungan dalam **gram** (`total_weight_grams`); PRD dalam kg → 1 kg = 1000 g.

- Batas normal per order: **20.000 g**.
- Total berat seluruh order yang dibawa driver **≤ 50.000 g**.
- Driver yang sudah membawa 40 kg hanya boleh menerima tambahan **maks 10 kg** (konsekuensi aturan 50 kg; satu pengecekan `total + baru <= 50000` mencakup keduanya).
- Biaya kelebihan berat per order:
  - `kelebihan = max(0, berat_order - 20000)`
  - `blok = ceil(kelebihan / 10000)` (**selalu dibulatkan ke atas**)
  - Biaya pelanggan tambahan = `blok × 5.000`
  - Potongan aplikasi tambahan = `blok × 1.000`

### 5.5 R-04 Biaya pengiriman (`FeeCalculator`)

Jarak = pasar → rumah customer, **sudah dihitung oleh sistem** (jangan dihitung ulang oleh client). Maksimal **7 km**; di atas itu order tidak bisa dibuat.

| Jarak | Biaya pelanggan | Potongan aplikasi |
|---|---|---|
| 0–3 km | Rp 10.000 | Rp 1.000 |
| > 3–5 km | Rp 10.000 + Rp 2.500 per km di atas 3 | Rp 1.000 |
| > 5–7 km | Rp 10.000 + Rp 2.500 per km di atas 3 | Rp 2.000 |

- Tidak ada persentase; semua nominal tetap.
- Total biaya = biaya jarak + biaya berat (5.4). Total potongan = potongan jarak + potongan berat.
- **[ASUMSI]** Pembulatan km: `km_ditagih = ceil(jarak)`; ambang potongan Rp 2.000 memakai jarak asli (`> 5.0`). Sesuaikan jika tim memutuskan lain.
- Jika fee sudah dihitung di alur customer (`orders.delivery_fee`), **pakai ulang** service itu dan jangan membuat duplikat.

Contoh uji (wajib jadi unit test):

| Jarak | Berat | Biaya pelanggan | Potongan app |
|---|---|---|---|
| 3 km | 20 kg | Rp 10.000 | Rp 1.000 |
| 4 km | 25 kg | Rp 10.000 + 2.500 + 5.000 = **Rp 17.500** | 1.000 + 1.000 = **Rp 2.000** |
| 5 km | 20 kg | Rp 10.000 + 5.000 = **Rp 15.000** | Rp 1.000 |
| 6 km | 45 kg | Rp 10.000 + 7.500 + 15.000 (3 blok) = **Rp 32.500** | 2.000 + 3.000 = **Rp 5.000** |
| 7 km | 20,001 kg | Rp 10.000 + 10.000 + 5.000 = **Rp 25.000** | 2.000 + 1.000 = **Rp 3.000** |

### 5.6 R-05 Terima & tolak

- Driver bisa menerima atau menolak order yang ditawarkan.
- **Tolak hanya boleh jika status `pending`** (dan delivery belum dimulai).
- Menolak satu order **tidak** membatalkan order lain dalam delivery yang sama.
- Setiap penolakan menyimpan `rejection_reason` dan `rejected_at`. Riwayat **tidak pernah dihapus**.
- Order yang ditolak otomatis dicari lagi (re-dispatch) ke driver lain, mengecualikan driver yang sudah menolak order itu.
- Order yang sudah `on_the_way` **tidak boleh dibatalkan** oleh driver.
- Jika semua `delivery_orders` pada satu delivery berstatus `rejected`, isi `deliveries.cancelled_at`. **[ASUMSI]**
- **[TANYA TIM]** PRD tidak mengatur pembatalan order yang sudah `accepted` tetapi belum berangkat. Default: **tidak diizinkan** (hanya tolak saat `pending`).

### 5.7 R-06 Alur perjalanan

| Kondisi | Aturan |
|---|---|
| Driver sedang mengambil pesanan | Boleh menerima order tambahan |
| Driver belum memulai perjalanan | Boleh menolak order tertentu (yang masih `pending`) |
| Driver mulai pengantaran | **Tidak** boleh menerima order tambahan |
| Driver sedang mengantar | **Tidak** boleh membatalkan order yang diterima |
| Satu order selesai | Status `delivery_orders` order itu → `completed` |
| Masih ada order belum selesai | Delivery tetap berlangsung |
| Semua order yang diterima selesai | Delivery selesai (`deliveries.completed_at` terisi) |

- Status per order disimpan di `delivery_orders`; `deliveries` hanya info perjalanan keseluruhan.
- Saat "mulai pengantaran": isi `deliveries.started_at`, ubah semua `accepted` → `on_the_way` dengan `started_at`. Syarat minimal 1 order `accepted`. **[ASUMSI]** Jika masih ada `pending`, tolak permintaan mulai (driver harus menerima/menolak dulu).
- Sinkronisasi `orders.status` mengikuti status `delivery_orders` (nilai enum orders dikonfirmasi tim). **[TANYA TIM]**

### 5.8 R-07 Pendaftaran driver

Data yang dikumpulkan dan tabel tujuannya:

| Kelompok | Field | Tabel |
|---|---|---|
| Data pribadi | nama, email, nomor HP, jenis kelamin, tanggal lahir, alamat domisili | `users` (+ `role=driver`), `driver_profiles.domicile_address` |
| Identitas | NIK, foto KTP, foto selfie | `driver_documents` |
| SIM | nomor SIM C, foto SIM, masa berlaku SIM | `driver_documents` |
| Kendaraan | jenis, merk, tipe, plat nomor, warna, tahun, foto kendaraan, foto STNK | `driver_vehicles` |
| Verifikasi admin | setujui/tolak | `driver_profiles.approval_status`, `rejection_reason`, `reviewed_at`, `reviewed_by` |

- Foto disimpan di storage project Laravel (sementara). Simpan **path** di kolom string; jangan simpan di luar `storage`. Siapkan lewat `Storage` facade agar mudah dipindah ke S3 nanti.
- `approval_status` **[ASUMSI]**: `pending`, `approved`, `rejected`. Saat daftar = `pending`. Driver tidak bisa menerima order sebelum `approved`.
- Saat admin menolak, `rejection_reason` wajib diisi.
- Pendaftaran harus transaksional: gagal di satu tabel = tidak ada data tersisa.
- Validasi: NIK 16 digit, email & phone unik, SIM tipe **C**, masa berlaku SIM belum kedaluwarsa, plat nomor unik, format gambar (jpg/png) dan batas ukuran.

### 5.9 R-08 Lokasi driver realtime

- RN mengirim `latitude`/`longitude` berkala dari GPS saat driver online.
- Laravel API **upsert** satu baris `driver_locations` per `user_id` dan set `recorded_at = now()`.
- Setelah tersimpan, broadcast event lewat **Laravel Reverb**.
- Channel diberi otorisasi (private channel). **[TANYA TIM]** siapa yang boleh subscribe (customer pemilik order aktif, admin). Default: customer pemilik order yang `on_the_way`, dan admin.
- Interval kirim (misal 5–10 detik) dan filter perpindahan minimum adalah parameter config. **[ASUMSI]**

## 6. Rencana API (usulan, **[ASUMSI]**)

Prefix `/api/driver`, auth Sanctum, response JSON konsisten (`Resource`).

| Method | Endpoint | Fungsi |
|---|---|---|
| POST | `/register` | Pendaftaran driver (multipart) |
| GET | `/me` | Profil, status approval |
| PATCH | `/availability` | Set `is_available` |
| PUT | `/location` | Upsert koordinat terbaru |
| GET | `/deliveries/current` | Delivery aktif + daftar order |
| GET | `/deliveries/history` | Riwayat delivery |
| POST | `/delivery-orders/{id}/accept` | Terima order |
| POST | `/delivery-orders/{id}/reject` | Tolak (body: `rejection_reason`) |
| POST | `/deliveries/{id}/start` | Mulai pengantaran |
| POST | `/delivery-orders/{id}/complete` | Selesaikan satu order |

Admin: `GET /api/admin/drivers?approval_status=`, `POST /api/admin/drivers/{id}/approve`, `POST /api/admin/drivers/{id}/reject`.

Kode error yang konsisten untuk pelanggaran aturan: kapasitas penuh, berat melebihi batas, delivery sudah dimulai, status tidak valid, bukan pemilik.

## 7. Prinsip Implementasi

- Struktur: `Enums`, `FormRequests`, `Services` (`DispatchService`, `DriverRanker`, `CapacityChecker`, `FeeCalculator`, `DeliveryService`), `Policies`, `API Resources`, `Events`/`Jobs`.
- Operasi accept/start/complete/dispatch dibungkus **DB transaction** dengan `lockForUpdate()` pada delivery driver untuk mencegah race condition (dua order diterima serentak melewati batas 3 atau 50 kg).
- Validasi aturan bisnis di **service**, bukan di controller.
- Re-dispatch berjalan lewat **queued job**; jika tak ada driver tersedia, order tetap menunggu dan dicoba ulang (retry/schedule). **[ASUMSI]**
- Jangan menghapus baris `delivery_orders` yang ditolak.
- Semua pembulatan uang memakai integer Rupiah / decimal, bukan float.

## 8. Fitur lain

- Review/rating driver (`driver_review`) dan hitung ulang `rating_avg`/`rating_count`.
- Pembayaran/payout driver, dompet, dan laporan pendapatan.
- Pemindahan storage gambar ke cloud.
- UI aplikasi React Native dan dashboard admin.

## 9. Checklist Pengerjaan

### Fase 0 — Fondasi
- [x] Buat `config/delivery.php` (max_orders=3, max_weight_g=50000, normal_weight_g=20000, weight_block_g=10000, max_distance_km=7, base_fee=10000, per_km_fee=2500, weight_fee=5000, dll.)
- [x] Buat Enum: `DeliveryOrderStatus`, `ApprovalStatus`, `UserRole` (jika belum ada)
- [x] Migration tambahan Bagian 3.3 yang sudah disetujui (unique `driver_locations.user_id`, index)
- [x] Model + relasi Eloquent untuk semua tabel di Bagian 3.1 (pastikan nama PK custom terdaftar)
- [x] Factory & seeder: driver, kendaraan, pasar, order dengan berat berbeda
- [x] Middleware/Policy: hanya driver `approved` yang boleh pakai endpoint driver

### Fase 1 — Pendaftaran & verifikasi driver (R-07)
- [x] `POST /register` + FormRequest validasi lengkap
- [x] Simpan data ke `users`, `driver_profiles`, `driver_documents`, `driver_vehicles` dalam satu transaksi
- [x] Upload foto KTP, selfie, SIM, kendaraan, STNK ke storage Laravel (`storage:link`)
- [x] Endpoint admin: daftar pendaftar, detail dokumen
- [x] Endpoint admin: approve (`approved`, `reviewed_at`, `reviewed_by`)
- [x] Endpoint admin: reject (wajib `rejection_reason`)
- [x] Test: validasi gagal tidak meninggalkan data parsial; driver `pending`/`rejected` ditolak aksesnya

### Fase 2 — Lokasi realtime (R-08)
- [x] `PUT /location` upsert satu baris per driver + `recorded_at`
- [x] Event `DriverLocationUpdated` + broadcast via Reverb dengan channel ber-otorisasi
- [x] `PATCH /availability` (`is_available`)
- [x] Test: dua kali kirim lokasi = tetap satu baris; user non-driver ditolak

### Fase 3 — Perhitungan biaya & berat (R-03, R-04)
- [x] `FeeCalculator`: biaya jarak, potongan jarak, biaya berat, potongan berat
- [x] Tolak jarak > 7 km
- [x] Unit test semua baris contoh di Bagian 5.5 + batas 20 kg, 20,001 kg, 3 km, 5 km
- [x] Pastikan tidak ada persentase di mana pun

### Fase 4 — Kapasitas & berat driver (R-02, R-03)
- [x] `CapacityChecker`: hitung order aktif (`pending`, `accepted`, `on_the_way`), abaikan `rejected`
- [x] Cek maks 3 order per delivery
- [x] Cek total berat ≤ 50.000 g (driver 40 kg hanya menerima ≤ 10 kg)
- [x] Cek delivery belum dimulai untuk tambahan order
- [x] Test: 3 order OK, order ke-4 ditolak; 40 kg + 10 kg OK; 40 kg + 10,001 kg gagal; order rejected tidak menambah muatan

### Fase 5 — Dispatch (R-01)
- [x] Query kandidat sesuai syarat 5.2 (approved, available, punya lokasi, delivery belum mulai, bukan penolak order ini)
- [x] `DriverRanker`: jarak ke pasar, prioritas belum dapat order, paling lama menunggu (`completed_at` / `recorded_at`)
- [x] Dispatch dua tahap: driver < 2 order dahulu, fallback ke driver dengan 2 order
- [x] Buat/ambil `deliveries` aktif driver (`delivery_number` unik) + `delivery_orders` `pending` dengan `assigned_at`
- [x] Job dispatch + retry bila belum ada driver
- [x] Test: urutan prioritas, fallback order ketiga, driver yang menolak tidak ditawari ulang

### Fase 6 — Terima, tolak, re-dispatch (R-05)
- [x] `POST /delivery-orders/{id}/accept` (set `accepted_at`, cek kapasitas dengan lock)
- [x] `POST /delivery-orders/{id}/reject` (hanya `pending`, simpan `rejection_reason` + `rejected_at`)
- [x] Menolak satu order tidak mempengaruhi order lain di delivery yang sama
- [x] Trigger re-dispatch otomatis setelah penolakan
- [x] Set `deliveries.cancelled_at` jika semua order ditolak
- [x] Larang tolak/batal untuk status selain `pending` (termasuk `on_the_way`)
- [x] Test: tolak saat `on_the_way` → error; riwayat penolakan tetap ada

### Fase 7 — Perjalanan & penyelesaian (R-06)
- [x] `POST /deliveries/{id}/start` (isi `started_at`; `accepted` → `on_the_way`)
- [x] Blokir penerimaan order tambahan setelah delivery dimulai
- [x] `POST /delivery-orders/{id}/complete` (status `completed`, `completed_at`)
- [x] Auto-complete delivery saat semua order yang diterima selesai
- [x] Sinkronisasi `orders.status` (setelah enum disepakati)
- [x] `GET /deliveries/current` dan `/deliveries/history`
- [x] Test: delivery tetap berjalan selama ada order belum selesai; selesai hanya setelah semua selesai

### Fase 8 — Opsional / lanjutan
- [ ] Penggabungan order searah/berdekatan memakai rute peta (R-01b), setelah layanan peta dipilih
- [ ] Penyesuaian prioritas dispatch berdasarkan arah perjalanan

### Fase 9 — Penutup
- [x] Feature test end-to-end: daftar → approve → online → dapat order → terima → mulai → selesai
- [x] Test concurrency: dua penerimaan bersamaan tidak melampaui 3 order / 50 kg
- [x] Dokumentasi API (Postman/OpenAPI) untuk tim React Native
- [ ] Review semua poin **[TANYA TIM]** dan hapus `TODO(confirm)` yang sudah diputuskan

## 10. Daftar Pertanyaan Terbuka untuk Tim

1. Cara menggabungkan kriteria jarak vs prioritas "belum dapat order" pada dispatch (5.2), dan nilai radius.
2. Nilai enum `delivery_orders.status`, `orders.status`, `driver_profiles.approval_status`.
3. Apakah `pending` dihitung sebagai muatan (5.3) dan apakah order `accepted` boleh dibatalkan sebelum berangkat.
4. Penyimpanan jarak, potongan aplikasi, dan penghasilan driver (3.3), karena LRS belum punya kolomnya.
5. Pembulatan kilometer (5.5).
6. Siapa yang boleh subscribe channel lokasi (5.9).
7. Apakah mulai pengantaran boleh saat masih ada order `pending` (5.7).
