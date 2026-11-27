<?php

namespace App\Http\Controllers;

use App\Http\Middleware\EnsureAccountIsActive;
use App\Http\Requests\LoginRequest;
use App\Http\Resources\AccountResource;
use App\Models\Enums\AccountRole;
use App\Models\InstitutionSetting;
use App\Models\PushSubscription;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AuthSessionController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $user = EnsureAccountIsActive::activeUser($request);

        return response()->json([
            'user' => $user === null ? null : new AccountResource($user),
            'csrf_token' => csrf_token(),
        ])->header('Cache-Control', 'no-store');
    }

    public function store(LoginRequest $request): JsonResponse
    {
        if (EnsureAccountIsActive::activeUser($request) !== null) {
            return response()->json([
                'message' => 'Cierra tu sesión antes de entrar con otra cuenta.',
            ], 409)->header('Cache-Control', 'no-store');
        }

        $request->authenticate();
        DB::transaction(function () use ($request): void {
            InstitutionSetting::query()->whereKey('institution')->lockForUpdate()->first();
            $actor = User::query()->lockForUpdate()->find($request->user()?->id);

            if (! $actor?->is_active || ! in_array($actor->role, [AccountRole::Administrator, AccountRole::Responsible], true)
                || $actor->auth_version !== $request->user()->auth_version) {
                EnsureAccountIsActive::endSession($request);
                abort(403, 'La cuenta cambió durante el acceso. Inicia sesión de nuevo.');
            }

            PushSubscription::query()->where('device_id', $request->attributes->get('push_device_id'))
                ->whereNull('revoked_at')->update(['revoked_at' => now('UTC')]);
            $request->session()->put('account_auth_version', $request->user()->auth_version);
            $request->session()->regenerate();
        }, 3);

        return response()->json([
            'user' => new AccountResource($request->user()),
            'csrf_token' => csrf_token(),
        ])->header('Cache-Control', 'no-store');
    }

    public function destroy(Request $request): JsonResponse
    {
        EnsureAccountIsActive::endSession($request);

        return response()->json([
            'user' => null,
            'csrf_token' => csrf_token(),
        ])->header('Cache-Control', 'no-store');
    }
}
