<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\Institution;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Branch> */
class BranchFactory extends Factory
{
    public function definition(): array
    {
        $area = fake()->unique()->city();

        return [
            'institution_id' => Institution::factory(),
            'name' => "Branch {$area}",
            'code' => strtoupper(Str::random(5)),
            'subdomain' => 'b'.Str::lower(Str::random(8)),
            'type' => 'school_college',
            'email' => fake()->safeEmail(),
            'phone' => '+88017'.fake()->numerify('########'),
            'address' => "{$area}, Bangladesh",
            'timezone' => 'Asia/Dhaka',
            'currency' => 'BDT',
            'locale' => 'en',
            'status' => 'active',
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['status' => 'inactive']);
    }

    public function subdomain(string $subdomain): static
    {
        return $this->state(fn () => ['subdomain' => $subdomain]);
    }
}
