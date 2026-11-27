<?php

namespace Database\Factories;

use App\Models\EmergencyType;
use App\Models\Enums\IncidentPriority;
use App\Models\Enums\IncidentStatus;
use App\Models\Incident;
use App\Models\Location;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Incident> */
class IncidentFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'emergency_type_id' => EmergencyType::factory(),
            'location_id' => Location::factory(),
            'description' => fake()->sentence(),
            'reporter_user_id' => null,
            'status' => IncidentStatus::Pending,
            'priority' => IncidentPriority::Unevaluated,
            'reported_at' => now('UTC'),
        ];
    }
}
