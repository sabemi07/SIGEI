<?php

namespace App\Http\Controllers;

use App\Http\Requests\SavePushSubscriptionRequest;
use App\Http\Requests\TestPushNotificationRequest;
use App\Models\AuditLog;
use App\Models\Enums\AccountRole;
use App\Models\InstitutionSetting;
use App\Models\InternalNotification;
use App\Models\PushDelivery;
use App\Models\PushSubscription;
use App\Models\User;
use App\Services\WebPushService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PushSubscriptionController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $subscription = $this->currentSubscription($request);

        return response()->json([
            'public_key' => $this->publicKey(),
            'delivery_available' => app(WebPushService::class)->isAvailable(),
            'subscription' => $this->subscriptionState($subscription),
            'latest_test' => $this->latestTest($subscription)?->summary(),
        ]);
    }

    public function store(SavePushSubscriptionRequest $request): JsonResponse
    {
        abort_if($this->publicKey() === null, 503, 'Las claves Web Push todavía no están preparadas.');

        return DB::transaction(function () use ($request): JsonResponse {
            $actor = $this->lockActor($request);
            abort_unless($request->integer('cuenta_esperada') === $actor->id, 409, 'La cuenta cambió. Revisa tu sesión antes de vincular este dispositivo.');
            $device = $request->attributes->get('push_device_id');
            $hash = hash('sha256', $request->validated('endpoint'));
            $existingEndpoint = PushSubscription::query()->where('endpoint_hash', $hash)->first();
            abort_if($existingEndpoint !== null && ($existingEndpoint->device_id !== $device || $existingEndpoint->user_id !== $actor->id), 409,
                'La suscripción pertenece a otro registro de dispositivo. Desvincula la suscripción del navegador y vuelve a intentar.');
            PushSubscription::query()->where('device_id', $device)->whereNull('revoked_at')->update(['revoked_at' => now('UTC')]);
            $subscription = PushSubscription::query()->where('user_id', $actor->id)->where('device_id', $device)->first()
                ?? new PushSubscription(['user_id' => $actor->id, 'device_id' => $device]);

            $subscription->endpoint = $request->validated('endpoint');
            $subscription->public_key = $request->validated('keys.p256dh');
            $subscription->auth_token = $request->validated('keys.auth');
            $subscription->content_encoding = 'aes128gcm';
            $subscription->expires_at = $request->filled('expirationTime') ? Carbon::createFromTimestampMs($request->integer('expirationTime'), 'UTC') : null;
            $subscription->revoked_at = null;
            $subscription->auth_version = $actor->auth_version;
            $subscription->session_hash = hash('sha256', $request->session()->getId());
            $subscription->binding_id = (string) Str::uuid();
            $subscription->save();
            $this->audit($actor, $subscription, 'push.subscribed');

            return response()->json(['subscription' => $this->subscriptionState($subscription), 'delivery_available' => app(WebPushService::class)->isAvailable()]);
        }, 3);
    }

    public function destroy(Request $request): JsonResponse
    {
        return DB::transaction(function () use ($request): JsonResponse {
            $actor = $this->lockActor($request);
            $subscription = $this->currentSubscription($request);

            if ($subscription !== null) {
                $subscription->revoked_at = now('UTC');
                $subscription->save();
                $this->audit($actor, $subscription, 'push.unsubscribed');
            }

            return response()->json(['subscription' => null]);
        }, 3);
    }

    public function notification(Request $request, InternalNotification $notification): JsonResponse
    {
        $subscription = PushSubscription::query()->where('device_id', $request->attributes->get('push_device_id'))
            ->whereNull('revoked_at')->whereNotNull('binding_id')->first();
        abort_unless($subscription !== null && hash_equals((string) $subscription->binding_id, (string) $request->header('X-SIGEI-Push-Binding'))
            && ($request->user() === null || $request->user()->id === $subscription->user_id)
            && app(WebPushService::class)->canNotify($subscription, $notification), 404);

        return response()->json([
            'id' => $notification->id,
            'title' => 'SIGEI · Aviso institucional',
            'body' => 'Abre el sistema para consultar un aviso de tu cuenta.',
            'url' => '/?aviso='.rawurlencode($notification->id),
            'is_test' => $notification->kind === 'push.test',
        ]);
    }

    public function test(TestPushNotificationRequest $request, WebPushService $sender): JsonResponse
    {
        abort_unless($sender->isAvailable(), 503, 'El envío Web Push todavía no está configurado.');

        return DB::transaction(function () use ($request, $sender): JsonResponse {
            $actor = $this->lockActor($request);
            abort_unless($request->integer('cuenta_esperada') === $actor->id, 409, 'La cuenta cambió antes de solicitar la prueba.');
            $subscription = $this->currentSubscription($request);
            abort_if($subscription === null, 422, 'Vincula este dispositivo antes de solicitar la prueba.');
            $previous = $this->latestTest($subscription);

            if ($previous !== null && in_array($previous->status->value, ['queued', 'sending'], true)
                && $previous->created_at->gt(now('UTC')->subSeconds(config('sigei.push.ttl')))) {
                return response()->json(['latest_test' => $previous->summary()], 202);
            }

            $notification = InternalNotification::query()->create([
                'user_id' => $actor->id, 'kind' => 'push.test', 'title' => 'Prueba de notificaciones',
                'body' => 'Prueba solicitada desde esta cuenta. No corresponde a un incidente.',
            ]);
            $sender->queueNotification($notification, $subscription->id);
            $delivery = PushDelivery::query()->where('notification_id', $notification->id)->sole();
            AuditLog::query()->create(['actor_id' => $actor->id, 'action' => 'push.test_requested', 'subject_type' => 'push_delivery', 'subject_id' => $delivery->id]);

            return response()->json(['latest_test' => $delivery->summary()], 202);
        }, 3);
    }

    private function latestTest(?PushSubscription $subscription): ?PushDelivery
    {
        return $subscription === null ? null : PushDelivery::query()->where('subscription_id', $subscription->id)
            ->where('binding_id', $subscription->binding_id)->whereHas('notification', fn ($query) => $query->where('kind', 'push.test'))
            ->latest('id')->first();
    }

    private function currentSubscription(Request $request): ?PushSubscription
    {
        return PushSubscription::query()->where('user_id', $request->user()->id)
            ->where('device_id', $request->attributes->get('push_device_id'))
            ->where('auth_version', $request->user()->auth_version)
            ->where('session_hash', hash('sha256', $request->session()->getId()))
            ->whereNull('revoked_at')->whereNotNull('binding_id')
            ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now('UTC')))->first();
    }

    private function lockActor(Request $request): User
    {
        abort_unless(InstitutionSetting::query()->whereKey('institution')->lockForUpdate()->first() !== null, 503);
        $actor = User::query()->lockForUpdate()->find($request->user()?->getAuthIdentifier());
        abort_unless($actor?->is_active && in_array($actor->role, [AccountRole::Administrator, AccountRole::Responsible], true)
            && $request->session()->get('account_auth_version') === $actor->auth_version, 403);

        return $actor;
    }

    private function publicKey(): ?string
    {
        return app(WebPushService::class)->keys()['public_key'] ?? null;
    }

    /** @return array{id: int, binding_id: string, endpoint_hash: string}|null */
    private function subscriptionState(?PushSubscription $subscription): ?array
    {
        return $subscription === null ? null : [
            'id' => $subscription->id,
            'binding_id' => $subscription->binding_id,
            'endpoint_hash' => $subscription->endpoint_hash,
        ];
    }

    private function audit(User $actor, PushSubscription $subscription, string $action): void
    {
        AuditLog::query()->create([
            'actor_id' => $actor->id, 'action' => $action, 'subject_type' => 'push_subscription', 'subject_id' => $subscription->id,
        ]);
    }
}
