<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\EmergencyType;
use App\Models\Enums\IncidentPriority;
use App\Models\Enums\IncidentStatus;
use App\Models\Incident;
use App\Models\InternalNotification;
use App\Models\Location;
use App\Models\User;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PublicIncidentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['sigei.public_reports_per_session' => 1000, 'sigei.public_reports_per_ip' => 1000]);
        $this->seed();
    }

    /** @return array<string, mixed> */
    private function reportData(array $overrides = []): array
    {
        return array_replace([
            'tipo_id' => EmergencyType::query()->firstOrFail()->id,
            'ubicacion_id' => Location::query()->where('requires_details', false)->firstOrFail()->id,
            'descripcion' => 'Reporte utilizado únicamente en pruebas.',
            'solicitud_id' => (string) Str::uuid(),
        ], $overrides);
    }

    public function test_any_guest_can_create_a_persistent_incident_with_server_initial_values(): void
    {
        $this->travelTo(now('UTC')->setDateTime(2026, 10, 29, 3, 15, 0));
        $administrator = User::factory()->administrator()->active()->create();
        $this->getJson('/api/session')->assertOk();
        $response = $this->postJson('/api/incidents', $this->reportData())
            ->assertCreated()->assertJsonMissingPath('data.observaciones')
            ->assertJsonMissingPath('data.seguimientos')->assertJsonMissingPath('data.reportanteId');
        $incident = Incident::query()->sole();

        $this->assertSame($incident->folio, $response->json('data.folio'));
        $this->assertMatchesRegularExpression('/^SIG-[0-9A-HJKMNP-TV-Z]{26}$/', $incident->folio);
        $this->assertSame(IncidentStatus::Pending, $incident->status);
        $this->assertSame(IncidentPriority::Unevaluated, $incident->priority);
        $this->assertNull($incident->reporter_user_id);
        $this->assertNull($incident->currentAssignment);
        $this->assertSame(0, $incident->lock_version);
        $this->assertSame('2026-10-29 03:15', $incident->reported_at->format('Y-m-d H:i'));
        $this->assertSame('28/10/2026', $response->json('data.fecha'));
        $this->assertSame('22:15', $response->json('data.hora'));
        $this->assertNull(AuditLog::query()->sole()->actor_id);
        $this->assertSame('incident.created', AuditLog::query()->sole()->action);
        $this->assertDatabaseHas('internal_notifications', ['user_id' => $administrator->id, 'incident_id' => $incident->id, 'kind' => 'incident.created']);
    }

    public function test_a_zero_description_or_location_detail_is_preserved_in_the_receipt(): void
    {
        $location = Location::query()->where('requires_details', true)->sole();
        $this->postJson('/api/incidents', $this->reportData([
            'ubicacion_id' => $location->id,
            'otro_lugar' => '0',
            'descripcion' => '0',
        ]))->assertCreated()->assertJsonPath('data.descripcion', '0')
            ->assertJsonPath('data.ubicacion', $location->name.' · 0');
    }

    public function test_omitted_description_is_optional_and_an_authenticated_reporter_is_derived_from_the_session(): void
    {
        $reporter = User::factory()->responsible()->active()->create();
        $this->actingAs($reporter)->withSession(['account_auth_version' => $reporter->auth_version]);
        $data = $this->reportData();
        unset($data['descripcion']);
        $this->postJson('/api/incidents', $data)->assertCreated();

        $this->assertNull(Incident::query()->sole()->description);
        $this->assertSame($reporter->id, Incident::query()->sole()->reporter_user_id);
        $this->assertSame($reporter->id, AuditLog::query()->sole()->actor_id);
    }

    public function test_other_place_requires_details_by_catalog_flag_even_after_renaming(): void
    {
        $location = Location::query()->where('requires_details', true)->sole();
        $location->update(['name' => 'Ubicación personalizada']);
        $data = $this->reportData(['ubicacion_id' => $location->id]);
        $this->postJson('/api/incidents', $data)->assertUnprocessable()->assertJsonValidationErrors('otro_lugar');
        $data['otro_lugar'] = '  Aula 203 del edificio D  ';
        $this->postJson('/api/incidents', $data)->assertCreated();

        $this->assertSame('Aula 203 del edificio D', Incident::query()->sole()->location_details);
        $this->assertSame('Ubicación personalizada', Incident::query()->sole()->location_name);
    }

    public function test_inactive_catalog_entries_cannot_create_new_incidents(): void
    {
        $type = EmergencyType::query()->firstOrFail();
        $type->update(['is_active' => false]);
        $this->postJson('/api/incidents', $this->reportData(['tipo_id' => $type->id]))
            ->assertUnprocessable()->assertJsonValidationErrors('tipo_id');
        $type->update(['is_active' => true]);
        $location = Location::query()->where('requires_details', false)->firstOrFail();
        $location->update(['is_active' => false]);
        $this->postJson('/api/incidents', $this->reportData(['ubicacion_id' => $location->id]))
            ->assertUnprocessable()->assertJsonValidationErrors('ubicacion_id');
        $this->assertDatabaseCount('incidents', 0);
    }

    /** @return array<string, array{string, mixed}> */
    public static function forgedAttributes(): array
    {
        return [
            'folio' => ['folio', 'SIG-CLIENTE'],
            'estado' => ['estado', 'Cerrada'],
            'prioridad' => ['prioridad', 'Alta'],
            'responsable' => ['responsable_id', 42],
            'reportante' => ['reporter_user_id', 42],
            'fecha' => ['reported_at', '2000-01-01'],
            'cierre' => ['closed_by', 42],
            'observaciones internas' => ['observaciones', 'Dato impuesto'],
            'honeypot' => ['website', 'https://bot.example.test'],
        ];
    }

    #[DataProvider('forgedAttributes')]
    public function test_client_cannot_impose_server_owned_attributes(string $field, mixed $value): void
    {
        $this->postJson('/api/incidents', $this->reportData([$field => $value]))
            ->assertUnprocessable()->assertJsonValidationErrors($field);
        $this->assertDatabaseCount('incidents', 0);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_validation_rejects_invalid_catalog_ids_request_ids_and_excessive_text(): void
    {
        $this->postJson('/api/incidents', $this->reportData(['tipo_id' => 999999, 'ubicacion_id' => [], 'solicitud_id' => 'not-a-uuid', 'descripcion' => str_repeat('x', 1001)]))
            ->assertUnprocessable()->assertJsonValidationErrors(['tipo_id', 'ubicacion_id', 'solicitud_id', 'descripcion']);
        $this->assertDatabaseCount('incidents', 0);
    }

    public function test_replaying_the_same_request_does_not_duplicate_incidents_audit_or_notifications(): void
    {
        User::factory()->administrator()->active()->create();
        $this->getJson('/api/session');
        $this->withCookie(config('session.cookie'), session()->getId())->withCredentials();
        $data = $this->reportData();
        $folio = $this->postJson('/api/incidents', $data)->assertCreated()->json('data.folio');
        $this->postJson('/api/incidents', $data)->assertCreated()->assertJsonPath('data.folio', $folio);

        $this->assertDatabaseCount('incidents', 1);
        $this->assertDatabaseCount('audit_logs', 1);
        $this->assertSame(1, InternalNotification::query()->count());
    }

    public function test_replaying_a_request_with_changed_content_is_a_conflict_without_receipt_disclosure(): void
    {
        $this->getJson('/api/session');
        $this->withCookie(config('session.cookie'), session()->getId())->withCredentials();
        $data = $this->reportData();
        $this->postJson('/api/incidents', $data)->assertCreated();
        $this->postJson('/api/incidents', array_replace($data, ['descripcion' => 'Contenido diferente']))
            ->assertConflict()->assertJsonMissingPath('data.folio');

        $this->assertDatabaseCount('incidents', 1);
    }

    public function test_another_session_cannot_reuse_a_submission_to_obtain_its_folio(): void
    {
        $this->getJson('/api/session');
        $this->withCookie(config('session.cookie'), session()->getId())->withCredentials();
        $data = $this->reportData();
        $this->postJson('/api/incidents', $data)->assertCreated();
        session()->regenerate();
        $this->withCookie(config('session.cookie'), session()->getId());
        $this->postJson('/api/incidents', $data)->assertConflict()->assertJsonMissingPath('data.folio');
        $this->assertDatabaseCount('incidents', 1);
    }

    public function test_a_revoked_session_is_not_silently_converted_into_a_public_report(): void
    {
        $reporter = User::factory()->active()->create(['auth_version' => 1]);
        $this->actingAs($reporter)->withSession(['account_auth_version' => 0]);
        $this->postJson('/api/incidents', $this->reportData())->assertUnauthorized()->assertJsonStructure(['csrf_token']);

        $this->assertDatabaseCount('incidents', 0);
        $this->assertGuest();
    }

    public function test_public_creation_requires_real_csrf_verification(): void
    {
        $this->app->bind(PreventRequestForgery::class, fn (Application $app): PreventRequestForgery => new class($app, $app['encrypter']) extends PreventRequestForgery
        {
            protected function runningUnitTests(): bool
            {
                return false;
            }
        });
        $data = $this->reportData();
        $this->postJson('/api/incidents', $data)->assertStatus(419);
        $this->assertDatabaseCount('incidents', 0);
        $this->withSession(['_token' => 'public-test-csrf'])->withHeader('X-CSRF-TOKEN', 'public-test-csrf')
            ->postJson('/api/incidents', $data)->assertCreated();
    }
}
