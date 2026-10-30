<?php

namespace Database\Factories;

use App\Models\Incident;
use App\Models\IncidentAssignment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<IncidentAssignment> */
class IncidentAssignmentFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'incident_id' => Incident::factory(),
            'assigned_to' => User::factory()->responsible()->active(),
            'assigned_by' => User::factory()->administrator()->active(),
            'assigned_at' => now('UTC'),
        ];
    }
}
