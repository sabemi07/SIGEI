<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureAccountIsActive;
use App\Models\AuditLog;
use App\Models\Enums\AccountRole;
use App\Models\PushSubscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    /** @return array<string, mixed> */
    private function accountData(array $overrides = []): array
    {
        return array_replace([
            'nombre' => 'Responsable de pruebas',
            'correo' => 'responsable@example.test',
            'area' => 'Seguridad',
            'rol' => 'Responsable',
            'activo' => true,
            'password' => 'clave-de-pruebas-segura',
            'password_confirmation' => 'clave-de-pruebas-segura',
        ], $overrides);
    }

    private function authenticateAccount(User $user): static
    {
        $user->refresh();
        $this->actingAs($user)->withSession(['account_auth_version' => $user->auth_version]);

        return $this;
    }

    public function test_guests_cannot_list_create_or_update_accounts(): void
    {
        $target = User::factory()->create();
        $this->getJson('/api/users')->assertUnauthorized();
        $this->postJson('/api/users', $this->accountData())->assertUnauthorized();
        $this->patchJson('/api/users/'.$target->id, $this->accountData())->assertUnauthorized();
    }

    public function test_account_writes_recheck_the_session_version_inside_the_transaction(): void
    {
        $administrator = User::factory()->administrator()->active()->create();
        $this->authenticateAccount($administrator);
        DB::table('users')->where('id', $administrator->id)->increment('auth_version');

        $this->withoutMiddleware(EnsureAccountIsActive::class)
            ->postJson('/api/users', $this->accountData())->assertForbidden();

        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_responsibles_cannot_list_create_or_update_accounts(): void
    {
        $responsible = User::factory()->responsible()->active()->create();
        $this->authenticateAccount($responsible);
        $this->getJson('/api/users')->assertForbidden();
        $this->postJson('/api/users', $this->accountData())->assertForbidden();
        $this->patchJson('/api/users/'.$responsible->id, $this->accountData())->assertForbidden();
    }

    public function test_administrator_can_create_list_and_edit_real_accounts(): void
    {
        $administrator = User::factory()->administrator()->active()->create();
        $this->authenticateAccount($administrator);
        $response = $this->postJson('/api/users', $this->accountData(['correo' => '  RESPONSABLE@example.test  ']))
            ->assertCreated()->assertJsonPath('data.correo', 'responsable@example.test')
            ->assertJsonPath('data.rol', 'Responsable')->assertJsonPath('session_ended', false)
            ->assertJsonMissingPath('data.password')->assertJsonMissingPath('data.remember_token');
        $target = User::query()->findOrFail($response->json('data.id'));
        $originalHash = $target->password;
        $this->assertTrue(Hash::check('clave-de-pruebas-segura', $target->password));
        $this->getJson('/api/users')->assertOk()->assertJsonCount(2, 'data');

        $data = $this->accountData(['correo' => $target->email, 'nombre' => 'Nombre actualizado', 'area' => 'Atención médica']);
        unset($data['password'], $data['password_confirmation']);
        $this->patchJson('/api/users/'.$target->id, $data)->assertOk()->assertJsonPath('data.nombre', 'Nombre actualizado');

        $this->assertSame($originalHash, $target->refresh()->password);
        $this->assertSame('Atención médica', $target->area);
        $this->assertSame(2, AuditLog::query()->where('actor_id', $administrator->id)->count());
        $auditText = AuditLog::query()->get()->pluck('metadata')->toJson();
        $this->assertStringNotContainsString('password', $auditText);
        $this->assertStringNotContainsString('clave-de-pruebas-segura', $auditText);
        $this->assertStringNotContainsString($originalHash, $auditText);
    }

    /** @return array<string, array{array<string, mixed>, string}> */
    public static function invalidAccountData(): array
    {
        return [
            'rol desconocido' => [['rol' => 'Reportante'], 'rol'],
            'correo inválido' => [['correo' => 'correo inválido'], 'correo'],
            'nombre excesivo' => [['nombre' => str_repeat('n', 151)], 'nombre'],
            'área excesiva' => [['area' => str_repeat('a', 151)], 'area'],
            'sin contraseña' => [['password' => null], 'password'],
            'contraseña corta' => [['password' => 'corta', 'password_confirmation' => 'corta'], 'password'],
            'contraseña sin confirmar' => [['password_confirmation' => 'no-coincide'], 'password'],
            'contraseña larga' => [['password' => str_repeat('a', 73), 'password_confirmation' => str_repeat('a', 73)], 'password'],
            'contraseña larga multibyte' => [['password' => str_repeat('á', 37), 'password_confirmation' => str_repeat('á', 37)], 'password'],
            'id impuesto' => [['id' => 1], 'id'],
            'rol inglés impuesto' => [['role' => 'Administrador'], 'role'],
            'activación inglesa impuesta' => [['is_active' => true], 'is_active'],
            'token impuesto' => [['remember_token' => 'token-no-permitido'], 'remember_token'],
            'verificación impuesta' => [['email_verified_at' => '2026-10-29'], 'email_verified_at'],
            'versión de autenticación impuesta' => [['auth_version' => 42], 'auth_version'],
        ];
    }

    #[DataProvider('invalidAccountData')]
    public function test_invalid_account_data_is_rejected(array $overrides, string $field): void
    {
        $this->authenticateAccount(User::factory()->administrator()->active()->create());
        $this->postJson('/api/users', $this->accountData($overrides))->assertUnprocessable()->assertJsonValidationErrors($field);
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_duplicate_email_is_rejected_after_normalization(): void
    {
        $administrator = User::factory()->administrator()->active()->create(['email' => 'admin@example.test']);
        $this->authenticateAccount($administrator)->postJson('/api/users', $this->accountData(['correo' => 'ADMIN@example.test']))
            ->assertUnprocessable()->assertJsonValidationErrors('correo');
    }

    public function test_last_active_administrator_cannot_be_deactivated_or_demoted(): void
    {
        $administrator = User::factory()->administrator()->active()->create();
        User::factory()->administrator()->inactive()->create();
        $this->authenticateAccount($administrator);

        $this->patchJson('/api/users/'.$administrator->id, $this->accountData(['correo' => $administrator->email, 'rol' => 'Administrador', 'activo' => false]))
            ->assertUnprocessable();
        $this->patchJson('/api/users/'.$administrator->id, $this->accountData(['correo' => $administrator->email, 'rol' => 'Responsable']))
            ->assertUnprocessable();

        $this->assertTrue($administrator->refresh()->is_active);
        $this->assertSame(AccountRole::Administrator, $administrator->role);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_administrator_can_demote_another_when_one_active_administrator_remains(): void
    {
        $administrator = User::factory()->administrator()->active()->create();
        $other = User::factory()->administrator()->active()->create();
        $this->authenticateAccount($administrator)->patchJson('/api/users/'.$other->id, $this->accountData(['correo' => $other->email, 'rol' => 'Responsable']))
            ->assertOk()->assertJsonPath('session_ended', false);

        $this->assertSame(AccountRole::Responsible, $other->refresh()->role);
        $this->assertAuthenticatedAs($administrator);
    }

    public function test_deactivation_revokes_target_sessions_and_push_subscriptions_only(): void
    {
        $administrator = User::factory()->administrator()->active()->create();
        $target = User::factory()->responsible()->active()->create();

        foreach ([$administrator, $target] as $user) {
            DB::table('sessions')->insert(['id' => 'session-'.$user->id, 'user_id' => $user->id, 'payload' => '', 'last_activity' => time()]);
            PushSubscription::query()->create([
                'user_id' => $user->id,
                'device_id' => Str::uuid()->toString(),
                'endpoint' => 'https://push.example.test/'.$user->id,
                'public_key' => 'test-key',
                'auth_token' => 'test-token',
            ]);
        }

        $this->authenticateAccount($administrator)->patchJson('/api/users/'.$target->id, $this->accountData(['correo' => $target->email, 'activo' => false]))
            ->assertOk();

        $this->assertDatabaseMissing('sessions', ['user_id' => $target->id]);
        $this->assertDatabaseHas('sessions', ['user_id' => $administrator->id]);
        $this->assertNotNull($target->pushSubscriptions()->sole()->revoked_at);
        $this->assertNull($administrator->pushSubscriptions()->sole()->revoked_at);
    }

    public function test_password_change_invalidates_sessions_and_never_returns_the_hash(): void
    {
        $administrator = User::factory()->administrator()->active()->create();
        $target = User::factory()->responsible()->active()->create();
        DB::table('sessions')->insert(['id' => 'target-session', 'user_id' => $target->id, 'payload' => '', 'last_activity' => time()]);

        $this->authenticateAccount($administrator)->patchJson('/api/users/'.$target->id, $this->accountData(['correo' => $target->email]))
            ->assertOk()->assertJsonMissingPath('data.password');

        $this->assertTrue(Hash::check('clave-de-pruebas-segura', $target->refresh()->password));
        $this->assertDatabaseMissing('sessions', ['user_id' => $target->id]);
    }

    public function test_self_demotion_ends_the_current_session_when_another_administrator_exists(): void
    {
        $administrator = User::factory()->administrator()->active()->create();
        User::factory()->administrator()->active()->create();

        $this->authenticateAccount($administrator)->patchJson('/api/users/'.$administrator->id, $this->accountData(['correo' => $administrator->email]))
            ->assertOk()->assertJsonPath('session_ended', true)->assertJsonStructure(['csrf_token']);

        $this->assertGuest();
        $this->getJson('/api/users')->assertUnauthorized();
    }

    public function test_accounts_cannot_be_deleted_through_the_api(): void
    {
        $administrator = User::factory()->administrator()->active()->create();
        $this->authenticateAccount($administrator)->deleteJson('/api/users/'.$administrator->id)->assertMethodNotAllowed();
        $this->assertDatabaseHas('users', ['id' => $administrator->id]);
    }

    public function test_only_security_changes_advance_the_authentication_version(): void
    {
        $administrator = User::factory()->administrator()->active()->create();
        $target = User::factory()->responsible()->active()->create()->refresh();
        $originalVersion = $target->auth_version;
        $data = $this->accountData(['correo' => $target->email, 'nombre' => 'Nombre nuevo', 'area' => 'Área nueva']);
        unset($data['password'], $data['password_confirmation']);
        $this->authenticateAccount($administrator);

        $this->patchJson('/api/users/'.$target->id, $data)->assertOk()->assertJsonMissingPath('data.auth_version');
        $this->assertSame($originalVersion, $target->refresh()->auth_version);

        $data['rol'] = 'Administrador';
        $this->patchJson('/api/users/'.$target->id, $data)->assertOk();
        $this->assertSame($originalVersion + 1, $target->refresh()->auth_version);

        $data['password'] = $data['password_confirmation'] = 'otra-clave-segura-de-prueba';
        $this->patchJson('/api/users/'.$target->id, $data)->assertOk();
        $this->assertSame($originalVersion + 2, $target->refresh()->auth_version);

        unset($data['password'], $data['password_confirmation']);
        $data['activo'] = false;
        $this->patchJson('/api/users/'.$target->id, $data)->assertOk();
        $this->assertSame($originalVersion + 3, $target->refresh()->auth_version);
    }
}
