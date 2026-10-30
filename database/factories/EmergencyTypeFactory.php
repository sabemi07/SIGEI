<?php

namespace Database\Factories;

use App\Models\EmergencyType;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<EmergencyType> */
class EmergencyTypeFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['name' => fake()->unique()->words(3, true), 'is_active' => true];
    }
}
