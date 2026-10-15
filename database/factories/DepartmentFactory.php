<?php

namespace Database\Factories;

use App\Models\Department;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * Tenant keys are filled by BelongsToBranch from the active context, so create departments
 * inside tenant()->runForBranch($branch, fn () => Department::factory()->create()).
 *
 * @extends Factory<Department>
 */
class DepartmentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            'code' => strtoupper(Str::random(4)),
            'description' => fake()->sentence(),
            'is_active' => true,
        ];
    }
}
