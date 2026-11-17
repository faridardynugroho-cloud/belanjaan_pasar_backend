<?php

namespace App\Models;

use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements FilamentUser
{
    use HasApiTokens, HasFactory, Notifiable;

    public const ROLE_CUSTOMER = 'customer';
    public const ROLE_ADMIN = 'admin';
    public const ROLE_DRIVER = 'driver';

    protected $primaryKey = 'user_id';

    protected $fillable = [
        'name',
        'email',
        'password',
        'phone',
        'gender',
        'birth_date',
        'role',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'birth_date' => 'date',
        ];
    }

    // ===== Helpers =====

    public function isCustomer(): bool
    {
        return $this->role === self::ROLE_CUSTOMER;
    }

    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    public function isDriver(): bool
    {
        return $this->role === self::ROLE_DRIVER;
    }

    // ===== Relasi =====

    public function addresses(): HasMany
    {
        return $this->hasMany(Address::class, 'user_id', 'user_id');
    }

    public function defaultAddress(): HasOne
    {
        return $this->hasOne(Address::class, 'user_id', 'user_id')
            ->where('is_default', true);
    }

    public function driverProfile(): HasOne
    {
        return $this->hasOne(DriverProfile::class, 'user_id', 'user_id');
    }

    public function driverLocations(): HasMany
    {
        return $this->hasMany(DriverLocation::class, 'user_id', 'user_id');
    }

    public function latestDriverLocation(): HasOne
    {
        return $this->hasOne(DriverLocation::class, 'user_id', 'user_id')
            ->latestOfMany('recorded_at');
    }

    public function carts(): HasMany
    {
        return $this->hasMany(Cart::class, 'user_id', 'user_id');
    }

    public function cart(): HasOne
    {
        return $this->hasOne(Cart::class, 'user_id', 'user_id');
    }

    public function shoppingRequests(): HasMany
    {
        return $this->hasMany(ShoppingRequest::class, 'user_id', 'user_id');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'user_id', 'user_id');
    }

    public function deliveries(): HasManyThrough
    {
        return $this->hasManyThrough(
            Delivery::class,
            DriverProfile::class,
            'user_id',
            'driver_profile_id',
            'user_id',
            'driver_profile_id',
        );
    }

    /** Penugasan helper yang dibuat oleh user ini (role admin). */
    public function shoppingAssignmentsMade(): HasMany
    {
        return $this->hasMany(ShoppingRequestAssignment::class, 'assigned_by', 'user_id');
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $panel->getId() === 'admin' && $this->isAdmin();
    }
}
