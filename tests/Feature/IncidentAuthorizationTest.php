<?php

namespace Tests\Feature;

use App\Models\Enums\IncidentStatus;
use App\Models\Incident;
use App\Models\IncidentAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class IncidentAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_administrators_manage_accounts(): void
    {
        $target = User::factory()->create();
        $admin = User::factory()->administrator()->active()->create();
        $responsible = User::factory()->responsible()->active()->create();
        $this->assertTrue(Gate::forUser($admin)->allows('update', $target));
        $this->assertFalse(Gate::forUser($responsible)->allows('update', $target));
        $admin->is_active = false;
        $this->assertFalse(Gate::forUser($admin)->allows('create', User::class));
    }

    public function test_authorship_allows_consultation_but_not_internal_details_or_follow_up(): void
    {
        $reporter = User::factory()->active()->create();
        $incident = Incident::factory()->create(['reporter_user_id' => $reporter->id]);
        $this->assertTrue(Gate::forUser($reporter)->allows('view', $incident));
        $this->assertFalse(Gate::forUser($reporter)->allows('viewInternal', $incident));
        $this->assertFalse(Gate::forUser($reporter)->allows('followUp', $incident));
        $this->assertFalse(Gate::forUser(User::factory()->active()->create())->allows('view', $incident));
        $this->assertFalse(Gate::forUser(null)->allows('view', $incident));
    }

    public function test_current_assignee_can_view_internal_details_and_confirm_reception_once(): void
    {
        $assignment = IncidentAssignment::factory()->create();
        $gate = Gate::forUser($assignment->assignee);
        $this->assertTrue($gate->allows('view', $assignment->incident));
        $this->assertTrue($gate->allows('viewInternal', $assignment->incident));
        $this->assertTrue($gate->allows('confirmReceipt', $assignment->incident));
        $this->assertFalse($gate->allows('assign', $assignment->incident));
        $this->assertFalse($gate->allows('close', $assignment->incident));
        $assignment->update(['received_at' => now(), 'received_by' => $assignment->assigned_to]);
        $this->assertFalse($gate->allows('confirmReceipt', $assignment->incident));
    }

    public function test_an_old_assignment_does_not_grant_access_after_reassignment(): void
    {
        $old = IncidentAssignment::factory()->create();
        $old->update(['ended_at' => now()]);
        $new = IncidentAssignment::factory()->create(['incident_id' => $old->incident_id]);
        $this->assertFalse(Gate::forUser($old->assignee)->allows('view', $old->incident));
        $this->assertFalse(Gate::forUser($old->assignee)->allows('viewInternal', $old->incident));
        $this->assertTrue(Gate::forUser($new->assignee)->allows('view', $old->incident));
    }

    public function test_closed_incidents_are_read_only_and_only_administrators_close_attended_cases(): void
    {
        $assignment = IncidentAssignment::factory()->create();
        $admin = $assignment->assigner;
        $incident = $assignment->incident;
        $this->assertTrue(Gate::forUser($admin)->allows('assign', $incident));
        $this->assertFalse(Gate::forUser($admin)->allows('close', $incident));
        $incident->update(['status' => IncidentStatus::Attended]);
        $this->assertTrue(Gate::forUser($admin)->allows('close', $incident));
        $this->assertFalse(Gate::forUser($assignment->assignee)->allows('close', $incident));
        $incident->update(['status' => IncidentStatus::Closed]);
        $this->assertTrue(Gate::forUser($admin)->allows('view', $incident));
        $this->assertTrue(Gate::forUser($assignment->assignee)->allows('view', $incident));
        $this->assertFalse(Gate::forUser($admin)->allows('assign', $incident));
        $this->assertFalse(Gate::forUser($admin)->allows('close', $incident));
        $this->assertFalse(Gate::forUser($assignment->assignee)->allows('followUp', $incident));
        $assignment->assignee->is_active = false;
        $this->assertFalse(Gate::forUser($assignment->assignee)->allows('view', $incident));
    }
}
