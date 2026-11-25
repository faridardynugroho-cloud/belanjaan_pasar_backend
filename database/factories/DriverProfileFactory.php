<?php

namespace Database\Factories;

use App\Enums\ApprovalStatus;
use App\Enums\UserRole;
use App\Models\DriverProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DriverProfile>
 */
class DriverProfileFactory extends Factory
{
    protected $model = DriverProfile::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory()->state(['role' => UserRole::Driver->value]),
            'domicile_address' => fake()->address(),
            'is_available' => false,
            'rating_avg' => 0,
            'rating_count' => 0,
            'approval_status' => ApprovalStatus::Pending->value,
        ];
    }

    public function approved(): static
    {
        return $this->state(fn () => [
            'approval_status' => ApprovalStatus::Approved->value,
            'reviewed_at' => now(),
        ]);
    }

    public function rejected(string $reason = 'Dokumen tidak sesuai'): static
    {
        return $this->state(fn () => [
            'approval_status' => ApprovalStatus::Rejected->value,
            'rejection_reason' => $reason,
            'reviewed_at' => now(),
        ]);
    }

    public function revision(): static
    {
        return $this->state(fn () => [
            'approval_status' => ApprovalStatus::Revision->value,
            'reviewed_at' => now(),
        ]);
    }

    public function available(bool $available = true): static
    {
        return $this->state(fn () => [
            'is_available' => $available,
        ]);
    }
}
