<?php

namespace Database\Factories;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Usuario Demo',
            'email' => Str::lower(Str::random(12)).'@example.com',
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
        ];
    }

    public function superAdministrator(): static {
        return $this->afterCreating(function (User $user) {
            $superadmin = Role::firstOrCreate(['name' => 'superadmin']);
            $user->syncRoles([$superadmin]);
        });
    }

    public function administratorClub(): static {
        return $this->afterCreating(function (User $user) {
            $adminClub = Role::firstOrCreate(['name' => 'admin_club']);
            $user->syncRoles([$adminClub]);
        });
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
