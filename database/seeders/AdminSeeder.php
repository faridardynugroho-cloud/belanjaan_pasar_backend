<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        $email = config('admin.email');
        $password = config('admin.password');

        if (blank($email) || blank($password)) {
            throw new \RuntimeException('ADMIN_EMAIL dan ADMIN_PASSWORD belum diisi di .env.');
        }

        User::firstOrCreate(
            ['email' => $email],
            [
                'name' => config('admin.name'),
                'password' => Hash::make($password),
                'role' => User::ROLE_ADMIN,
            ]
        );
    }
}
