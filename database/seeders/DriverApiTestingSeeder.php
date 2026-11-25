<?php

namespace Database\Seeders;

use App\Enums\ApprovalStatus;
use App\Enums\DeliveryOrderStatus;
use App\Enums\UserRole;
use App\Models\Delivery;
use App\Models\DeliveryOrder;
use App\Models\DriverDocument;
use App\Models\DriverDocumentCheck;
use App\Models\DriverLocation;
use App\Models\DriverProfile;
use App\Models\DriverVehicle;
use App\Models\Market;
use App\Models\Order;
use App\Models\User;
use App\Services\DriverVerificationService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DriverApiTestingSeeder extends Seeder
{
    /**
     * Jalankan seeder database untuk pengujian API Driver.
     */
    public function run(): void
    {
        $password = Hash::make('password123');

        // =========================================================================
        // 1. Akun Admin (Untuk pengujian endpoint /api/admin/drivers/*)
        // =========================================================================
        $admin = User::updateOrCreate(
            ['email' => 'admin@belanjaanpasar.com'],
            [
                'name' => 'Admin Verifikasi',
                'password' => $password,
                'phone' => '081110000001',
                'gender' => 'male',
                'birth_date' => '1990-01-01',
                'role' => UserRole::Admin->value,
            ]
        );

        // =========================================================================
        // 2. Akun Customer (Sebagai pemesan barang)
        // =========================================================================
        $customer = User::updateOrCreate(
            ['email' => 'customer@belanjaanpasar.com'],
            [
                'name' => 'Siti Customer',
                'password' => $password,
                'phone' => '081220000001',
                'gender' => 'female',
                'birth_date' => '1996-04-12',
                'role' => UserRole::Customer->value,
            ]
        );

        // =========================================================================
        // 3. Pasar Asal Pengiriman (Markets)
        // =========================================================================
        $marketMinggu = Market::updateOrCreate(
            ['name' => 'Pasar Minggu'],
            [
                'address' => 'Jl. Pasar Minggu Raya No. 1, Jakarta Selatan',
                'latitude' => -6.2000000,
                'longitude' => 106.8000000,
                'is_active' => true,
            ]
        );

        $marketKebayoran = Market::updateOrCreate(
            ['name' => 'Pasar Kebayoran Lama'],
            [
                'address' => 'Jl. Raya Kebayoran Lama No. 15, Jakarta Selatan',
                'latitude' => -6.2386000,
                'longitude' => 106.7794000,
                'is_active' => true,
            ]
        );

        // =========================================================================
        // 4. Driver 1: Approved & Online (Siap uji operasional API Driver)
        //    Kredensial sesuai default Postman: budi.driver@example.com / password123
        // =========================================================================
        $driverUser1 = User::updateOrCreate(
            ['email' => 'budi.driver@example.com'],
            [
                'name' => 'Budi Driver',
                'password' => $password,
                'phone' => '081234567890',
                'gender' => 'male',
                'birth_date' => '1995-05-15',
                'role' => UserRole::Driver->value,
            ]
        );

        $driverProfile1 = DriverProfile::updateOrCreate(
            ['user_id' => $driverUser1->user_id],
            [
                'domicile_address' => 'Jl. Pasar Minggu No. 10, Jakarta Selatan',
                'is_available' => true,
                'approval_status' => ApprovalStatus::Approved->value,
                'rating_avg' => 4.85,
                'rating_count' => 24,
                'reviewed_at' => now()->subDays(5),
                'reviewed_by' => $admin->user_id,
            ]
        );

        DriverDocument::updateOrCreate(
            ['driver_profile_id' => $driverProfile1->driver_profile_id],
            [
                'nik' => '3171234567890001',
                'ktp_photo' => 'driver-documents/ktp/budi_ktp.jpg',
                'selfie_photo' => 'driver-documents/selfie/budi_selfie.jpg',
                'sim_number' => '950512345678',
                'sim_photo' => 'driver-documents/sim/budi_sim.jpg',
                'sim_expired_at' => now()->addYears(3)->toDateString(),
            ]
        );

        DriverVehicle::updateOrCreate(
            ['driver_profile_id' => $driverProfile1->driver_profile_id],
            [
                'vehicle_type' => 'Motor',
                'vehicle_brand' => 'Honda',
                'vehicle_model' => 'Vario 125',
                'vehicle_plate' => 'B 1234 XYZ',
                'vehicle_color' => 'Hitam',
                'production_year' => 2022,
                'vehicle_photo' => 'driver-vehicles/vehicle/budi_motor.jpg',
                'stnk_photo' => 'driver-vehicles/stnk/budi_stnk.jpg',
            ]
        );

        DriverVerificationService::pastikanPemeriksaan($driverProfile1);
        DriverDocumentCheck::where('driver_profile_id', $driverProfile1->driver_profile_id)
            ->update([
                'status' => DriverDocumentCheck::STATUS_VALID,
                'checked_by' => $admin->user_id,
                'checked_at' => now()->subDays(5),
            ]);

        DriverLocation::updateOrCreate(
            ['user_id' => $driverUser1->user_id],
            [
                'latitude' => -6.2005000,
                'longitude' => 106.8005000,
                'recorded_at' => now(),
            ]
        );

        // =========================================================================
        // 5. Driver 2: Pending (Siap uji verifikasi Admin Approve / Reject)
        //    Kredensial: andi.pending@example.com / password123
        // =========================================================================
        $driverUser2 = User::updateOrCreate(
            ['email' => 'andi.pending@example.com'],
            [
                'name' => 'Andi Pendaftar',
                'password' => $password,
                'phone' => '081399990002',
                'gender' => 'male',
                'birth_date' => '1996-08-20',
                'role' => UserRole::Driver->value,
            ]
        );

        $driverProfile2 = DriverProfile::updateOrCreate(
            ['user_id' => $driverUser2->user_id],
            [
                'domicile_address' => 'Jl. Tebet Barat Dalam No. 5, Jakarta Selatan',
                'is_available' => false,
                'approval_status' => ApprovalStatus::Pending->value,
                'rating_avg' => 0,
                'rating_count' => 0,
            ]
        );

        DriverDocument::updateOrCreate(
            ['driver_profile_id' => $driverProfile2->driver_profile_id],
            [
                'nik' => '3171234567890002',
                'ktp_photo' => 'driver-documents/ktp/andi_ktp.jpg',
                'selfie_photo' => 'driver-documents/selfie/andi_selfie.jpg',
                'sim_number' => '960812345678',
                'sim_photo' => 'driver-documents/sim/andi_sim.jpg',
                'sim_expired_at' => now()->addYears(2)->toDateString(),
            ]
        );

        DriverVehicle::updateOrCreate(
            ['driver_profile_id' => $driverProfile2->driver_profile_id],
            [
                'vehicle_type' => 'Motor',
                'vehicle_brand' => 'Yamaha',
                'vehicle_model' => 'NMAX',
                'vehicle_plate' => 'B 5678 ABC',
                'vehicle_color' => 'Putih',
                'production_year' => 2023,
                'vehicle_photo' => 'driver-vehicles/vehicle/andi_motor.jpg',
                'stnk_photo' => 'driver-vehicles/stnk/andi_stnk.jpg',
            ]
        );

        DriverVerificationService::pastikanPemeriksaan($driverProfile2);

        // =========================================================================
        // 6. Driver 3: Rejected (Untuk uji kasus negatif error DRIVER_NOT_APPROVED)
        //    Kredensial: dedi.rejected@example.com / password123
        // =========================================================================
        $driverUser3 = User::updateOrCreate(
            ['email' => 'dedi.rejected@example.com'],
            [
                'name' => 'Dedi Ditolak',
                'password' => $password,
                'phone' => '081499990003',
                'gender' => 'male',
                'birth_date' => '1993-11-12',
                'role' => UserRole::Driver->value,
            ]
        );

        $driverProfile3 = DriverProfile::updateOrCreate(
            ['user_id' => $driverUser3->user_id],
            [
                'domicile_address' => 'Jl. Kalibata Timur No. 12, Jakarta Selatan',
                'is_available' => false,
                'approval_status' => ApprovalStatus::Rejected->value,
                'rejection_reason' => 'Foto KTP buram dan masa berlaku SIM sudah kedaluwarsa.',
                'reviewed_at' => now()->subDays(2),
                'reviewed_by' => $admin->user_id,
            ]
        );

        DriverDocument::updateOrCreate(
            ['driver_profile_id' => $driverProfile3->driver_profile_id],
            [
                'nik' => '3171234567890003',
                'ktp_photo' => 'driver-documents/ktp/dedi_ktp.jpg',
                'selfie_photo' => 'driver-documents/selfie/dedi_selfie.jpg',
                'sim_number' => '931112345678',
                'sim_photo' => 'driver-documents/sim/dedi_sim.jpg',
                'sim_expired_at' => now()->subMonth()->toDateString(),
            ]
        );

        DriverVehicle::updateOrCreate(
            ['driver_profile_id' => $driverProfile3->driver_profile_id],
            [
                'vehicle_type' => 'Motor',
                'vehicle_brand' => 'Suzuki',
                'vehicle_model' => 'Address',
                'vehicle_plate' => 'B 9999 REJ',
                'vehicle_color' => 'Merah',
                'production_year' => 2018,
                'vehicle_photo' => 'driver-vehicles/vehicle/dedi_motor.jpg',
                'stnk_photo' => 'driver-vehicles/stnk/dedi_stnk.jpg',
            ]
        );

        DriverVerificationService::pastikanPemeriksaan($driverProfile3);

        // =========================================================================
        // 7. Pesanan Siap Antar (Orders STATUS_READY_FOR_DELIVERY)
        //    Digunakan untuk menguji job dispatching atau simulasi penawaran pesanan
        // =========================================================================
        $readyOrdersData = [
            [
                'order_number' => 'ORD-TEST-001',
                'recipient_name' => 'Bapak Hartono',
                'recipient_phone' => '081234560001',
                'shipping_address' => 'Jl. Pejaten Barat No. 22, Jakarta Selatan',
                'shipping_latitude' => -6.2105000,
                'shipping_longitude' => 106.8110000,
                'subtotal' => 125000,
                'delivery_fee' => 10000,
                'grand_total' => 135000,
                'total_weight_grams' => 12000, // 12 kg
            ],
            [
                'order_number' => 'ORD-TEST-002',
                'recipient_name' => 'Ibu Rahmawati',
                'recipient_phone' => '081234560002',
                'shipping_address' => 'Jl. Ampera Raya No. 8, Jakarta Selatan',
                'shipping_latitude' => -6.2150000,
                'shipping_longitude' => 106.8150000,
                'subtotal' => 85000,
                'delivery_fee' => 10000,
                'grand_total' => 95000,
                'total_weight_grams' => 7500, // 7.5 kg
            ],
            [
                'order_number' => 'ORD-TEST-003',
                'recipient_name' => 'Pak Joko',
                'recipient_phone' => '081234560003',
                'shipping_address' => 'Jl. Kemang Timur No. 14, Jakarta Selatan',
                'shipping_latitude' => -6.2200000,
                'shipping_longitude' => 106.8200000,
                'subtotal' => 220000,
                'delivery_fee' => 15000,
                'grand_total' => 235000,
                'total_weight_grams' => 22000, // 22 kg (uji kelebihan berat normal)
            ],
            [
                'order_number' => 'ORD-TEST-004',
                'recipient_name' => 'Ibu Linda',
                'recipient_phone' => '081234560004',
                'shipping_address' => 'Jl. Warung Buncit No. 3, Jakarta Selatan',
                'shipping_latitude' => -6.2180000,
                'shipping_longitude' => 106.8250000,
                'subtotal' => 95000,
                'delivery_fee' => 10000,
                'grand_total' => 105000,
                'total_weight_grams' => 5000, // 5 kg
            ],
        ];

        foreach ($readyOrdersData as $data) {
            Order::updateOrCreate(
                ['order_number' => $data['order_number']],
                array_merge($data, [
                    'user_id' => $customer->user_id,
                    'market_id' => $marketMinggu->market_id,
                    'status' => Order::STATUS_READY_FOR_DELIVERY,
                ])
            );
        }

        // =========================================================================
        // 8. Driver 4: Driver dengan Delivery Aktif Saat Ini
        //    Kredensial: eko.active@example.com / password123
        //    Langsung siap untuk pengujian:
        //    - GET /api/driver/deliveries/current
        //    - POST /api/driver/delivery-orders/{id}/accept
        //    - POST /api/driver/deliveries/{id}/start
        // =========================================================================
        $driverUser4 = User::updateOrCreate(
            ['email' => 'eko.active@example.com'],
            [
                'name' => 'Eko Pengantar Aktif',
                'password' => $password,
                'phone' => '081599990004',
                'gender' => 'male',
                'birth_date' => '1992-02-14',
                'role' => UserRole::Driver->value,
            ]
        );

        $driverProfile4 = DriverProfile::updateOrCreate(
            ['user_id' => $driverUser4->user_id],
            [
                'domicile_address' => 'Jl. Ragunan No. 88, Jakarta Selatan',
                'is_available' => true,
                'approval_status' => ApprovalStatus::Approved->value,
                'rating_avg' => 4.90,
                'rating_count' => 50,
                'reviewed_at' => now()->subMonths(1),
                'reviewed_by' => $admin->user_id,
            ]
        );

        DriverDocument::updateOrCreate(
            ['driver_profile_id' => $driverProfile4->driver_profile_id],
            [
                'nik' => '3171234567890004',
                'ktp_photo' => 'driver-documents/ktp/eko_ktp.jpg',
                'selfie_photo' => 'driver-documents/selfie/eko_selfie.jpg',
                'sim_number' => '920212345678',
                'sim_photo' => 'driver-documents/sim/eko_sim.jpg',
                'sim_expired_at' => now()->addYears(3)->toDateString(),
            ]
        );

        DriverVehicle::updateOrCreate(
            ['driver_profile_id' => $driverProfile4->driver_profile_id],
            [
                'vehicle_type' => 'Motor',
                'vehicle_brand' => 'Honda',
                'vehicle_model' => 'PCX 160',
                'vehicle_plate' => 'B 7777 EKO',
                'vehicle_color' => 'Hitam Glossy',
                'production_year' => 2023,
                'vehicle_photo' => 'driver-vehicles/vehicle/eko_motor.jpg',
                'stnk_photo' => 'driver-vehicles/stnk/eko_stnk.jpg',
            ]
        );

        DriverVerificationService::pastikanPemeriksaan($driverProfile4);
        DriverDocumentCheck::where('driver_profile_id', $driverProfile4->driver_profile_id)
            ->update([
                'status' => DriverDocumentCheck::STATUS_VALID,
                'checked_by' => $admin->user_id,
                'checked_at' => now()->subMonths(1),
            ]);

        DriverLocation::updateOrCreate(
            ['user_id' => $driverUser4->user_id],
            [
                'latitude' => -6.2015000,
                'longitude' => 106.8015000,
                'recorded_at' => now(),
            ]
        );

        // Buat Order khusus yang sedang di-assign ke Driver Eko
        $orderPending = Order::updateOrCreate(
            ['order_number' => 'ORD-ACTIVE-001'],
            [
                'user_id' => $customer->user_id,
                'market_id' => $marketMinggu->market_id,
                'recipient_name' => 'Ibu Maya',
                'recipient_phone' => '081234567001',
                'shipping_address' => 'Jl. Jatipadang No. 17, Jakarta Selatan',
                'shipping_latitude' => -6.2120000,
                'shipping_longitude' => 106.8120000,
                'subtotal' => 140000,
                'delivery_fee' => 10000,
                'grand_total' => 150000,
                'total_weight_grams' => 8000, // 8 kg
                'status' => Order::STATUS_READY_FOR_DELIVERY,
            ]
        );

        $orderAccepted = Order::updateOrCreate(
            ['order_number' => 'ORD-ACTIVE-002'],
            [
                'user_id' => $customer->user_id,
                'market_id' => $marketMinggu->market_id,
                'recipient_name' => 'Bapak Hendra',
                'recipient_phone' => '081234567002',
                'shipping_address' => 'Jl. Kebagusan Raya No. 45, Jakarta Selatan',
                'shipping_latitude' => -6.2160000,
                'shipping_longitude' => 106.8160000,
                'subtotal' => 180000,
                'delivery_fee' => 12500,
                'grand_total' => 192500,
                'total_weight_grams' => 11000, // 11 kg
                'status' => Order::STATUS_READY_FOR_DELIVERY,
            ]
        );

        $activeDelivery = Delivery::updateOrCreate(
            ['delivery_number' => 'DLV-TEST-CURRENT-01'],
            [
                'driver_profile_id' => $driverProfile4->driver_profile_id,
                'started_at' => null, // preparing
                'completed_at' => null,
                'cancelled_at' => null,
            ]
        );

        DeliveryOrder::updateOrCreate(
            [
                'delivery_id' => $activeDelivery->delivery_id,
                'order_id' => $orderPending->order_id,
            ],
            [
                'status' => DeliveryOrderStatus::Pending->value,
                'assigned_at' => now()->subMinutes(10),
            ]
        );

        DeliveryOrder::updateOrCreate(
            [
                'delivery_id' => $activeDelivery->delivery_id,
                'order_id' => $orderAccepted->order_id,
            ],
            [
                'status' => DeliveryOrderStatus::Accepted->value,
                'assigned_at' => now()->subMinutes(15),
                'accepted_at' => now()->subMinutes(8),
            ]
        );

        // =========================================================================
        // 9. Riwayat Pengantaran Selesai (Completed Delivery) untuk Driver Budi
        //    Sehingga endpoint GET /api/driver/deliveries/history langsung terisi data
        // =========================================================================
        $orderHistory = Order::updateOrCreate(
            ['order_number' => 'ORD-HIST-001'],
            [
                'user_id' => $customer->user_id,
                'market_id' => $marketMinggu->market_id,
                'recipient_name' => 'Bapak Tirta',
                'recipient_phone' => '081234567099',
                'shipping_address' => 'Jl. Siaga Raya No. 10, Jakarta Selatan',
                'shipping_latitude' => -6.2130000,
                'shipping_longitude' => 106.8130000,
                'subtotal' => 200000,
                'delivery_fee' => 10000,
                'grand_total' => 210000,
                'total_weight_grams' => 9000,
                'status' => Order::STATUS_COMPLETED,
            ]
        );

        $completedDelivery = Delivery::updateOrCreate(
            ['delivery_number' => 'DLV-HIST-COMPLETED-01'],
            [
                'driver_profile_id' => $driverProfile1->driver_profile_id,
                'started_at' => now()->subHours(3),
                'completed_at' => now()->subHours(2),
                'cancelled_at' => null,
            ]
        );

        DeliveryOrder::updateOrCreate(
            [
                'delivery_id' => $completedDelivery->delivery_id,
                'order_id' => $orderHistory->order_id,
            ],
            [
                'status' => DeliveryOrderStatus::Completed->value,
                'assigned_at' => now()->subHours(3)->subMinutes(15),
                'accepted_at' => now()->subHours(3)->subMinutes(10),
                'started_at' => now()->subHours(3),
                'completed_at' => now()->subHours(2),
                'proof_photo' => 'delivery-proofs/sample_proof.jpg',
                'received_by' => DeliveryOrder::RECEIVED_CUSTOMER,
                'received_note' => 'Diterima langsung oleh customer dalam kondisi baik',
            ]
        );
    }
}
