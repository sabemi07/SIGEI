<?php

namespace Tests\Feature;

use App\Models\PushSubscription;
use App\Models\User;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_obtain_session_csrf_token_without_account_information(): void
    {
        User::factory()->active()->create();

        $response = $this->getJson('/api/session')
            ->assertOk()
            ->assertJsonPath('user', null)
            ->assertJsonStructure(['user', 'csrf_token']);

        $this->assertNotEmpty($response->json('csrf_token'));
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
    }

    public function test_active_account_can_log_in_using_password_and_rotates_session_and_csrf_token(): void
    {
        $user = User::factory()->administrator()->active()->create();
        $token = $this->getJson('/api/session')->json('csrf_token');
        $sessionId = session()->getId();

        $response = $this->postJson('/api/login', ['email' => $user->email, 'password' => 'password'])
            ->assertOk()
            ->assertJsonPath('user.id', $user->id)
            ->assertJsonPath('user.rol', 'Administrador')
            ->assertJsonPath('user.activo', true);

        $this->assertAuthenticatedAs($user);
        $this->assertSame($user->auth_version, session('account_auth_version'));
        $this->assertNotSame($sessionId, session()->getId());
        $this->assertNotSame($token, $response->json('csrf_token'));
        $this->assertEqualsCanonicalizing(['id', 'nombre', 'correo', 'rol', 'area', 'activo'], array_keys($response->json('user')));
    }

    public function test_email_is_trimmed_and_normalized_without_accepting_client_role(): void
    {
        $user = User::factory()->responsible()->active()->create(['email' => 'responsable@example.test']);

        $this->postJson('/api/login', [
            'email' => '  RESPONSABLE@EXAMPLE.TEST  ',
            'password' => 'password',
            'role' => 'Administrador',
            'is_active' => true,
        ])->assertOk()->assertJsonPath('user.rol', 'Responsable');

        $this->assertAuthenticatedAs($user);
    }

    public function test_wrong_password_unknown_account_and_inactive_account_return_the_same_error(): void
    {
        $active = User::factory()->active()->create();
        $inactive = User::factory()->inactive()->create();
        $errors = [];

        foreach ([[$active->email, 'incorrecta'], ['missing@example.test', 'password'], [$inactive->email, 'password']] as [$email, $password]) {
            $response = $this->postJson('/api/login', compact('email', 'password'))
                ->assertUnprocessable()
                ->assertJsonValidationErrors('email');
            $errors[] = $response->json('errors.email');
            $this->assertGuest();
        }

        $this->assertSame($errors[0], $errors[1]);
        $this->assertSame($errors[0], $errors[2]);
    }

    public function test_login_validates_input_and_does_not_create_accounts(): void
    {
        $this->postJson('/api/login', ['email' => ['wrong'], 'password' => ['wrong']])
            ->assertUnprocessable()->assertJsonValidationErrors(['email', 'password']);

        $this->assertDatabaseCount('users', 0);
    }

    public function test_login_rejects_passwords_that_exceed_bcrypt_byte_limit(): void
    {
        $this->postJson('/api/login', ['email' => 'valid@example.test', 'password' => str_repeat('é', 37)])
            ->assertUnprocessable()->assertJsonValidationErrors('password');

        $this->assertGuest();
    }

    public function test_password_whitespace_is_not_trimmed(): void
    {
        $password = '  password-segura  ';
        $user = User::factory()->active()->create(['password' => $password]);

        $this->postJson('/api/login', ['email' => $user->email, 'password' => trim($password)])
            ->assertUnprocessable();
        $this->assertGuest();

        $this->postJson('/api/login', ['email' => $user->email, 'password' => $password])->assertOk();
        $this->assertAuthenticatedAs($user);
    }

    public function test_protected_routes_return_json_unauthorized_without_accept_header(): void
    {
        $this->get('/api/users')->assertUnauthorized()->assertJsonStructure(['message']);
    }

    public function test_repeated_failures_are_limited_by_normalized_email_and_ip(): void
    {
        $user = User::factory()->active()->create(['email' => 'limite@example.test']);

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->postJson('/api/login', ['email' => ' LIMITE@EXAMPLE.TEST ', 'password' => 'incorrecta'])
                ->assertUnprocessable();
        }

        $this->postJson('/api/login', ['email' => $user->email, 'password' => 'password'])
            ->assertStatus(429)->assertHeader('Retry-After');
        $this->assertGuest();
    }

    public function test_successful_login_clears_the_failed_attempt_counter(): void
    {
        $user = User::factory()->active()->create();
        $key = 'login:'.hash('sha256', $user->email.'|127.0.0.1');
        RateLimiter::hit($key, 60);

        $this->postJson('/api/login', ['email' => $user->email, 'password' => 'password'])->assertOk();

        $this->assertSame(0, RateLimiter::attempts($key));
    }

    public function test_failed_attempt_limits_are_kept_when_cache_uses_the_database(): void
    {
        config(['cache.default' => 'database']);
        $this->app['cache']->forgetDriver('array');
        $this->app->forgetInstance(\Illuminate\Cache\RateLimiter::class);
        RateLimiter::clearResolvedInstance(\Illuminate\Cache\RateLimiter::class);
        $user = User::factory()->active()->create();

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->postJson('/api/login', ['email' => $user->email, 'password' => 'incorrecta'])->assertUnprocessable();
        }

        $this->postJson('/api/login', ['email' => $user->email, 'password' => 'password'])->assertStatus(429);
        $this->assertGuest();
    }

    public function test_logout_invalidates_the_session_and_rotates_csrf_without_revoking_other_devices(): void
    {
        $user = User::factory()->active()->create();
        $otherDevice = PushSubscription::query()->create([
            'user_id' => $user->id,
            'device_id' => Str::uuid()->toString(),
            'endpoint' => 'https://push.example.test/other-device',
            'public_key' => 'test-key',
            'auth_token' => 'test-token',
        ]);
        $this->postJson('/api/login', ['email' => $user->email, 'password' => 'password'])->assertOk();
        $oldToken = session()->token();
        $oldSessionId = session()->getId();
        session()->put('private_marker', 'sensitive-session-data');

        $response = $this->postJson('/api/logout')->assertOk()->assertJsonPath('user', null);

        $this->assertGuest();
        $this->assertNotSame($oldToken, $response->json('csrf_token'));
        $this->assertNotSame($oldSessionId, session()->getId());
        $this->assertNull(session('private_marker'));
        $this->assertNull($otherDevice->refresh()->revoked_at);
        $this->getJson('/api/session')->assertJsonPath('user', null);
    }

    public function test_changing_accounts_requires_logging_out_first(): void
    {
        $first = User::factory()->active()->create();
        $second = User::factory()->active()->create();
        $this->actingAs($first)->withSession(['account_auth_version' => $first->auth_version]);

        $this->postJson('/api/login', ['email' => $second->email, 'password' => 'password'])->assertStatus(409);
        $this->assertAuthenticatedAs($first);
    }

    public function test_session_refresh_removes_an_account_that_was_deactivated_in_database(): void
    {
        $user = User::factory()->active()->create();
        $this->actingAs($user)->withSession(['account_auth_version' => $user->auth_version]);
        DB::table('users')->where('id', $user->id)->update(['is_active' => false]);

        $this->getJson('/api/session')->assertOk()->assertJsonPath('user', null);

        $this->assertGuest();
    }

    public function test_protected_routes_reject_deactivated_accounts_even_when_guard_had_cached_user(): void
    {
        $user = User::factory()->administrator()->active()->create();
        $this->actingAs($user)->withSession(['account_auth_version' => $user->auth_version]);
        DB::table('users')->where('id', $user->id)->update(['is_active' => false]);

        $this->getJson('/api/users')->assertUnauthorized();

        $this->assertGuest();
    }

    public function test_session_refresh_uses_current_server_role(): void
    {
        $user = User::factory()->administrator()->active()->create();
        $this->actingAs($user)->withSession(['account_auth_version' => $user->auth_version]);
        DB::table('users')->where('id', $user->id)->update(['role' => 'Responsable']);

        $this->getJson('/api/session')->assertOk()->assertJsonPath('user.rol', 'Responsable');
    }

    public function test_incremented_auth_version_invalidates_an_authenticated_session_even_with_cached_guard(): void
    {
        $user = User::factory()->active()->create();
        $this->postJson('/api/login', ['email' => $user->email, 'password' => 'password'])->assertOk();
        DB::table('users')->where('id', $user->id)->increment('auth_version');

        $this->getJson('/api/session')->assertOk()->assertJsonPath('user', null);

        $this->assertGuest();
        $this->assertNull(session('account_auth_version'));
    }

    public function test_stale_session_cannot_access_reactivated_account_and_does_not_affect_other_users(): void
    {
        $user = User::factory()->administrator()->active()->create(['auth_version' => 2]);
        $other = User::factory()->active()->create();
        $this->actingAs($user)->withSession(['account_auth_version' => 0]);

        $this->getJson('/api/users')->assertUnauthorized();
        $this->assertGuest();
        $this->assertSame(0, $other->refresh()->auth_version);

        $this->postJson('/api/login', ['email' => $other->email, 'password' => 'password'])
            ->assertOk()->assertJsonPath('user.id', $other->id);
    }

    public function test_authenticated_session_without_auth_version_is_rejected(): void
    {
        $user = User::factory()->active()->create();
        $this->actingAs($user);

        $this->getJson('/api/session')->assertOk()->assertJsonPath('user', null);
        $this->assertGuest();
    }

    public function test_login_requires_csrf_verification_when_test_bypass_is_disabled(): void
    {
        $this->enableRealCsrfVerification();
        $user = User::factory()->active()->create();

        $this->postJson('/api/login', ['email' => $user->email, 'password' => 'password'])->assertStatus(419);
        $this->assertGuest();

        $this->withSession(['_token' => 'known-test-csrf-token'])
            ->withHeader('X-CSRF-TOKEN', 'known-test-csrf-token')
            ->postJson('/api/login', ['email' => $user->email, 'password' => 'password'])
            ->assertOk();
    }

    public function test_logout_requires_csrf_verification_when_test_bypass_is_disabled(): void
    {
        $this->enableRealCsrfVerification();
        $user = User::factory()->active()->create();
        $this->actingAs($user)->withSession(['account_auth_version' => $user->auth_version]);

        $this->postJson('/api/logout')->assertStatus(419);
        $this->assertAuthenticatedAs($user);

        $this->withSession(['_token' => 'known-test-csrf-token'])
            ->withHeader('X-CSRF-TOKEN', 'known-test-csrf-token')
            ->postJson('/api/logout')->assertOk();
        $this->assertGuest();
    }

    private function enableRealCsrfVerification(): void
    {
        $this->app->bind(PreventRequestForgery::class, fn (Application $app): PreventRequestForgery => new class($app, $app['encrypter']) extends PreventRequestForgery
        {
            protected function runningUnitTests(): bool
            {
                return false;
            }
        });
    }
}
