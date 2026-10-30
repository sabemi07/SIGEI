<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureAccountIsActive;
use App\Models\AuditLog;
use App\Models\Enums\IncidentPriority;
use App\Models\Enums\IncidentStatus;
use App\Models\Incident;
use App\Models\InstitutionSetting;
use App\Models\InternalNotification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class IncidentWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    private function asAccount(User $user): static
    {
        $this->actingAs($user->refresh())->withSession(['account_auth_version' => $user->auth_version]);

        return $this;
    }

    private function endpoint(Incident $incident, string $action): string
    {
        return '/api/incidents/'.$incident->folio.'/'.$action;
    }

    /** @return array{User, User, Incident} */
    private function assignedIncident(): array
    {
        $administrator = User::factory()->administrator()->active()->create();
        $responsible = User::factory()->responsible()->active()->create();
        $incident = Incident::factory()->create();
        $this->asAccount($administrator)->postJson($this->endpoint($incident, 'management'), [
            'version' => 0,
            'responsable_id' => $responsible->id,
            'prioridad' => 'Alta',
        ])->assertOk();

        return [$administrator, $responsible, $incident->refresh()];
    }

    public function test_administrator_assigns_an_active_responsible_and_records_audit_and_notification(): void
    {
        [$administrator, $responsible, $incident] = $this->assignedIncident();
        $this->assertSame(IncidentStatus::Pending, $incident->status);
        $this->assertSame(IncidentPriority::High, $incident->priority);
        $this->assertSame(1, $incident->lock_version);
        $this->assertSame($responsible->id, $incident->currentAssignment->assigned_to);
        $this->assertSame($administrator->id, $incident->currentAssignment->assigned_by);
        $this->assertNull($incident->currentAssignment->received_at);
        $this->assertDatabaseHas('audit_logs', ['incident_id' => $incident->id, 'actor_id' => $administrator->id, 'action' => 'incident.managed']);
        $this->assertDatabaseHas('internal_notifications', ['user_id' => $responsible->id, 'incident_id' => $incident->id, 'kind' => 'incident.assigned', 'read_at' => null]);
    }

    public function test_administrator_cannot_assign_an_inactive_account_or_another_administrator(): void
    {
        $administrator = User::factory()->administrator()->active()->create();
        $inactive = User::factory()->inactive()->create();
        $incident = Incident::factory()->create();
        $this->asAccount($administrator);

        foreach ([$inactive->id, $administrator->id] as $invalid) {
            $this->postJson($this->endpoint($incident, 'management'), ['version' => 0, 'responsable_id' => $invalid, 'prioridad' => 'Alta'])
                ->assertUnprocessable()->assertJsonValidationErrors('responsable_id');
        }

        $this->assertDatabaseCount('incident_assignments', 0);
    }

    public function test_only_current_assignee_can_confirm_reception_and_bell_reading_does_not_confirm_it(): void
    {
        [$administrator, $responsible, $incident] = $this->assignedIncident();
        $notification = InternalNotification::query()->where('user_id', $responsible->id)->sole();
        $notification->update(['read_at' => now()]);
        $this->assertNull($incident->currentAssignment->received_at);
        $this->asAccount($administrator)->postJson($this->endpoint($incident, 'receipt'), ['version' => 1])->assertForbidden();
        $this->asAccount(User::factory()->active()->create())->postJson($this->endpoint($incident, 'receipt'), ['version' => 1])->assertForbidden();
        $this->asAccount($responsible)->postJson($this->endpoint($incident, 'receipt'), ['version' => 1])->assertOk();

        $this->assertSame(IncidentStatus::Received, $incident->refresh()->status);
        $this->assertSame($responsible->id, $incident->currentAssignment->received_by);
        $this->assertNotNull($incident->currentAssignment->received_at);
        $this->postJson($this->endpoint($incident, 'receipt'), ['version' => 2])->assertConflict();
    }

    public function test_responsible_must_confirm_before_follow_up_and_cannot_skip_states(): void
    {
        [, $responsible, $incident] = $this->assignedIncident();
        $this->asAccount($responsible);
        $this->postJson($this->endpoint($incident, 'updates'), ['version' => 1, 'estado' => 'En atención', 'observacion' => 'Prueba interna'])->assertConflict();
        $this->postJson($this->endpoint($incident, 'receipt'), ['version' => 1])->assertOk();
        $this->postJson($this->endpoint($incident, 'updates'), ['version' => 2, 'estado' => 'Atendida', 'observacion' => 'No debe saltar estados'])
            ->assertUnprocessable()->assertJsonValidationErrors('estado');
        $this->postJson($this->endpoint($incident, 'updates'), ['version' => 2, 'estado' => 'En atención', 'observacion' => 'Se inicia atención de prueba'])->assertOk();
        $this->postJson($this->endpoint($incident, 'updates'), ['version' => 3, 'estado' => 'Recibida', 'observacion' => 'No debe retroceder'])
            ->assertUnprocessable()->assertJsonValidationErrors('estado');
        $this->assertSame(IncidentStatus::InProgress, $incident->refresh()->status);
    }

    public function test_only_administrator_closes_an_attended_case_and_closed_cases_are_read_only(): void
    {
        [$administrator, $responsible, $incident] = $this->assignedIncident();
        $this->asAccount($administrator)->postJson($this->endpoint($incident, 'close'), ['version' => 1, 'observacion' => 'Cierre prematuro'])->assertConflict();
        $this->asAccount($responsible)->postJson($this->endpoint($incident, 'receipt'), ['version' => 1])->assertOk();
        $this->postJson($this->endpoint($incident, 'updates'), ['version' => 2, 'estado' => 'En atención', 'observacion' => 'En atención de prueba'])->assertOk();
        $this->postJson($this->endpoint($incident, 'updates'), ['version' => 3, 'estado' => 'Atendida', 'observacion' => 'Atención concluida de prueba'])->assertOk();
        $this->postJson($this->endpoint($incident, 'close'), ['version' => 4, 'observacion' => 'Intento no autorizado'])->assertForbidden();
        $this->postJson($this->endpoint($incident, 'updates'), ['version' => 4, 'estado' => 'Atendida', 'observacion' => 'Cambio tras atención'])->assertUnprocessable();
        $this->asAccount($administrator)->postJson($this->endpoint($incident, 'close'), ['version' => 4, 'observacion' => 'Cierre administrativo de prueba'])->assertOk();

        $this->assertSame(IncidentStatus::Closed, $incident->refresh()->status);
        $this->assertSame($administrator->id, $incident->closed_by);
        $this->assertNotNull($incident->closed_at);
        $this->postJson($this->endpoint($incident, 'management'), ['version' => 5, 'responsable_id' => $responsible->id, 'prioridad' => 'Baja'])->assertConflict();
        $this->postJson($this->endpoint($incident, 'close'), ['version' => 5, 'observacion' => 'Repetido'])->assertConflict();
        $this->asAccount($responsible)->postJson($this->endpoint($incident, 'updates'), ['version' => 5, 'estado' => 'En atención', 'observacion' => 'Reabrir'])->assertConflict();
        $this->assertSame(5, $incident->refresh()->lock_version);
    }

    public function test_old_versions_cannot_overwrite_a_more_recent_management_change(): void
    {
        [$administrator, $responsible, $incident] = $this->assignedIncident();
        $this->asAccount($administrator)->postJson($this->endpoint($incident, 'management'), ['version' => 0, 'responsable_id' => $responsible->id, 'prioridad' => 'Baja'])->assertConflict();
        $this->assertSame(IncidentPriority::High, $incident->refresh()->priority);
        $this->assertSame(1, $incident->lock_version);
    }

    public function test_noop_does_not_create_versions_audit_or_notifications(): void
    {
        [$administrator, $responsible, $incident] = $this->assignedIncident();
        $auditCount = AuditLog::query()->count();
        $notificationCount = InternalNotification::query()->count();
        $this->asAccount($administrator)->postJson($this->endpoint($incident, 'management'), ['version' => 1, 'responsable_id' => $responsible->id, 'prioridad' => 'Alta'])->assertOk();

        $this->assertSame(1, $incident->refresh()->lock_version);
        $this->assertSame($auditCount, AuditLog::query()->count());
        $this->assertSame($notificationCount, InternalNotification::query()->count());
    }

    public function test_reassignment_keeps_state_and_old_receipt_but_requires_receipt_from_new_assignee(): void
    {
        [$administrator, $responsible, $incident] = $this->assignedIncident();
        $this->asAccount($responsible)->postJson($this->endpoint($incident, 'receipt'), ['version' => 1])->assertOk();
        $this->postJson($this->endpoint($incident, 'updates'), ['version' => 2, 'estado' => 'En atención', 'observacion' => 'Prueba interna'])->assertOk();
        $next = User::factory()->responsible()->active()->create();
        $this->asAccount($administrator)->postJson($this->endpoint($incident, 'management'), ['version' => 3, 'responsable_id' => $next->id, 'prioridad' => 'Alta'])->assertOk();

        $this->assertSame(IncidentStatus::InProgress, $incident->refresh()->status);
        $this->assertNull($incident->currentAssignment->received_at);
        $oldAssignment = $incident->assignments()->whereNotNull('ended_at')->sole();
        $this->assertNotNull($oldAssignment->received_at);
        $this->assertSame($responsible->id, $oldAssignment->received_by);
        $this->asAccount($responsible)->postJson($this->endpoint($incident, 'updates'), ['version' => 4, 'estado' => 'Atendida', 'observacion' => 'Viejo responsable'])->assertForbidden();
        $this->asAccount($next)->postJson($this->endpoint($incident, 'updates'), ['version' => 4, 'estado' => 'Atendida', 'observacion' => 'Falta confirmar'])->assertConflict();
        $this->postJson($this->endpoint($incident, 'receipt'), ['version' => 4])->assertOk();
        $this->assertSame(IncidentStatus::InProgress, $incident->refresh()->status);
        $this->postJson($this->endpoint($incident, 'updates'), ['version' => 5, 'estado' => 'Atendida', 'observacion' => 'Atención del nuevo responsable'])->assertOk();
    }

    public function test_priority_must_be_evaluated_before_attention_and_internal_notes_require_text(): void
    {
        [$administrator, $responsible, $incident] = $this->assignedIncident();
        $this->asAccount($administrator)->postJson($this->endpoint($incident, 'management'), ['version' => 1, 'responsable_id' => $responsible->id, 'prioridad' => 'Por evaluar'])->assertOk();
        $this->asAccount($responsible)->postJson($this->endpoint($incident, 'receipt'), ['version' => 2])->assertOk();
        $this->postJson($this->endpoint($incident, 'updates'), ['version' => 3, 'estado' => 'En atención', 'observacion' => 'Sin prioridad'])->assertUnprocessable();
        $this->postJson($this->endpoint($incident, 'updates'), ['version' => 3, 'estado' => 'Recibida', 'observacion' => '   '])->assertUnprocessable()->assertJsonValidationErrors('observacion');
    }

    public function test_reporter_cannot_manage_follow_or_close_a_case_only_because_they_created_it(): void
    {
        [$administrator, $responsible, $incident] = $this->assignedIncident();
        $reporter = User::factory()->responsible()->active()->create();
        $incident->update(['reporter_user_id' => $reporter->id]);
        $this->asAccount($reporter);
        $this->postJson($this->endpoint($incident, 'management'), ['version' => 1, 'responsable_id' => $reporter->id, 'prioridad' => 'Alta'])->assertForbidden();
        $this->postJson($this->endpoint($incident, 'receipt'), ['version' => 1])->assertForbidden();
        $this->postJson($this->endpoint($incident, 'updates'), ['version' => 1, 'estado' => 'Recibida', 'observacion' => 'No autorizado'])->assertForbidden();
        $this->postJson($this->endpoint($incident, 'close'), ['version' => 1, 'observacion' => 'No autorizado'])->assertForbidden();
    }

    public function test_management_does_not_accept_state_changes_and_responsible_cannot_forge_receipt_author(): void
    {
        [$administrator, $responsible, $incident] = $this->assignedIncident();
        $this->asAccount($administrator)->postJson($this->endpoint($incident, 'management'), ['version' => 1, 'responsable_id' => $responsible->id, 'prioridad' => 'Alta', 'estado' => 'Recibida'])
            ->assertUnprocessable()->assertJsonValidationErrors('estado');
        $this->asAccount($responsible)->postJson($this->endpoint($incident, 'receipt'), ['version' => 1, 'received_by' => $administrator->id])
            ->assertUnprocessable()->assertJsonValidationErrors('received_by');
        $this->assertNull($incident->currentAssignment->received_at);
    }

    public function test_generic_notifications_do_not_leak_internal_notes_and_honor_preferences(): void
    {
        [$administrator, $responsible, $incident] = $this->assignedIncident();
        $this->asAccount($responsible)->postJson($this->endpoint($incident, 'receipt'), ['version' => 1])->assertOk();
        $this->postJson($this->endpoint($incident, 'updates'), ['version' => 2, 'estado' => 'En atención', 'observacion' => 'NOTA-INTERNA-PRIVADA'])->assertOk();
        $notice = InternalNotification::query()->where('user_id', $administrator->id)->latest('created_at')->firstOrFail();
        $this->assertStringNotContainsString('NOTA-INTERNA-PRIVADA', $notice->body);
        $before = InternalNotification::query()->count();
        InstitutionSetting::query()->findOrFail('institution')->update(['notify_updates' => false]);
        $this->postJson($this->endpoint($incident, 'updates'), ['version' => 3, 'estado' => 'En atención', 'observacion' => 'Otra observación'])->assertOk();
        $this->assertSame($before, InternalNotification::query()->count());
    }

    public function test_mutations_revalidate_auth_version_inside_the_transaction(): void
    {
        [$administrator, $responsible, $incident] = $this->assignedIncident();
        $this->asAccount($administrator);
        DB::table('users')->where('id', $administrator->id)->increment('auth_version');
        $this->withoutMiddleware(EnsureAccountIsActive::class)->postJson($this->endpoint($incident, 'management'), ['version' => 1, 'responsable_id' => $responsible->id, 'prioridad' => 'Baja'])->assertUnauthorized();
        $this->assertSame(IncidentPriority::High, $incident->refresh()->priority);
    }
}
