<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureAccountIsActive;
use App\Models\AuditLog;
use App\Models\Incident;
use App\Models\InstitutionSetting;
use App\Models\User;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class InstitutionSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    private function asAccount(User $user): static
    {
        return $this->actingAs($user->refresh())->withSession(['account_auth_version' => $user->auth_version]);
    }

    /** @return array<string, mixed> */
    private function settingsData(): array
    {
        return $this->getJson('/api/settings')->assertOk()->json('data');
    }

    public function test_only_active_administrators_can_read_and_update_settings(): void
    {
        $this->getJson('/api/settings')->assertUnauthorized();
        $this->putJson('/api/settings', [])->assertUnauthorized();
        $this->asAccount(User::factory()->responsible()->active()->create());
        $this->getJson('/api/settings')->assertForbidden();
        $this->putJson('/api/settings', [])->assertForbidden();
        $this->asAccount(User::factory()->administrator()->inactive()->create());
        $this->getJson('/api/settings')->assertUnauthorized();
        $this->putJson('/api/settings', [])->assertUnauthorized();
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_write_rechecks_the_actor_after_acquiring_the_institution_lock(): void
    {
        $administrator = User::factory()->administrator()->active()->create();
        $this->asAccount($administrator);
        $data = $this->settingsData();
        $data['siglas'] = 'CAMBIO';
        DB::table('users')->where('id', $administrator->id)->increment('auth_version');
        $this->withoutMiddleware(EnsureAccountIsActive::class)->putJson('/api/settings', $data)->assertForbidden();
        $this->assertSame('SIGEI', InstitutionSetting::query()->sole()->abbreviation);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_administrator_can_persist_institution_catalogs_and_notification_preferences(): void
    {
        $administrator = User::factory()->administrator()->active()->create();
        $this->asAccount($administrator);
        $data = $this->settingsData();
        $data['institucion'] = '  Escuela de pruebas  ';
        $data['siglas'] = '  EDP  ';
        $data['notificarNuevos'] = false;
        $data['notificarAsignaciones'] = false;
        $data['notificarSeguimiento'] = false;
        $data['tipos'][0]['nombre'] = 'Atención médica';
        $data['tipos'][] = ['id' => null, 'nombre' => '  Riesgo eléctrico  ', 'activo' => true];
        $data['tipos'][] = ['id' => null, 'nombre' => 'Riesgo estructural', 'activo' => true];
        $data['ubicaciones'][] = ['nombre' => 'Anexo', 'activo' => true, 'requiereDetalle' => true];

        $saved = $this->putJson('/api/settings', $data)->assertOk()
            ->assertJsonPath('data.institucion', 'Escuela de pruebas')->assertJsonPath('data.siglas', 'EDP')
            ->assertJsonPath('data.version', 1)->assertJsonPath('data.notificarNuevos', false)
            ->assertJsonPath('data.notificarAsignaciones', false)->assertJsonPath('data.notificarSeguimiento', false)
            ->assertJsonCount(7, 'data.tipos')->assertJsonCount(11, 'data.ubicaciones')->json('data');
        $this->assertIsInt($saved['tipos'][5]['id']);
        $this->assertSame('Riesgo eléctrico', $saved['tipos'][5]['nombre']);
        $this->assertSame($saved, $this->settingsData());
        $this->assertDatabaseHas('institution_settings', ['scope' => 'institution', 'updated_by' => $administrator->id, 'lock_version' => 1]);
        $audit = AuditLog::query()->where('action', 'settings.updated')->sole();
        $this->assertSame($administrator->id, $audit->actor_id);
        $this->assertNotNull($audit->created_at);
        $this->assertSame('SIGEI', $audit->metadata['before']['siglas']);
        $this->assertEquals($saved, $audit->metadata['after']);
        $this->getJson('/api/settings/public')->assertOk()->assertJsonPath('siglas', 'EDP')
            ->assertJsonMissingPath('notificarNuevos')->assertJsonMissingPath('updated_by')->assertJsonMissingPath('version');
    }

    public function test_deactivation_preserves_rows_and_incident_snapshots_and_can_be_reversed(): void
    {
        $administrator = User::factory()->administrator()->active()->create();
        $this->asAccount($administrator);
        $data = $this->settingsData();
        $incident = Incident::factory()->create(['emergency_type_id' => $data['tipos'][0]['id'], 'location_id' => $data['ubicaciones'][0]['id']]);
        $originalNames = $incident->only(['type_name', 'location_name']);
        $data['tipos'][0]['nombre'] = 'Tipo renombrado';
        $data['tipos'][0]['activo'] = false;
        $data['ubicaciones'][0]['nombre'] = 'Zona renombrada';
        $data['ubicaciones'][0]['activo'] = false;
        $saved = $this->putJson('/api/settings', $data)->assertOk()->json('data');
        $this->assertDatabaseCount('emergency_types', 5);
        $this->assertDatabaseCount('locations', 10);
        $this->assertSame($originalNames, $incident->refresh()->only(['type_name', 'location_name']));
        $this->getJson('/api/settings/public')->assertJsonCount(4, 'tipos')->assertJsonCount(9, 'ubicaciones');
        $this->getJson('/api/incidents/'.$incident->folio)->assertOk()->assertJsonPath('data.tipo', $originalNames['type_name']);
        $saved['tipos'][0]['activo'] = true;
        $saved['ubicaciones'][0]['activo'] = true;
        $this->putJson('/api/settings', $saved)->assertOk()->assertJsonPath('data.version', 2);
        $this->getJson('/api/settings/public')->assertJsonCount(5, 'tipos')->assertJsonCount(10, 'ubicaciones');
        $this->assertSame($originalNames, $incident->refresh()->only(['type_name', 'location_name']));
    }

    public function test_stale_version_cannot_overwrite_a_saved_change(): void
    {
        $this->asAccount(User::factory()->administrator()->active()->create());
        $firstDraft = $this->settingsData();
        $secondDraft = $firstDraft;
        $firstDraft['siglas'] = 'PRIMERO';
        $this->putJson('/api/settings', $firstDraft)->assertOk()->assertJsonPath('data.version', 1);
        $secondDraft['siglas'] = 'SEGUNDO';
        $this->putJson('/api/settings', $secondDraft)->assertConflict();
        $this->assertSame('PRIMERO', InstitutionSetting::query()->sole()->abbreviation);
        $this->assertDatabaseCount('audit_logs', 1);
    }

    public function test_unchanged_save_does_not_create_a_version_or_audit_entry(): void
    {
        $this->asAccount(User::factory()->administrator()->active()->create());
        $this->putJson('/api/settings', $this->settingsData())->assertOk()->assertJsonPath('data.version', 0);
        $this->assertDatabaseCount('audit_logs', 0);
        $this->assertNull(InstitutionSetting::query()->sole()->updated_by);
    }

    /** @return array<string, array{string, mixed}> */
    public static function invalidSettings(): array
    {
        return [
            'institución vacía' => ['institucion', '   '],
            'institución larga' => ['institucion', str_repeat('a', 151)],
            'siglas largas' => ['siglas', str_repeat('a', 21)],
            'versión ausente' => ['version', null],
            'versión negativa' => ['version', -1],
            'preferencia ausente' => ['notificarNuevos', null],
            'preferencia inválida' => ['notificarAsignaciones', 'sí'],
            'tipo vacío' => ['tipos.0.nombre', ' '],
            'tipo largo' => ['tipos.0.nombre', str_repeat('a', 101)],
            'ubicación larga' => ['ubicaciones.0.nombre', str_repeat('a', 151)],
            'detalle inválido' => ['ubicaciones.0.requiereDetalle', 'sí'],
            'tipo inyectado' => ['tipos.0.updated_by', 999],
            'autor inyectado' => ['updated_by', 999],
            'versión interna inyectada' => ['lock_version', 12],
        ];
    }

    #[DataProvider('invalidSettings')]
    public function test_invalid_settings_are_rejected_without_partial_writes(string $field, mixed $value): void
    {
        $this->asAccount(User::factory()->administrator()->active()->create());
        $original = $this->settingsData();
        $data = $original;
        $data['siglas'] = 'CAMBIO';
        data_set($data, $field, $value);
        $this->putJson('/api/settings', $data)->assertUnprocessable();
        $this->assertSame($original, $this->settingsData());
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_catalogs_cannot_be_emptied_or_left_without_active_options(): void
    {
        $this->asAccount(User::factory()->administrator()->active()->create());
        $data = $this->settingsData();

        foreach (['tipos', 'ubicaciones'] as $field) {
            $empty = $data;
            $empty[$field] = [];
            $this->putJson('/api/settings', $empty)->assertUnprocessable()->assertJsonValidationErrors($field);
            $inactive = $data;
            $inactive[$field] = array_map(fn (array $item): array => array_replace($item, ['activo' => false]), $data[$field]);
            $this->putJson('/api/settings', $inactive)->assertUnprocessable()->assertJsonValidationErrors($field);
        }

        $this->assertSame($data, $this->settingsData());
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_catalog_ids_cannot_be_dropped_duplicated_or_replaced_with_unknown_ids(): void
    {
        $this->asAccount(User::factory()->administrator()->active()->create());
        $data = $this->settingsData();

        foreach (['tipos', 'ubicaciones'] as $field) {
            $missing = $data;
            array_pop($missing[$field]);
            $this->putJson('/api/settings', $missing)->assertUnprocessable();
            $unknown = $data;
            $unknown[$field][0]['id'] = 999999;
            $this->putJson('/api/settings', $unknown)->assertUnprocessable();
            $duplicate = $data;
            $duplicate[$field][1]['id'] = $duplicate[$field][0]['id'];
            $this->putJson('/api/settings', $duplicate)->assertUnprocessable();
        }

        $this->assertSame($data, $this->settingsData());
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_duplicate_names_include_inactive_entries_and_are_case_insensitive(): void
    {
        $this->asAccount(User::factory()->administrator()->active()->create());
        $data = $this->settingsData();
        $data['tipos'][0]['activo'] = false;
        $saved = $this->putJson('/api/settings', $data)->assertOk()->json('data');
        $saved['tipos'][] = ['nombre' => mb_strtoupper($saved['tipos'][0]['nombre']), 'activo' => true];
        $this->putJson('/api/settings', $saved)->assertUnprocessable()->assertJsonValidationErrors('tipos.0.nombre');
        $this->assertDatabaseCount('emergency_types', 5);
        $this->assertDatabaseCount('audit_logs', 1);
    }

    public function test_mysql_collation_collisions_roll_back_the_entire_save(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            $this->markTestSkipped('La comparación de acentos corresponde a la intercalación MySQL.');
        }

        $this->asAccount(User::factory()->administrator()->active()->create());
        $data = $this->settingsData();
        $data['siglas'] = 'CAMBIO';
        $data['tipos'][] = ['nombre' => 'Electrico', 'activo' => true];
        $data['tipos'][] = ['nombre' => 'Eléctrico', 'activo' => true];
        $this->putJson('/api/settings', $data)->assertUnprocessable();
        $this->assertSame('SIGEI', InstitutionSetting::query()->sole()->abbreviation);
        $this->assertDatabaseCount('emergency_types', 5);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_other_location_requirement_is_saved_independently_of_its_name(): void
    {
        $this->asAccount(User::factory()->administrator()->active()->create());
        $data = $this->settingsData();
        $otherIndex = array_search(true, array_column($data['ubicaciones'], 'requiereDetalle'), true);
        $data['ubicaciones'][$otherIndex]['nombre'] = 'Zona por especificar';
        $this->putJson('/api/settings', $data)->assertOk();
        $public = $this->getJson('/api/settings/public')->assertOk()->json();
        $location = collect($public['ubicaciones'])->firstWhere('id', $data['ubicaciones'][$otherIndex]['id']);
        $this->assertSame('Zona por especificar', $location['nombre']);
        $this->assertTrue($location['requiereDetalle']);
        $this->postJson('/api/incidents', [
            'tipo_id' => $data['tipos'][0]['id'], 'ubicacion_id' => $location['id'],
            'solicitud_id' => (string) Str::uuid(),
        ])->assertUnprocessable()->assertJsonValidationErrors('otro_lugar');
    }

    public function test_deactivated_catalog_options_cannot_be_used_by_an_old_public_form(): void
    {
        $this->asAccount(User::factory()->administrator()->active()->create());
        $data = $this->settingsData();
        $data['tipos'][0]['activo'] = false;
        $data['ubicaciones'][0]['activo'] = false;
        $this->putJson('/api/settings', $data)->assertOk();
        $this->postJson('/api/incidents', [
            'tipo_id' => $data['tipos'][0]['id'], 'ubicacion_id' => $data['ubicaciones'][1]['id'],
            'solicitud_id' => (string) Str::uuid(),
        ])->assertUnprocessable()->assertJsonValidationErrors('tipo_id');
        $this->postJson('/api/incidents', [
            'tipo_id' => $data['tipos'][1]['id'], 'ubicacion_id' => $data['ubicaciones'][0]['id'],
            'solicitud_id' => (string) Str::uuid(),
        ])->assertUnprocessable()->assertJsonValidationErrors('ubicacion_id');
        $this->assertDatabaseCount('incidents', 0);
    }

    public function test_notification_preferences_control_future_events_without_deleting_older_notifications(): void
    {
        $administrator = User::factory()->administrator()->active()->create();
        $responsible = User::factory()->responsible()->active()->create();
        $this->asAccount($administrator);
        $data = $this->settingsData();
        $report = [
            'tipo_id' => $data['tipos'][0]['id'], 'ubicacion_id' => $data['ubicaciones'][0]['id'],
            'solicitud_id' => (string) Str::uuid(),
        ];
        $this->postJson('/api/incidents', $report)->assertCreated();
        $this->assertDatabaseCount('internal_notifications', 1);
        $data['notificarNuevos'] = false;
        $data['notificarAsignaciones'] = false;
        $data['notificarSeguimiento'] = false;
        $saved = $this->putJson('/api/settings', $data)->assertOk()->json('data');
        $report['solicitud_id'] = (string) Str::uuid();
        $folio = $this->postJson('/api/incidents', $report)->assertCreated()->json('data.folio');
        $this->postJson('/api/incidents/'.$folio.'/management', [
            'version' => 0, 'responsable_id' => $responsible->id, 'prioridad' => 'Alta',
        ])->assertOk();
        $this->asAccount($responsible)->postJson('/api/incidents/'.$folio.'/receipt', ['version' => 1])->assertOk();
        $this->postJson('/api/incidents/'.$folio.'/updates', [
            'version' => 2, 'estado' => 'En atención', 'observacion' => 'Iniciando atención.',
        ])->assertOk();
        $this->assertDatabaseCount('internal_notifications', 1);
        $this->asAccount($administrator);
        $saved['notificarNuevos'] = true;
        $this->putJson('/api/settings', $saved)->assertOk();
        $report['solicitud_id'] = (string) Str::uuid();
        $this->postJson('/api/incidents', $report)->assertCreated();
        $this->assertDatabaseCount('internal_notifications', 2);
    }

    public function test_validation_failure_in_the_second_catalog_rolls_back_changes_to_the_first(): void
    {
        $this->asAccount(User::factory()->administrator()->active()->create());
        $original = $this->settingsData();
        $data = $original;
        $data['siglas'] = 'CAMBIO';
        $data['tipos'][0]['nombre'] = 'Cambio que debe revertirse';
        $data['ubicaciones'][0]['nombre'] = $data['ubicaciones'][1]['nombre'];
        $data['ubicaciones'][1]['nombre'] = 'Otra zona';
        $this->putJson('/api/settings', $data)->assertUnprocessable();
        $this->assertSame($original, $this->settingsData());
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_settings_write_requires_real_csrf_verification(): void
    {
        $this->asAccount(User::factory()->administrator()->active()->create());
        $data = $this->settingsData();
        $data['siglas'] = 'SEGURO';
        $this->app->bind(PreventRequestForgery::class, function (Application $app): PreventRequestForgery {
            return new class($app, $app['encrypter']) extends PreventRequestForgery
            {
                protected function runningUnitTests(): bool
                {
                    return false;
                }
            };
        });
        $this->putJson('/api/settings', $data)->assertStatus(419);
        $this->assertSame('SIGEI', InstitutionSetting::query()->sole()->abbreviation);
        $this->withSession(['_token' => 'known-test-csrf-token'])
            ->withHeader('X-CSRF-TOKEN', 'known-test-csrf-token')
            ->putJson('/api/settings', $data)->assertOk()->assertJsonPath('data.siglas', 'SEGURO');
    }
}
