<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::firstOrNew(['email' => 'admin@belanjaanpasar.com']);

        $admin->forceFill([
            'name' => 'Administrator',
            'password' => Hash::make('BelanjaanPasar2026'), 
            'role' => 'admin',
        ])->save();
    }
}