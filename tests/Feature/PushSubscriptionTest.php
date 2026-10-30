<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureAccountIsActive;
use App\Http\Middleware\IdentifyPushDevice;
use App\Models\AuditLog;
use App\Models\Incident;
use App\Models\InternalNotification;
use App\Models\PushSubscription;
use App\Models\User;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PushSubscriptionTest extends TestCase
{
    use RefreshDatabase;

    private string $keyFile;

    private string $device;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->keyFile = storage_path('framework/testing/sigei-test-push-'.Str::uuid().'.json');
        config(['sigei.push.key_file' => $this->keyFile]);
        $this->device = (string) Str::uuid();
        $this->artisan('sigei:push-keys')->assertSuccessful();
    }

    protected function tearDown(): void
    {
        if (isset($this->keyFile) && is_file($this->keyFile)) {
            unlink($this->keyFile);
        }

        parent::tearDown();
    }

    private function asAccount(User $user, ?string $device = null): static
    {
        $user->refresh();
        $this->actingAs($user)->withSession(['account_auth_version' => $user->auth_version]);

        return $this->withCookie(IdentifyPushDevice::COOKIE, $device ?? $this->device)
            ->withCookie(config('session.cookie'), session()->getId())->withCredentials();
    }

    /** @return array<string, mixed> */
    private function subscriptionData(User $user): array
    {
        $keys = json_decode(file_get_contents($this->keyFile), true);

        return [
            'cuenta_esperada' => $user->id,
            'endpoint' => 'https://fcm.googleapis.com/fcm/send/'.Str::uuid(),
            'keys' => ['p256dh' => $keys['public_key'], 'auth' => rtrim(strtr(base64_encode(random_bytes(16)), '+/', '-_'), '=')],
            'expirationTime' => null,
            'contentEncoding' => 'aes128gcm',
        ];
    }

    public function test_device_cookie_is_issued_encrypted_and_http_only_without_authenticating_the_visitor(): void
    {
        $response = $this->getJson('/api/session')->assertOk()->assertJsonPath('user', null);
        $response->assertCookie(IdentifyPushDevice::COOKIE);
        $cookie = collect($response->headers->getCookies())->first(fn ($cookie): bool => $cookie->getName() === IdentifyPushDevice::COOKIE);
        $this->assertTrue($cookie->isHttpOnly());
        $this->assertSame('strict', $cookie->getSameSite());
        $this->assertFalse(Str::isUuid($cookie->getValue()));
    }

    public function test_guests_cannot_inspect_register_or_revoke_push_subscriptions(): void
    {
        $this->getJson('/api/push')->assertUnauthorized();
        $this->postJson('/api/push', [])->assertUnauthorized();
        $this->deleteJson('/api/push')->assertUnauthorized();
        $this->assertDatabaseCount('push_subscriptions', 0);
    }

    public function test_active_accounts_can_register_only_the_current_device_and_keys_are_encrypted_at_rest(): void
    {
        $user = User::factory()->responsible()->active()->create();
        $data = $this->subscriptionData($user);
        $this->asAccount($user)->postJson('/api/push', $data)->assertOk()->assertJsonPath('delivery_available', false)
            ->assertJsonMissingPath('subscription.endpoint')->assertJsonMissingPath('subscription.keys');
        $subscription = PushSubscription::query()->sole();
        $this->assertSame($user->id, $subscription->user_id);
        $this->assertSame($this->device, $subscription->device_id);
        $this->assertSame(hash('sha256', session()->getId()), $subscription->session_hash);
        $this->assertSame($data['endpoint'], $subscription->endpoint);
        $raw = DB::table('push_subscriptions')->first();
        $this->assertNotSame($data['endpoint'], $raw->endpoint);
        $this->assertNotSame($data['keys']['p256dh'], $raw->public_key);
        $this->assertNotSame($data['keys']['auth'], $raw->auth_token);
        $this->getJson('/api/push')->assertOk()->assertJsonPath('subscription.id', $subscription->id)
            ->assertJsonPath('subscription.endpoint_hash', hash('sha256', $data['endpoint']));
        $this->assertSame($user->id, AuditLog::query()->where('action', 'push.subscribed')->sole()->actor_id);
        $this->assertNull(AuditLog::query()->where('action', 'push.subscribed')->sole()->metadata);
    }

    public function test_repeated_registration_keeps_one_record_and_rotates_the_binding_for_old_messages(): void
    {
        $user = User::factory()->administrator()->active()->create();
        $data = $this->subscriptionData($user);
        $first = $this->asAccount($user)->postJson('/api/push', $data)->assertOk()->json('subscription');
        $second = $this->postJson('/api/push', $data)->assertOk()->json('subscription');
        $this->assertSame($first['id'], $second['id']);
        $this->assertNotSame($first['binding_id'], $second['binding_id']);
        $this->assertDatabaseCount('push_subscriptions', 1);
    }

    public function test_logout_revokes_only_the_current_device_and_a_new_login_requires_relinking(): void
    {
        $user = User::factory()->responsible()->active()->create();
        $this->asAccount($user)->postJson('/api/push', $this->subscriptionData($user))->assertOk();
        $current = PushSubscription::query()->sole();
        $otherDevice = (string) Str::uuid();
        $this->asAccount($user, $otherDevice)->postJson('/api/push', $this->subscriptionData($user))->assertOk();
        $this->asAccount($user)->postJson('/api/logout')->assertOk();
        $this->assertNotNull($current->refresh()->revoked_at);
        $this->assertNull(PushSubscription::query()->where('device_id', $otherDevice)->sole()->revoked_at);
        $this->withCookie(config('session.cookie'), session()->getId())
            ->postJson('/api/login', ['email' => $user->email, 'password' => 'password'])->assertOk();
        $this->asAccount($user)->getJson('/api/push')->assertOk()->assertJsonPath('subscription', null);
    }

    public function test_account_switch_revokes_the_old_binding_without_transferring_its_endpoint(): void
    {
        $first = User::factory()->responsible()->active()->create();
        $second = User::factory()->administrator()->active()->create();
        $data = $this->subscriptionData($first);
        $this->asAccount($first)->postJson('/api/push', $data)->assertOk();
        $this->postJson('/api/logout')->assertOk();
        $this->withCookie(config('session.cookie'), session()->getId())
            ->postJson('/api/login', ['email' => $second->email, 'password' => 'password'])->assertOk();
        $this->asAccount($second)->getJson('/api/push')->assertJsonPath('subscription', null);
        $data['cuenta_esperada'] = $second->id;
        $this->postJson('/api/push', $data)->assertConflict();
        $this->postJson('/api/push', $this->subscriptionData($second))->assertOk();
        $this->assertNotNull(PushSubscription::query()->where('user_id', $first->id)->sole()->revoked_at);
        $this->assertSame($second->id, PushSubscription::query()->whereNull('revoked_at')->sole()->user_id);
    }

    public function test_an_endpoint_cannot_be_stolen_by_another_device_even_for_the_same_user(): void
    {
        $user = User::factory()->responsible()->active()->create();
        $data = $this->subscriptionData($user);
        $this->asAccount($user)->postJson('/api/push', $data)->assertOk();
        $this->asAccount($user, (string) Str::uuid())->postJson('/api/push', $data)->assertConflict();
        $this->assertSame($this->device, PushSubscription::query()->sole()->device_id);
    }

    public function test_stale_account_intent_and_stale_session_version_cannot_register_a_device(): void
    {
        $user = User::factory()->active()->create();
        $data = $this->subscriptionData($user);
        $data['cuenta_esperada'] = $user->id + 1;
        $this->asAccount($user)->postJson('/api/push', $data)->assertConflict();
        $data['cuenta_esperada'] = $user->id;
        DB::table('users')->where('id', $user->id)->increment('auth_version');
        $this->withoutMiddleware(EnsureAccountIsActive::class)->postJson('/api/push', $data)->assertForbidden();
        $this->assertDatabaseCount('push_subscriptions', 0);
    }

    public function test_revocation_is_idempotent_and_does_not_touch_other_devices(): void
    {
        $user = User::factory()->active()->create();
        $this->asAccount($user)->postJson('/api/push', $this->subscriptionData($user))->assertOk();
        $this->deleteJson('/api/push')->assertOk()->assertJsonPath('subscription', null);
        $this->deleteJson('/api/push')->assertOk();
        $this->getJson('/api/push')->assertJsonPath('subscription', null);
        $this->assertNotNull(PushSubscription::query()->sole()->revoked_at);
        $this->assertSame(1, AuditLog::query()->where('action', 'push.unsubscribed')->count());
    }

    public function test_expired_subscriptions_are_not_presented_as_linked(): void
    {
        $user = User::factory()->active()->create();
        $this->asAccount($user)->postJson('/api/push', $this->subscriptionData($user))->assertOk();
        PushSubscription::query()->sole()->update(['expires_at' => now('UTC')->subSecond()]);
        $this->getJson('/api/push')->assertJsonPath('subscription', null);
    }

    public function test_missing_keys_disable_registration_without_exposing_a_private_key(): void
    {
        $user = User::factory()->active()->create();
        $this->asAccount($user);
        $private = json_decode(file_get_contents($this->keyFile), true)['private_key'];
        $response = $this->getJson('/api/push')->assertOk()->assertJsonMissingPath('private_key');
        $this->assertStringNotContainsString($private, $response->getContent());
        unlink($this->keyFile);
        $this->getJson('/api/push')->assertOk()->assertJsonPath('public_key', null);
        $this->postJson('/api/push', $this->subscriptionDataWithoutFile($user))->assertStatus(503);
    }

    /** @return array<string, mixed> */
    private function subscriptionDataWithoutFile(User $user): array
    {
        return [
            'cuenta_esperada' => $user->id, 'endpoint' => 'https://fcm.googleapis.com/test',
            'keys' => ['p256dh' => rtrim(strtr(base64_encode("\x04".str_repeat('p', 64)), '+/', '-_'), '='), 'auth' => rtrim(strtr(base64_encode(str_repeat('a', 16)), '+/', '-_'), '=')],
            'contentEncoding' => 'aes128gcm',
        ];
    }

    /** @return array<string, array{string, mixed}> */
    public static function invalidSubscriptions(): array
    {
        return [
            'dirección HTTP' => ['endpoint', 'http://fcm.googleapis.com/test'],
            'dirección privada' => ['endpoint', 'https://127.0.0.1/test'],
            'dominio impostor' => ['endpoint', 'https://fcm.googleapis.com.evil.test/test'],
            'Apple impostor' => ['endpoint', 'https://fakepush.apple.com/test'],
            'credenciales en URL' => ['endpoint', 'https://user@fcm.googleapis.com/test'],
            'puerto alterno' => ['endpoint', 'https://fcm.googleapis.com:444/test'],
            'fragmento' => ['endpoint', 'https://fcm.googleapis.com/test#token'],
            'clave pública inválida' => ['keys.p256dh', 'invalid'],
            'clave auth inválida' => ['keys.auth', 'invalid'],
            'codificación inválida' => ['contentEncoding', 'aesgcm'],
            'caducidad anterior' => ['expirationTime', 1],
            'cuenta manipulada' => ['user_id', 123],
            'dispositivo manipulado' => ['device_id', '123'],
            'asociación manipulada' => ['binding_id', '123'],
            'clave adicional' => ['keys.secret', 'not-allowed'],
        ];
    }

    #[DataProvider('invalidSubscriptions')]
    public function test_invalid_subscriptions_are_rejected(string $field, mixed $value): void
    {
        $user = User::factory()->active()->create();
        $data = $this->subscriptionData($user);
        data_set($data, $field, $value);
        $this->asAccount($user)->postJson('/api/push', $data)->assertUnprocessable();
        $this->assertDatabaseCount('push_subscriptions', 0);
    }

    public function test_push_messages_require_a_current_binding_and_case_permission_without_confirming_receipt(): void
    {
        $user = User::factory()->responsible()->active()->create();
        $incident = Incident::factory()->create(['reporter_user_id' => $user->id]);
        $notice = InternalNotification::query()->create([
            'user_id' => $user->id, 'incident_id' => $incident->id, 'kind' => 'incident.updated',
            'title' => 'Private title', 'body' => 'Private body',
        ]);
        $binding = $this->asAccount($user)->postJson('/api/push', $this->subscriptionData($user))->assertOk()->json('subscription.binding_id');
        $path = '/api/push/notifications/'.$notice->id;
        $this->getJson($path)->assertNotFound();
        $response = $this->withHeader('X-SIGEI-Push-Binding', $binding)->getJson($path)->assertOk();
        $this->assertStringNotContainsString('Private', $response->getContent());
        $this->assertStringNotContainsString($incident->folio, $response->getContent());
        $this->assertNull($notice->refresh()->read_at);
        $this->assertDatabaseCount('incident_assignments', 0);
        $this->deleteJson('/api/push')->assertOk();
        $this->getJson($path)->assertNotFound();
    }

    public function test_notification_owned_by_another_account_or_case_without_permission_is_not_disclosed(): void
    {
        $user = User::factory()->responsible()->active()->create();
        $other = User::factory()->active()->create();
        $case = Incident::factory()->create();
        $notice = InternalNotification::query()->create([
            'user_id' => $other->id, 'incident_id' => $case->id, 'kind' => 'incident.updated', 'title' => 'Notice', 'body' => 'Body',
        ]);
        $binding = $this->asAccount($user)->postJson('/api/push', $this->subscriptionData($user))->assertOk()->json('subscription.binding_id');
        $this->withHeader('X-SIGEI-Push-Binding', $binding)->getJson('/api/push/notifications/'.$notice->id)->assertNotFound();
        $notice->user_id = $user->id;
        $notice->save();
        $this->getJson('/api/push/notifications/'.$notice->id)->assertNotFound();
    }

    public function test_csrf_is_required_for_linking_and_unlinking(): void
    {
        $user = User::factory()->active()->create();
        $this->asAccount($user);
        $this->app->bind(PreventRequestForgery::class, fn (Application $app): PreventRequestForgery => new class($app, $app['encrypter']) extends PreventRequestForgery
        {
            protected function runningUnitTests(): bool
            {
                return false;
            }
        });
        $this->postJson('/api/push', $this->subscriptionData($user))->assertStatus(419);
        $this->withSession(['_token' => 'push-test-csrf']);
        session()->save();
        $this->withHeader('X-CSRF-TOKEN', 'push-test-csrf')
            ->postJson('/api/push', $this->subscriptionData($user))->assertOk();
        $this->withHeader('X-CSRF-TOKEN', 'wrong-token')->deleteJson('/api/push')->assertStatus(419);
        $this->assertNull(PushSubscription::query()->sole()->revoked_at);
    }

    public function test_login_after_a_lost_session_revokes_the_previous_account_binding_on_this_device(): void
    {
        $first = User::factory()->active()->create();
        $second = User::factory()->active()->create();
        $this->asAccount($first)->postJson('/api/push', $this->subscriptionData($first))->assertOk();
        $old = PushSubscription::query()->sole();
        Auth::logout();
        $this->postJson('/api/login', ['email' => $second->email, 'password' => 'password'])->assertOk();
        $this->assertNotNull($old->refresh()->revoked_at);
    }

    public function test_password_change_revokes_all_devices_of_the_changed_account(): void
    {
        $target = User::factory()->responsible()->active()->create();
        $administrator = User::factory()->administrator()->active()->create();
        $this->asAccount($target)->postJson('/api/push', $this->subscriptionData($target))->assertOk();
        $this->asAccount($target, (string) Str::uuid())->postJson('/api/push', $this->subscriptionData($target))->assertOk();
        $this->asAccount($administrator)->patchJson('/api/users/'.$target->id, [
            'nombre' => $target->name, 'correo' => $target->email, 'rol' => 'Responsable', 'area' => 'Atención institucional', 'activo' => true,
            'password' => 'clave-nueva-segura-de-pruebas', 'password_confirmation' => 'clave-nueva-segura-de-pruebas',
        ])->assertOk();
        $this->assertSame(2, PushSubscription::query()->where('user_id', $target->id)->whereNotNull('revoked_at')->count());
        $this->assertSame(0, PushSubscription::query()->where('user_id', $target->id)->whereNull('revoked_at')->count());
    }
}
