<?php

use App\Models\User;
use Database\Seeders\AdminSeeder;
use Filament\Facades\Filament;

beforeEach(function () {
    config([
        'admin.email' => 'admin@example.test',
        'admin.name' => 'Admin Test',
        'admin.password' => 'rahasia-test-123',
    ]);
});

it('seeder membuat akun admin yang boleh masuk panel', function () {
    $this->seed(AdminSeeder::class);

    $admin = User::where('email', 'admin@example.test')->firstOrFail();

    expect($admin->role)->toBe(User::ROLE_ADMIN)
        ->and($admin->canAccessPanel(Filament::getPanel('admin')))->toBeTrue();
});

it('halaman login admin bisa dibuka', function () {
    $this->get('/admin/login')->assertOk();
});

it('customer tidak bisa masuk panel admin', function () {
    $customer = User::factory()->create(['role' => User::ROLE_CUSTOMER]);

    $this->actingAs($customer)->get('/admin')->assertForbidden();
});

it('semua halaman daftar di panel admin bisa dibuka', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $this->actingAs($admin);

    foreach (Filament::getPanel('admin')->getResources() as $resource) {
        if (! array_key_exists('index', $resource::getPages())) {
            continue;
        }

        $url = $resource::getUrl('index', isAbsolute: false, panel: 'admin');

        $this->assertSame(200, $this->get($url)->getStatusCode(), "Gagal membuka {$url}");
    }
});