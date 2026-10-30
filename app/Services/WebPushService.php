<?php

namespace App\Services;

use App\Jobs\SendWebPush;
use App\Models\Enums\AccountRole;
use App\Models\Enums\PushDeliveryStatus;
use App\Models\InstitutionSetting;
use App\Models\InternalNotification;
use App\Models\PushDelivery;
use App\Models\PushSubscription;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;
use Psr\Log\NullLogger;
use Throwable;

class WebPushService
{
    public function __construct(private ?WebPush $transport = null) {}

    /** @return array{public_key: string, private_key: string}|null */
    public function keys(): ?array
    {
        $path = config('sigei.push.key_file');
        $keys = is_readable($path) ? json_decode(file_get_contents($path), true) : null;

        return is_array($keys) && is_string($keys['private_key'] ?? null) && is_string($keys['public_key'] ?? null)
            && preg_match('/^[A-Za-z0-9_-]{43}$/D', $keys['private_key']) === 1
            && preg_match('/^[A-Za-z0-9_-]{87}$/D', $keys['public_key']) === 1
            ? ['public_key' => $keys['public_key'], 'private_key' => $keys['private_key']] : null;
    }

    public function isAvailable(): bool
    {
        $subject = config('sigei.push.subject', '');
        $validSubject = is_string($subject) && ((str_starts_with($subject, 'mailto:') && filter_var(substr($subject, 7), FILTER_VALIDATE_EMAIL))
            || (str_starts_with($subject, 'https://') && filter_var($subject, FILTER_VALIDATE_URL)));
        $queueDatabase = config('queue.connections.database.connection');

        return config('sigei.push.enabled') && $validSubject && $this->keys() !== null
            && ($queueDatabase === null || $queueDatabase === config('database.default'));
    }

    public static function isAllowedEndpoint(string $endpoint): bool
    {
        $url = parse_url($endpoint);
        $host = strtolower($url['host'] ?? '');
        $allowed = collect(config('sigei.push.endpoint_hosts'))->contains(fn (string $pattern): bool => str_starts_with($pattern, '*.') ? Str::endsWith($host, substr($pattern, 1)) : $host === $pattern);

        return ($url['scheme'] ?? '') === 'https' && $allowed && ! isset($url['user']) && ! isset($url['pass'])
            && ! isset($url['fragment']) && ($url['port'] ?? 443) === 443;
    }

    public function queueNotification(InternalNotification $notification, ?int $onlySubscription = null): void
    {
        if (! $this->isAvailable() || ! in_array($notification->kind, ['incident.created', 'incident.assigned', 'incident.updated', 'push.test'], true)
            || ($notification->kind === 'push.test' && $onlySubscription === null)) {
            return;
        }

        DB::transaction(function () use ($notification, $onlySubscription): void {
            $subscriptions = PushSubscription::query()->where('user_id', $notification->user_id)->whereNull('revoked_at')
                ->whereNotNull('binding_id')->when($onlySubscription !== null, fn ($query) => $query->whereKey($onlySubscription))->get();

            foreach ($subscriptions as $subscription) {
                if (! $this->canNotify($subscription, $notification)) {
                    continue;
                }

                $delivery = PushDelivery::query()->firstOrCreate([
                    'notification_id' => $notification->id, 'subscription_id' => $subscription->id, 'binding_id' => $subscription->binding_id,
                ]);

                if ($delivery->wasRecentlyCreated) {
                    SendWebPush::dispatch($delivery->id)->onConnection('database')->onQueue('webpush')->beforeCommit();
                }
            }
        }, 3);
    }

    public function canNotify(PushSubscription $subscription, InternalNotification $notification): bool
    {
        $user = $subscription->user;

        if ($user === null || ! $user->is_active || ! in_array($user->role, [AccountRole::Administrator, AccountRole::Responsible], true)
            || $subscription->revoked_at !== null || $subscription->binding_id === null || $subscription->session_hash === null
            || $subscription->auth_version !== $user->auth_version || $subscription->user_id !== $notification->user_id
            || ($subscription->expires_at !== null && $subscription->expires_at->isPast())) {
            return false;
        }

        if ($notification->kind === 'push.test') {
            return $notification->incident_id === null;
        }

        return $notification->incident !== null && Gate::forUser($user)->allows('view', $notification->incident);
    }

    public function send(int $deliveryId): bool
    {
        $delivery = DB::transaction(function () use ($deliveryId): ?PushDelivery {
            $delivery = PushDelivery::query()->lockForUpdate()->find($deliveryId);

            if ($delivery === null || ! in_array($delivery->status, [PushDeliveryStatus::Queued, PushDeliveryStatus::Sending], true)
                || ($delivery->status === PushDeliveryStatus::Sending && $delivery->last_attempt_at?->greaterThan(now('UTC')->subSeconds(60)))) {
                return null;
            }

            $subscription = $delivery->subscription;
            $notification = $delivery->notification;
            $settings = InstitutionSetting::query()->find('institution');
            $preference = match ($notification?->kind) {
                'incident.created' => $settings?->notify_new_incidents,
                'incident.assigned' => $settings?->notify_assignments,
                'incident.updated' => $settings?->notify_updates,
                'push.test' => true,
                default => false,
            };

            if (! $this->isAvailable() || $subscription === null || $notification === null
                || $subscription->binding_id !== $delivery->binding_id || ! $this->canNotify($subscription, $notification)
                || ! $preference || $delivery->created_at->lt(now('UTC')->subSeconds(config('sigei.push.ttl')))) {
                $delivery->status = PushDeliveryStatus::Skipped;
                $delivery->error_code = 'binding_or_notification_unavailable';
                $delivery->save();

                return null;
            }

            if (! self::isAllowedEndpoint($subscription->endpoint)) {
                $delivery->status = PushDeliveryStatus::Failed;
                $delivery->error_code = 'endpoint_not_allowed';
                $delivery->save();

                return null;
            }

            $delivery->status = PushDeliveryStatus::Sending;
            $delivery->attempts++;
            $delivery->last_attempt_at = now('UTC');
            $delivery->save();

            return $delivery;
        }, 3);

        if ($delivery === null) {
            return false;
        }

        $previousOpenSslConfig = getenv('OPENSSL_CONF');
        $opensslConfig = config('sigei.push.openssl_config');
        $bundledConfig = dirname(PHP_BINARY).'/extras/ssl/openssl.cnf';

        if (! filled($opensslConfig) && PHP_OS_FAMILY === 'Windows' && is_file($bundledConfig)) {
            $opensslConfig = $bundledConfig;
        }

        $changedOpenSslConfig = ! $previousOpenSslConfig && filled($opensslConfig) && is_file($opensslConfig);

        if ($changedOpenSslConfig) {
            putenv('OPENSSL_CONF='.$opensslConfig);
        }

        try {
            $keys = $this->keys();
            $transport = $this->transport ?? new WebPush([
                'VAPID' => ['subject' => config('sigei.push.subject'), 'publicKey' => $keys['public_key'], 'privateKey' => $keys['private_key']],
            ], [], new Client(['timeout' => 20, 'connect_timeout' => 8, 'allow_redirects' => false, 'http_errors' => false, 'verify' => true]), logger: new NullLogger);
            $subscription = $delivery->subscription;
            $report = $transport->sendOneNotification(Subscription::create([
                'endpoint' => $subscription->endpoint,
                'keys' => ['p256dh' => $subscription->public_key, 'auth' => $subscription->auth_token], 'contentEncoding' => $subscription->content_encoding,
            ]), json_encode(['notification_id' => $delivery->notification_id, 'binding_id' => $delivery->binding_id], JSON_THROW_ON_ERROR), [
                'TTL' => (int) config('sigei.push.ttl'), 'urgency' => 'high',
            ]);
            $status = $report->getResponse()?->getStatusCode();
            $expired = $report->isSubscriptionExpired();
            $accepted = $report->isSuccess();
            $tlsError = str_contains(strtolower($report->getReason()), 'certificate') || str_contains($report->getReason(), 'cURL error 60');
            $retry = ! $accepted && ! $expired && ! $tlsError && ($status === null || $status === 429 || $status >= 500);
            $code = $accepted ? null : ($expired ? 'subscription_expired' : ($tlsError ? 'tls_validation_failed' : ($retry ? 'temporary_provider_error' : 'provider_rejected')));
        } catch (Throwable) {
            $status = null;
            $expired = false;
            $accepted = false;
            $retry = true;
            $code = 'transport_error';
        } finally {
            if ($changedOpenSslConfig) {
                putenv($previousOpenSslConfig === false ? 'OPENSSL_CONF' : 'OPENSSL_CONF='.$previousOpenSslConfig);
            }
        }

        DB::transaction(function () use ($deliveryId, $delivery, $status, $expired, $accepted, $retry, $code): void {
            $stored = PushDelivery::query()->lockForUpdate()->find($deliveryId);

            if ($stored === null || $stored->status !== PushDeliveryStatus::Sending) {
                return;
            }

            $stored->status = $accepted ? PushDeliveryStatus::Accepted : ($expired ? PushDeliveryStatus::Expired : ($retry ? PushDeliveryStatus::Queued : PushDeliveryStatus::Failed));
            $stored->provider_status = $status;
            $stored->error_code = $code;
            $stored->accepted_at = $accepted ? now('UTC') : null;
            $stored->save();

            if ($expired) {
                PushSubscription::query()->whereKey($delivery->subscription_id)->where('binding_id', $delivery->binding_id)
                    ->whereNull('revoked_at')->update(['revoked_at' => now('UTC')]);
            }
        }, 3);

        return $retry;
    }
}
