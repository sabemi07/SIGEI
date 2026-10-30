<?php

namespace Tests\Feature;

use App\Http\Resources\IncidentReceiptResource;
use App\Models\EmergencyType;
use App\Models\Enums\IncidentStatus;
use App\Models\Incident;
use App\Models\IncidentAssignment;
use App\Models\IncidentUpdate;
use App\Models\Location;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class IncidentAccessTest extends TestCase
{
    use RefreshDatabase;

    private function authenticateAccount(User $user): static
    {
        return $this->actingAs($user)->withSession(['account_auth_version' => $user->refresh()->auth_version]);
    }

    private function addInternalUpdate(Incident $incident, User $author): void
    {
        IncidentUpdate::query()->create([
            'incident_id' => $incident->id,
            'author_id' => $author->id,
            'status' => IncidentStatus::InProgress,
            'internal_note' => 'OBSERVACIÓN INTERNA RESERVADA',
        ]);
    }

    public function test_incident_lists_details_and_assignment_accounts_require_a_session(): void
    {
        $incident = Incident::factory()->create();

        $this->getJson('/api/incidents')->assertUnauthorized();
        $this->getJson('/api/incidents/'.$incident->folio)->assertUnauthorized();
        $this->getJson('/api/responsibles')->assertUnauthorized();
    }

    public function test_responsible_lists_only_current_assignments_and_authored_reports(): void
    {
        $user = User::factory()->active()->create();
        $own = Incident::factory()->create(['reporter_user_id' => $user->id]);
        $ownClosed = Incident::factory()->create(['reporter_user_id' => $user->id, 'status' => IncidentStatus::Closed]);
        $assigned = IncidentAssignment::factory()->create(['assigned_to' => $user->id]);
        $other = Incident::factory()->create();
        $this->authenticateAccount($user);

        $response = $this->getJson('/api/incidents')->assertOk()->assertJsonCount(3, 'data');
        $this->assertEqualsCanonicalizing([$own->folio, $ownClosed->folio, $assigned->incident->folio], array_column($response->json('data'), 'folio'));
        $this->assertNotContains($other->folio, array_column($response->json('data'), 'folio'));
        $this->getJson('/api/incidents?scope=assigned')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.folio', $assigned->incident->folio);
        $this->getJson('/api/incidents?scope=mine')->assertOk()->assertJsonCount(2, 'data');
    }

    public function test_an_administrator_sees_all_incidents_but_mine_still_filters_authorship(): void
    {
        $admin = User::factory()->administrator()->active()->create();
        $own = Incident::factory()->create(['reporter_user_id' => $admin->id]);
        Incident::factory()->count(2)->create();
        $this->authenticateAccount($admin);

        $this->getJson('/api/incidents')->assertOk()->assertJsonCount(3, 'data');
        $this->getJson('/api/incidents?scope=mine')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.folio', $own->folio);
        $this->getJson('/api/incidents?scope=assigned')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_authors_cannot_read_internal_notes_through_list_or_detail(): void
    {
        $reporter = User::factory()->active()->create();
        $assignment = IncidentAssignment::factory()->create([
            'incident_id' => Incident::factory()->create(['reporter_user_id' => $reporter->id])->id,
        ]);
        $this->addInternalUpdate($assignment->incident, $assignment->assignee);
        $this->authenticateAccount($reporter);

        foreach (['/api/incidents', '/api/incidents?scope=mine'] as $path) {
            $response = $this->getJson($path)->assertOk()->assertJsonCount(1, 'data')
                ->assertJsonMissingPath('data.0.observaciones')
                ->assertJsonMissingPath('data.0.seguimientos.0.observacion')
                ->assertJsonMissingPath('data.0.seguimientos.0.autor')
                ->assertJsonMissingPath('data.0.seguimientos.0.autorId')
                ->assertJsonPath('data.0.permisos.verInterno', false);
            $this->assertStringNotContainsString('OBSERVACIÓN INTERNA RESERVADA', $response->getContent());
        }

        $this->getJson('/api/incidents/'.$assignment->incident->folio)->assertOk()
            ->assertJsonMissingPath('data.observaciones')->assertJsonMissingPath('data.seguimientos.0.observacion')
            ->assertJsonPath('data.permisos.seguimiento', false);
    }

    public function test_mine_omits_private_details_even_when_the_reporter_is_administrator_or_current_assignee(): void
    {
        $admin = User::factory()->administrator()->active()->create();
        $own = Incident::factory()->create(['reporter_user_id' => $admin->id]);
        $this->addInternalUpdate($own, $admin);
        $this->authenticateAccount($admin);
        $this->getJson('/api/incidents?scope=mine')->assertOk()->assertJsonMissingPath('data.0.observaciones')
            ->assertJsonMissingPath('data.0.seguimientos.0.observacion')->assertJsonPath('data.0.permisos.verInterno', false);

        $responsible = User::factory()->active()->create();
        $assignment = IncidentAssignment::factory()->create([
            'assigned_to' => $responsible->id,
            'incident_id' => Incident::factory()->create(['reporter_user_id' => $responsible->id])->id,
        ]);
        $this->addInternalUpdate($assignment->incident, $responsible);
        $this->authenticateAccount($responsible);
        $this->getJson('/api/incidents?scope=mine')->assertOk()->assertJsonMissingPath('data.0.observaciones')
            ->assertJsonMissingPath('data.0.seguimientos.0.autor')->assertJsonPath('data.0.permisos.gestionar', false);
    }

    public function test_current_assignees_and_administrators_receive_internal_details(): void
    {
        $assignment = IncidentAssignment::factory()->create();
        $this->addInternalUpdate($assignment->incident, $assignment->assignee);

        foreach ([$assignment->assignee, $assignment->assigner] as $account) {
            $this->authenticateAccount($account);
            $this->getJson('/api/incidents/'.$assignment->incident->folio)->assertOk()
                ->assertJsonPath('data.observaciones', 'OBSERVACIÓN INTERNA RESERVADA')
                ->assertJsonPath('data.seguimientos.0.observacion', 'OBSERVACIÓN INTERNA RESERVADA')
                ->assertJsonPath('data.seguimientos.0.autorId', $assignment->assigned_to)
                ->assertJsonPath('data.permisos.verInterno', true)
                ->assertJsonMissingPath('data.updates')->assertJsonMissingPath('data.audit_logs');
        }
    }

    public function test_unrelated_and_previously_assigned_accounts_get_not_found_for_detail(): void
    {
        $old = IncidentAssignment::factory()->create();
        $old->update(['ended_at' => now()]);
        $new = IncidentAssignment::factory()->create(['incident_id' => $old->incident_id]);

        foreach ([$old->assignee, User::factory()->active()->create()] as $account) {
            $this->authenticateAccount($account);
            $this->getJson('/api/incidents/'.$old->incident->folio)->assertNotFound();
            $this->getJson('/api/incidents?scope=assigned')->assertOk()->assertJsonCount(0, 'data');
        }

        $this->authenticateAccount($new->assignee);
        $this->getJson('/api/incidents/'.$old->incident->folio)->assertOk();
    }

    public function test_lists_paginate_in_server_date_order_and_validate_scope_and_limits(): void
    {
        $admin = User::factory()->administrator()->active()->create();
        $old = Incident::factory()->create(['reported_at' => now()->subDays(2)]);
        Incident::factory()->create(['reported_at' => now()->subDay()]);
        $new = Incident::factory()->create();
        $this->authenticateAccount($admin);

        $this->getJson('/api/incidents?per_page=2')->assertOk()->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.folio', $new->folio)->assertJsonPath('meta.total', 3)->assertJsonPath('meta.last_page', 2);
        $this->getJson('/api/incidents?per_page=2&page=2')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.folio', $old->folio);
        $this->getJson('/api/incidents?per_page=101')->assertUnprocessable();
        $this->getJson('/api/incidents?page=0')->assertUnprocessable();
        $this->getJson('/api/incidents?scope=private')->assertUnprocessable();
    }

    public function test_receipt_projection_does_not_expose_private_data_even_for_an_administrator(): void
    {
        $assignment = IncidentAssignment::factory()->create();
        $this->addInternalUpdate($assignment->incident, $assignment->assignee);
        $request = Request::create('/api/incidents', 'POST');
        $request->setUserResolver(fn (): User => $assignment->assigner);
        $data = (new IncidentReceiptResource($assignment->incident))->resolve($request);

        $this->assertEqualsCanonicalizing([
            'folio', 'tipo', 'ubicacion', 'fecha', 'hora', 'reportadoEn', 'descripcion', 'estado', 'prioridad',
        ], array_keys($data));
        $this->assertStringNotContainsString('OBSERVACIÓN INTERNA RESERVADA', json_encode($data));
    }

    public function test_receipts_use_server_utc_and_display_cancun_dates(): void
    {
        $incident = Incident::factory()->create(['reported_at' => '2026-10-29 03:15:00']);
        $data = (new IncidentReceiptResource($incident->refresh()))->resolve(Request::create('/'));

        $this->assertSame('28/10/2026', $data['fecha']);
        $this->assertSame('22:15', $data['hora']);
        $this->assertSame('2026-10-29T03:15:00.000000Z', $data['reportadoEn']);
    }

    public function test_closed_and_attended_cases_cannot_be_followed_up_by_the_responsible(): void
    {
        $assignment = IncidentAssignment::factory()->create(['received_at' => now()]);
        $this->authenticateAccount($assignment->assignee);

        foreach ([IncidentStatus::Attended, IncidentStatus::Closed] as $status) {
            $assignment->incident->update(['status' => $status]);
            $this->getJson('/api/incidents/'.$assignment->incident->folio)->assertOk()
                ->assertJsonPath('data.permisos.seguimiento', false)
                ->assertJsonPath('data.permisos.confirmarRecepcion', false)
                ->assertJsonPath('data.permisos.cerrar', false);
        }
    }

    public function test_reassigned_in_progress_case_requires_reception_of_the_current_assignment(): void
    {
        $assignment = IncidentAssignment::factory()->create([
            'incident_id' => Incident::factory()->create(['status' => IncidentStatus::InProgress])->id,
        ]);
        $this->authenticateAccount($assignment->assignee);
        $this->getJson('/api/incidents/'.$assignment->incident->folio)->assertOk()
            ->assertJsonPath('data.permisos.confirmarRecepcion', true)
            ->assertJsonPath('data.permisos.seguimiento', false);

        $assignment->update(['received_at' => now(), 'received_by' => $assignment->assigned_to]);
        $this->getJson('/api/incidents/'.$assignment->incident->folio)->assertOk()
            ->assertJsonPath('data.permisos.confirmarRecepcion', false)->assertJsonPath('data.permisos.seguimiento', true);
    }

    public function test_assignment_account_list_is_admin_only_active_and_has_no_credentials(): void
    {
        $admin = User::factory()->administrator()->active()->create();
        $responsible = User::factory()->active()->create();
        User::factory()->inactive()->create();
        $this->authenticateAccount($admin);
        $response = $this->getJson('/api/responsibles')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $responsible->id);
        $this->assertEqualsCanonicalizing(['id', 'nombre', 'rol', 'activo'], array_keys($response->json('data.0')));

        $this->authenticateAccount($responsible);
        $this->getJson('/api/responsibles')->assertForbidden();
    }

    public function test_revoked_accounts_cannot_read_incidents(): void
    {
        $admin = User::factory()->administrator()->active()->create();
        $incident = Incident::factory()->create();
        $this->authenticateAccount($admin);
        DB::table('users')->where('id', $admin->id)->increment('auth_version');

        $this->getJson('/api/incidents')->assertUnauthorized();
        $this->getJson('/api/incidents/'.$incident->folio)->assertUnauthorized();
    }

    public function test_public_settings_expose_only_active_catalogs_and_preserve_details_flag_after_renaming(): void
    {
        $this->seed();
        EmergencyType::query()->firstOrFail()->update(['is_active' => false]);
        Location::query()->where('requires_details', false)->firstOrFail()->update(['is_active' => false]);
        $other = Location::query()->where('requires_details', true)->sole();
        $other->update(['name' => 'Lugar específico']);

        $response = $this->getJson('/api/settings/public')->assertOk()->assertJsonCount(4, 'tipos')->assertJsonCount(9, 'ubicaciones')
            ->assertJsonMissingPath('notify_new_incidents')->assertJsonMissingPath('updated_by');
        $location = collect($response->json('ubicaciones'))->firstWhere('id', $other->id);
        $this->assertSame('Lugar específico', $location['nombre']);
        $this->assertTrue($location['requiereDetalle']);
        $this->assertEqualsCanonicalizing(['institucion', 'siglas', 'tipos', 'ubicaciones'], array_keys($response->json()));
    }
}
