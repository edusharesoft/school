<?php

namespace Database\Factories;

use App\Enums\UserType;
use App\Models\Branch;
use App\Models\Institution;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/** @extends Factory<User> */
class UserFactory extends Factory
{
    protected static ?string $password;

    public function definition(): array
    {
        return [
            'institution_id' => Institution::factory(),
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => '+88017'.fake()->unique()->numerify('########'),
            'user_type' => UserType::Staff,
            'status' => 'active',
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
        ];
    }

    public function platform(): static
    {
        return $this->state(fn () => ['institution_id' => null, 'user_type' => UserType::Platform]);
    }

    public function type(UserType $type): static
    {
        return $this->state(fn () => ['user_type' => $type]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['status' => 'inactive']);
    }

    /** Attach to a branch (and its institution) after creation. */
    public function forBranch(Branch $branch): static
    {
        return $this->state(fn () => ['institution_id' => $branch->institution_id, 'default_branch_id' => $branch->id])
            ->afterCreating(fn (User $user) => $user->branches()->syncWithoutDetaching([$branch->id => ['is_default' => true]]));
    }

    /** @param  string|string[]  $roles */
    public function role(string|array $roles): static
    {
        return $this->afterCreating(fn (User $user) => $user->assignRole($roles));
    }
}
