<?php

namespace Database\Factories;

use App\Models\Institution;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Institution> */
class InstitutionFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->company().' School & College';

        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(4)),
            'code' => strtoupper(Str::random(6)),
            'email' => fake()->companyEmail(),
            'phone' => '+88017'.fake()->numerify('########'),
            'address' => fake()->city().', Bangladesh',
            'primary_color' => '#1f6f5c',
            'timezone' => 'Asia/Dhaka',
            'currency' => 'BDT',
            'locale' => 'en',
            'status' => 'active',
        ];
    }

    public function suspended(): static
    {
        return $this->state(fn () => ['status' => 'suspended']);
    }
}
