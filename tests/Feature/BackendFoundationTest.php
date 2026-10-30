<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\EmergencyType;
use App\Models\Enums\AccountRole;
use App\Models\Enums\IncidentPriority;
use App\Models\Enums\IncidentStatus;
use App\Models\Incident;
use App\Models\IncidentAssignment;
use App\Models\IncidentUpdate;
use App\Models\InstitutionSetting;
use App\Models\InternalNotification;
use App\Models\Location;
use App\Models\PushSubscription;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use LogicException;
use Tests\TestCase;

class BackendFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_accounts_are_inactive_responsibles_without_privileged_mass_assignment(): void
    {
        $user = User::query()->create([
            'name' => 'Cuenta pendiente',
            'email' => 'pendiente@example.test',
            'password' => 'solo-para-pruebas',
        ])->refresh();

        $this->assertSame(AccountRole::Responsible, $user->role);
        $this->assertFalse($user->is_active);
        $this->assertFalse($user->isFillable('role'));
        $this->assertFalse($user->isFillable('is_active'));
    }

    public function test_public_incidents_have_server_folios_and_initial_values(): void
    {
        $incident = Incident::factory()->create()->refresh();

        $this->assertMatchesRegularExpression('/^SIG-[0-9A-HJKMNP-TV-Z]{26}$/', $incident->folio);
        $this->assertNull($incident->reporter_user_id);
        $this->assertNull($incident->currentAssignment);
        $this->assertSame(IncidentStatus::Pending, $incident->status);
        $this->assertSame(IncidentPriority::Unevaluated, $incident->priority);
        $this->assertNotSame($incident->folio, Incident::factory()->create()->folio);
    }

    public function test_folios_and_catalog_snapshots_are_generated_even_if_supplied(): void
    {
        $incident = Incident::factory()->make();
        $incident->folio = 'folio-enviado-por-cliente';
        $incident->type_name = 'Tipo falso';
        $incident->location_name = 'Ubicación falsa';
        $incident->save();

        $this->assertStringStartsWith('SIG-', $incident->folio);
        $this->assertSame($incident->emergencyType->name, $incident->type_name);
        $this->assertSame($incident->location->name, $incident->location_name);

        $incident->emergencyType->update(['name' => 'Nombre modificado', 'is_active' => false]);
        $incident->location->update(['name' => 'Lugar modificado', 'is_active' => false]);

        $this->assertNotSame('Nombre modificado', $incident->refresh()->type_name);
        $this->assertNotSame('Lugar modificado', $incident->location_name);
    }

    public function test_existing_folios_cannot_be_changed_through_the_model(): void
    {
        $incident = Incident::factory()->create();
        $incident->folio = 'SIG-'.Str::ulid();

        $this->expectException(LogicException::class);
        $incident->save();
    }

    public function test_database_rejects_duplicate_folios_without_model_events(): void
    {
        $incident = Incident::factory()->create();
        $duplicate = $incident->getAttributes();
        unset($duplicate['id']);

        $this->expectException(QueryException::class);
        DB::table('incidents')->insert($duplicate);
    }

    public function test_database_rejects_unknown_incident_status(): void
    {
        $incident = Incident::factory()->create();

        $this->expectException(QueryException::class);
        DB::table('incidents')->where('id', $incident->id)->update(['status' => 'Inventado']);
    }

    public function test_database_rejects_nonexistent_reporters(): void
    {
        $incident = Incident::factory()->create();

        $this->expectException(QueryException::class);
        DB::table('incidents')->where('id', $incident->id)->update(['reporter_user_id' => 999999]);
    }

    public function test_catalogs_referenced_by_incidents_cannot_be_deleted(): void
    {
        $incident = Incident::factory()->create();

        $this->expectException(QueryException::class);
        $incident->emergencyType->delete();
    }

    public function test_reporter_and_assignee_are_independent_accounts(): void
    {
        $reporter = User::factory()->active()->create();
        $incident = Incident::factory()->create(['reporter_user_id' => $reporter->id]);
        $assignment = IncidentAssignment::factory()->create(['incident_id' => $incident->id]);

        $this->assertTrue($reporter->reportedIncidents->sole()->is($incident));
        $this->assertTrue($assignment->assignee->assignments->sole()->is($assignment));
        $this->assertTrue($incident->reporter->is($reporter));
        $this->assertNotSame($assignment->assigned_to, $reporter->id);
    }

    public function test_database_allows_only_one_open_assignment_per_incident(): void
    {
        $assignment = IncidentAssignment::factory()->create();

        $this->expectException(QueryException::class);
        IncidentAssignment::factory()->create(['incident_id' => $assignment->incident_id]);
    }

    public function test_reassignment_preserves_previous_reception_and_starts_unconfirmed(): void
    {
        $old = IncidentAssignment::factory()->create();
        $old->update(['received_at' => now(), 'received_by' => $old->assigned_to, 'ended_at' => now()]);
        $new = IncidentAssignment::factory()->create(['incident_id' => $old->incident_id]);

        $this->assertNotNull($old->refresh()->received_at);
        $this->assertNull($new->received_at);
        $this->assertNull($new->received_by);
        $this->assertTrue($old->incident->currentAssignment->is($new));
        $this->assertCount(2, $old->incident->assignments);
    }

    public function test_notification_reading_is_per_account_and_does_not_confirm_reception(): void
    {
        $assignment = IncidentAssignment::factory()->create();
        $data = ['incident_id' => $assignment->incident_id, 'kind' => 'assignment', 'title' => 'Caso asignado', 'body' => 'Consulta el caso en SIGEI.'];
        $first = InternalNotification::query()->create(['user_id' => $assignment->assigned_to] + $data);
        $second = InternalNotification::query()->create(['user_id' => $assignment->assigned_by] + $data);

        $first->update(['read_at' => now()]);

        $this->assertNotNull($first->refresh()->read_at);
        $this->assertNull($second->refresh()->read_at);
        $this->assertNull($assignment->refresh()->received_at);
        $this->assertTrue($assignment->assignee->internalNotifications->sole()->is($first));
    }

    public function test_internal_updates_and_audit_preserve_author_and_omit_internal_content_from_serialization(): void
    {
        $assignment = IncidentAssignment::factory()->create();
        $update = IncidentUpdate::query()->create([
            'incident_id' => $assignment->incident_id,
            'author_id' => $assignment->assigned_to,
            'status' => IncidentStatus::InProgress,
            'internal_note' => 'Observación interna de prueba',
        ]);
        $audit = AuditLog::query()->create([
            'actor_id' => $assignment->assigned_to,
            'incident_id' => $assignment->incident_id,
            'action' => 'incident.updated',
            'subject_type' => 'incident',
            'subject_id' => $assignment->incident_id,
            'metadata' => ['internal' => 'Dato interno'],
        ]);

        $this->assertTrue($update->author->is($assignment->assignee));
        $this->assertNotNull($audit->created_at);
        $this->assertArrayNotHasKey('metadata', $audit->toArray());
        $this->assertArrayNotHasKey('internal_note', $update->toArray());

        $this->expectException(QueryException::class);
        $assignment->assignee->delete();
    }

    public function test_seeding_does_not_create_demo_accounts_or_reset_preferences(): void
    {
        $this->seed();
        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('incidents', 0);
        $this->assertDatabaseCount('emergency_types', 5);
        $this->assertDatabaseCount('locations', 10);

        InstitutionSetting::query()->firstOrFail()->update(['name' => 'Institución configurada', 'notify_updates' => false]);
        EmergencyType::query()->firstOrFail()->update(['is_active' => false]);
        $otherLocation = Location::query()->where('requires_details', true)->sole();
        $otherLocation->update(['name' => 'Ubicación especificada', 'is_active' => false]);
        $this->seed();

        $this->assertSame('Institución configurada', InstitutionSetting::query()->sole()->name);
        $this->assertFalse(InstitutionSetting::query()->sole()->notify_updates);
        $this->assertSame(1, EmergencyType::query()->where('is_active', false)->count());
        $this->assertTrue($otherLocation->refresh()->requires_details);
        $this->assertFalse($otherLocation->is_active);
        $this->assertDatabaseCount('locations', 10);
        $this->assertDatabaseCount('emergency_types', 5);
    }

    public function test_institution_settings_are_a_singleton(): void
    {
        $this->seed();

        $this->expectException(QueryException::class);
        InstitutionSetting::query()->create(['scope' => 'other', 'name' => 'Otra', 'abbreviation' => 'OTRA']);
    }

    public function test_database_prevents_duplicate_institution_settings(): void
    {
        $this->seed();

        $this->expectException(QueryException::class);
        InstitutionSetting::query()->create(['scope' => 'institution', 'name' => 'Duplicada', 'abbreviation' => 'DUP']);
    }

    public function test_timestamps_preserve_utc_and_can_be_displayed_in_cancun(): void
    {
        $this->travelTo(now('UTC')->setDateTime(2026, 10, 29, 3, 15, 0));
        $incident = Incident::factory()->create()->refresh();

        $this->assertSame('UTC', config('app.timezone'));
        $this->assertSame('America/Cancun', config('sigei.display_timezone'));
        $this->assertSame('2026-10-29 03:15', $incident->reported_at->format('Y-m-d H:i'));
        $this->assertSame('2026-10-28 22:15', $incident->reported_at->setTimezone(config('sigei.display_timezone'))->format('Y-m-d H:i'));

        if (DB::getDriverName() === 'mysql') {
            $this->assertSame('+00:00', DB::selectOne('SELECT @@session.time_zone AS timezone')->timezone);
        }
    }

    public function test_push_subscriptions_belong_to_account_and_device_and_hide_secrets(): void
    {
        $user = User::factory()->create();
        $subscription = PushSubscription::query()->create([
            'user_id' => $user->id,
            'device_id' => Str::uuid()->toString(),
            'endpoint' => 'https://push.example.test/device-one',
            'public_key' => 'public-key-for-tests',
            'auth_token' => 'token-for-tests',
        ]);

        $this->assertTrue($user->pushSubscriptions->sole()->is($subscription));
        $this->assertSame(hash('sha256', $subscription->endpoint), $subscription->endpoint_hash);
        $this->assertNull($subscription->revoked_at);
        $this->assertNull($subscription->expires_at);

        foreach (['endpoint', 'endpoint_hash', 'public_key', 'auth_token'] as $key) {
            $this->assertArrayNotHasKey($key, $subscription->toArray());
        }

        $this->expectException(QueryException::class);
        PushSubscription::query()->create([
            'user_id' => User::factory()->create()->id,
            'device_id' => Str::uuid()->toString(),
            'endpoint' => $subscription->endpoint,
            'public_key' => 'other-key',
            'auth_token' => 'other-token',
        ]);
    }

    public function test_an_account_can_have_multiple_devices_but_only_one_subscription_per_device(): void
    {
        $user = User::factory()->create();
        $device = Str::uuid()->toString();
        $data = ['user_id' => $user->id, 'public_key' => 'test-key', 'auth_token' => 'test-token'];
        PushSubscription::query()->create($data + ['device_id' => $device, 'endpoint' => 'https://push.example.test/one']);
        PushSubscription::query()->create($data + ['device_id' => Str::uuid()->toString(), 'endpoint' => 'https://push.example.test/two']);

        $this->assertCount(2, $user->pushSubscriptions);

        $this->expectException(QueryException::class);
        PushSubscription::query()->create($data + ['device_id' => $device, 'endpoint' => 'https://push.example.test/three']);
    }
}
