<?php

namespace Tests\Feature;

use App\Http\Middleware\IdentifyPushDevice;
use App\Jobs\SendWebPush;
use App\Models\EmergencyType;
use App\Models\Enums\PushDeliveryStatus;
use App\Models\Incident;
use App\Models\InstitutionSetting;
use App\Models\InternalNotification;
use App\Models\Location;
use App\Models\PushDelivery;
use App\Models\PushSubscription;
use App\Models\User;
use App\Services\WebPushService;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Minishlink\WebPush\MessageSentReport;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\NullLogger;
use RuntimeException;
use Tests\TestCase;

class WebPushDeliveryTest extends TestCase
{
    use RefreshDatabase;

    private string $keyFile;

    private WebPush $transport;

    private WebPushService $sender;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->keyFile = storage_path('framework/testing/sigei-test-sender-'.Str::uuid().'.json');
        config(['sigei.push.enabled' => true, 'sigei.push.subject' => 'mailto:push@example.test', 'sigei.push.key_file' => $this->keyFile]);
        $this->artisan('sigei:push-keys')->assertSuccessful();
        $this->transport = Mockery::mock(WebPush::class);
        $this->sender = new WebPushService($this->transport);
        $this->app->instance(WebPushService::class, $this->sender);
    }

    protected function tearDown(): void
    {
        if (isset($this->keyFile) && is_file($this->keyFile)) {
            unlink($this->keyFile);
        }

        parent::tearDown();
    }

    private function device(User $user, ?string $device = null): PushSubscription
    {
        $keys = $this->sender->keys();
        $subscription = new PushSubscription([
            'user_id' => $user->id, 'device_id' => $device ?? (string) Str::uuid(),
            'endpoint' => 'https://fcm.googleapis.com/fcm/send/test-'.Str::uuid(),
            'public_key' => $keys['public_key'], 'auth_token' => rtrim(strtr(base64_encode(random_bytes(16)), '+/', '-_'), '='),
        ]);
        $subscription->binding_id = (string) Str::uuid();
        $subscription->auth_version = $user->auth_version;
        $subscription->session_hash = hash('sha256', session()->getId());
        $subscription->save();

        return $subscription;
    }

    private function notice(User $user, ?Incident $incident = null, string $kind = 'incident.updated'): InternalNotification
    {
        return InternalNotification::query()->create([
            'user_id' => $user->id, 'incident_id' => ($incident ?? Incident::factory()->create(['reporter_user_id' => $user->id]))->id,
            'kind' => $kind, 'title' => 'Private incident title', 'body' => 'Private incident body',
        ]);
    }

    private function report(int $status): MessageSentReport
    {
        return new MessageSentReport(new Request('POST', 'https://fcm.googleapis.com/test'), new Response($status), $status >= 200 && $status < 300, 'sensitive-provider-reason');
    }

    private function asDevice(User $user, PushSubscription $subscription): static
    {
        $this->actingAs($user->refresh())->withSession(['account_auth_version' => $user->auth_version]);
        $subscription->session_hash = hash('sha256', session()->getId());
        $subscription->save();

        return $this->withCookie(IdentifyPushDevice::COOKIE, $subscription->device_id)
            ->withCookie(config('session.cookie'), session()->getId())->withCredentials();
    }

    public function test_notification_and_jobs_are_atomic_and_one_job_is_created_per_device_binding(): void
    {
        $user = User::factory()->administrator()->active()->create();
        $first = $this->device($user);
        $this->device($user);
        $notice = $this->notice($user);
        $this->assertDatabaseCount('push_deliveries', 2);
        $this->assertDatabaseCount('jobs', 2);
        $this->sender->queueNotification($notice);
        $this->assertDatabaseCount('jobs', 2);
        $payload = DB::table('jobs')->first()->payload;
        $this->assertStringNotContainsString($first->endpoint, $payload);
        $this->assertStringNotContainsString($first->auth_token, $payload);
        DB::beginTransaction();
        $this->notice($user);
        $this->assertDatabaseCount('push_deliveries', 4);
        DB::rollBack();
        $this->assertDatabaseCount('push_deliveries', 2);
        $this->assertDatabaseCount('jobs', 2);
    }

    public function test_accepted_push_contains_only_opaque_ids_and_does_not_confirm_receipt_or_reading(): void
    {
        $user = User::factory()->responsible()->active()->create();
        $subscription = $this->device($user);
        $notice = $this->notice($user);
        $delivery = PushDelivery::query()->sole();
        $this->transport->shouldReceive('sendOneNotification')->once()->withArgs(function (Subscription $target, string $payload, array $options) use ($notice, $subscription): bool {
            $this->assertSame(['notification_id' => $notice->id, 'binding_id' => $subscription->binding_id], json_decode($payload, true));
            $this->assertSame(3600, $options['TTL']);

            return true;
        })->andReturn($this->report(201));
        $this->assertFalse($this->sender->send($delivery->id));
        $this->assertSame(PushDeliveryStatus::Accepted, $delivery->refresh()->status);
        $this->assertSame(201, $delivery->provider_status);
        $this->assertSame(1, $delivery->attempts);
        $this->assertNotNull($delivery->accepted_at);
        $this->assertNull($notice->refresh()->read_at);
        $this->assertDatabaseCount('incident_assignments', 0);
        $this->assertFalse($this->sender->send($delivery->id));
    }

    /** @return array<string, array{int}> */
    public static function expiredResponses(): array
    {
        return ['not found' => [404], 'gone' => [410]];
    }

    #[DataProvider('expiredResponses')]
    public function test_expired_endpoints_are_revoked_without_retries(int $status): void
    {
        $user = User::factory()->administrator()->active()->create();
        $subscription = $this->device($user);
        $this->notice($user);
        $delivery = PushDelivery::query()->sole();
        $this->transport->shouldReceive('sendOneNotification')->once()->andReturn($this->report($status));
        $this->assertFalse($this->sender->send($delivery->id));
        $this->assertSame(PushDeliveryStatus::Expired, $delivery->refresh()->status);
        $this->assertNotNull($subscription->refresh()->revoked_at);
    }

    public function test_an_old_provider_response_cannot_revoke_a_new_binding(): void
    {
        $user = User::factory()->administrator()->active()->create();
        $subscription = $this->device($user);
        $this->notice($user);
        $this->transport->shouldReceive('sendOneNotification')->once()->andReturnUsing(function () use ($subscription): MessageSentReport {
            $subscription->binding_id = (string) Str::uuid();
            $subscription->save();

            return $this->report(410);
        });
        $this->sender->send(PushDelivery::query()->sole()->id);
        $this->assertNull($subscription->refresh()->revoked_at);
    }

    /** @return array<string, array{int}> */
    public static function retryResponses(): array
    {
        return ['rate limited' => [429], 'temporary failure' => [503]];
    }

    #[DataProvider('retryResponses')]
    public function test_temporary_failures_retry_and_can_later_be_accepted(int $status): void
    {
        $user = User::factory()->administrator()->active()->create();
        $this->device($user);
        $this->notice($user);
        $delivery = PushDelivery::query()->sole();
        $this->transport->shouldReceive('sendOneNotification')->twice()->andReturn($this->report($status), $this->report(201));
        $this->assertTrue($this->sender->send($delivery->id));
        $this->assertSame(PushDeliveryStatus::Queued, $delivery->refresh()->status);
        $this->assertFalse($this->sender->send($delivery->id));
        $this->assertSame(PushDeliveryStatus::Accepted, $delivery->refresh()->status);
        $this->assertSame(2, $delivery->attempts);
    }

    public function test_permanent_rejection_is_recorded_without_logging_sensitive_provider_details(): void
    {
        $user = User::factory()->administrator()->active()->create();
        $subscription = $this->device($user);
        $this->notice($user);
        $this->transport->shouldReceive('sendOneNotification')->once()->andReturn($this->report(403));
        $delivery = PushDelivery::query()->sole();
        $this->assertFalse($this->sender->send($delivery->id));
        $this->assertSame(PushDeliveryStatus::Failed, $delivery->refresh()->status);
        $this->assertSame('provider_rejected', $delivery->error_code);
        $this->assertStringNotContainsString('sensitive-provider', $delivery->toJson());
        $this->assertStringNotContainsString($subscription->endpoint, $delivery->toJson());
    }

    public function test_transport_exceptions_are_sanitized_and_exhaustion_records_failure(): void
    {
        $user = User::factory()->administrator()->active()->create();
        $this->device($user);
        $this->notice($user);
        $delivery = PushDelivery::query()->sole();
        $this->transport->shouldReceive('sendOneNotification')->once()->andThrow(new RuntimeException('sensitive endpoint or key'));
        $job = new SendWebPush($delivery->id);

        try {
            $job->handle($this->sender);
            $this->fail('The temporary failure must be retried.');
        } catch (RuntimeException $exception) {
            $this->assertSame('webpush_temporary_failure', $exception->getMessage());
            $this->assertNull($exception->getPrevious());
        }

        $job->failed(new RuntimeException('queue exhausted'));
        $this->assertSame(PushDeliveryStatus::Failed, $delivery->refresh()->status);
        $this->assertSame('retry_exhausted', $delivery->error_code);
    }

    public function test_queued_messages_are_skipped_when_the_binding_account_or_case_access_changes(): void
    {
        $user = User::factory()->responsible()->active()->create();
        $subscription = $this->device($user);
        $incident = Incident::factory()->create(['reporter_user_id' => $user->id]);
        $this->notice($user, $incident);
        $delivery = PushDelivery::query()->sole();
        $incident->reporter_user_id = null;
        $incident->save();
        $this->transport->shouldNotReceive('sendOneNotification');
        $this->assertFalse($this->sender->send($delivery->id));
        $this->assertSame(PushDeliveryStatus::Skipped, $delivery->refresh()->status);
        $incident->reporter_user_id = $user->id;
        $incident->save();
        $this->notice($user, $incident);
        $new = PushDelivery::query()->latest('id')->first();
        $subscription->binding_id = (string) Str::uuid();
        $subscription->save();
        $this->assertFalse($this->sender->send($new->id));
        $this->assertSame(PushDeliveryStatus::Skipped, $new->refresh()->status);
    }

    public function test_preferences_and_allowed_hosts_are_rechecked_before_sending(): void
    {
        $user = User::factory()->administrator()->active()->create();
        $subscription = $this->device($user);
        $this->notice($user);
        InstitutionSetting::query()->sole()->update(['notify_updates' => false]);
        $this->transport->shouldNotReceive('sendOneNotification');
        $delivery = PushDelivery::query()->sole();
        $this->assertFalse($this->sender->send($delivery->id));
        $this->assertSame(PushDeliveryStatus::Skipped, $delivery->refresh()->status);
        InstitutionSetting::query()->sole()->update(['notify_updates' => true]);
        $this->notice($user);
        $subscription->endpoint = 'https://127.0.0.1/internal';
        $subscription->save();
        $latest = PushDelivery::query()->latest('id')->first();
        $this->assertFalse($this->sender->send($latest->id));
        $this->assertSame('endpoint_not_allowed', $latest->refresh()->error_code);
    }

    public function test_test_button_targets_only_this_device_and_reuses_a_pending_test(): void
    {
        $user = User::factory()->responsible()->active()->create();
        $subscription = $this->device($user);
        $this->device($user);
        $this->asDevice($user, $subscription);
        $response = $this->postJson('/api/push/test', ['cuenta_esperada' => $user->id])->assertStatus(202)->assertJsonPath('latest_test.status', 'queued');
        $id = $response->json('latest_test.id');
        $this->assertDatabaseCount('push_deliveries', 1);
        $this->assertDatabaseCount('incidents', 0);
        $this->assertDatabaseCount('jobs', 1);
        $this->assertSame($subscription->id, PushDelivery::query()->sole()->subscription_id);
        $this->postJson('/api/push/test', ['cuenta_esperada' => $user->id])->assertStatus(202)->assertJsonPath('latest_test.id', $id);
        $this->getJson('/api/push')->assertJsonPath('latest_test.id', $id)->assertJsonPath('delivery_available', true);
    }

    public function test_unlinked_guests_stale_accounts_and_arbitrary_test_payloads_are_rejected(): void
    {
        $this->postJson('/api/push/test', ['cuenta_esperada' => 1])->assertUnauthorized();
        $user = User::factory()->responsible()->active()->create();
        $this->actingAs($user)->withSession(['account_auth_version' => $user->auth_version]);
        $this->postJson('/api/push/test', ['cuenta_esperada' => $user->id])->assertUnprocessable();
        $subscription = $this->device($user);
        $this->asDevice($user, $subscription)->postJson('/api/push/test', ['cuenta_esperada' => $user->id + 1])->assertConflict();
        $this->postJson('/api/push/test', ['cuenta_esperada' => $user->id, 'body' => 'Injected body'])->assertUnprocessable();
        $this->assertDatabaseCount('push_deliveries', 0);
    }

    public function test_background_notice_survives_session_expiry_but_case_opening_requires_login(): void
    {
        $user = User::factory()->responsible()->active()->create();
        $subscription = $this->device($user);
        $notice = $this->notice($user);
        $this->asDevice($user, $subscription);
        Auth::logout();
        $this->withHeader('X-SIGEI-Push-Binding', $subscription->binding_id)->getJson('/api/push/notifications/'.$notice->id)->assertOk();
        $this->getJson('/api/incidents/'.$notice->incident->folio)->assertUnauthorized();
        $this->assertNull($notice->refresh()->read_at);
    }

    public function test_real_library_builds_encrypted_requests_using_an_injected_http_client_without_network(): void
    {
        $user = User::factory()->administrator()->active()->create();
        $subscription = $this->device($user);
        $this->notice($user);
        $client = new class implements ClientInterface
        {
            /** @var list<RequestInterface> */
            public array $requests = [];

            public function sendRequest(RequestInterface $request): ResponseInterface
            {
                $this->requests[] = $request;

                return new Response(201);
            }
        };
        $keys = $this->sender->keys();
        $transport = new WebPush(['VAPID' => ['subject' => 'mailto:push@example.test', 'publicKey' => $keys['public_key'], 'privateKey' => $keys['private_key']]], [], $client, logger: new NullLogger);
        $sender = new WebPushService($transport);
        $delivery = PushDelivery::query()->sole();
        $this->assertFalse($sender->send($delivery->id));
        $this->assertSame(PushDeliveryStatus::Accepted, $delivery->refresh()->status);
        $this->assertCount(1, $client->requests);
        $request = $client->requests[0];
        $this->assertSame($subscription->endpoint, (string) $request->getUri());
        $this->assertSame('aes128gcm', $request->getHeaderLine('Content-Encoding'));
        $this->assertStringStartsWith('vapid ', $request->getHeaderLine('Authorization'));
        $this->assertStringNotContainsString('notification_id', (string) $request->getBody());
        $this->assertStringNotContainsString('Private incident', (string) $request->getBody());
    }

    public function test_public_report_is_persisted_without_contacting_an_unavailable_push_provider(): void
    {
        $administrator = User::factory()->administrator()->active()->create();
        $this->device($administrator);
        $this->transport->shouldNotReceive('sendOneNotification');
        $this->postJson('/api/incidents', [
            'tipo_id' => EmergencyType::query()->first()->id, 'ubicacion_id' => Location::query()->where('requires_details', false)->first()->id,
            'solicitud_id' => (string) Str::uuid(),
        ])->assertCreated();
        $this->assertDatabaseCount('incidents', 1);
        $this->assertDatabaseCount('internal_notifications', 1);
        $this->assertDatabaseCount('push_deliveries', 1);
        $this->assertDatabaseCount('jobs', 1);
    }

    public function test_disabled_delivery_keeps_internal_notifications_without_creating_jobs(): void
    {
        $user = User::factory()->administrator()->active()->create();
        $this->device($user);
        config(['sigei.push.enabled' => false]);
        $this->notice($user);
        $this->assertDatabaseCount('internal_notifications', 1);
        $this->assertDatabaseCount('push_deliveries', 0);
        $this->assertDatabaseCount('jobs', 0);
    }
}
