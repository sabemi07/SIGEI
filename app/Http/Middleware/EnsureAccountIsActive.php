<?php

namespace App\Http\Middleware;

use App\Models\Enums\AccountRole;
use App\Models\InstitutionSetting;
use App\Models\PushSubscription;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class EnsureAccountIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        if (self::activeUser($request) === null) {
            return response()->json([
                'message' => 'Tu sesión terminó. Inicia sesión de nuevo.',
                'csrf_token' => csrf_token(),
            ], 401)->header('Cache-Control', 'no-store');
        }

        return $next($request);
    }

    public static function activeUser(Request $request): ?User
    {
        $sessionUser = $request->user();

        if (! $sessionUser instanceof User) {
            return null;
        }

        $user = User::query()->find($sessionUser->getAuthIdentifier());

        if ($user === null
            || ! $user->is_active
            || ! in_array($user->getRawOriginal('role'), array_column(AccountRole::cases(), 'value'), true)
            || $request->session()->get('account_auth_version') !== $user->auth_version) {
            self::endSession($request);

            return null;
        }

        Auth::setUser($user);

        return $user;
    }

    public static function endSession(Request $request): void
    {
        DB::transaction(function () use ($request): void {
            InstitutionSetting::query()->whereKey('institution')->lockForUpdate()->first();
            PushSubscription::query()->where('device_id', $request->attributes->get('push_device_id'))
                ->where('session_hash', hash('sha256', $request->session()->getId()))
                ->whereNull('revoked_at')->update(['revoked_at' => now('UTC')]);
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }, 3);
    }
}
