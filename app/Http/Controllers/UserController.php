<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveUserRequest;
use App\Http\Resources\AccountResource;
use App\Models\AuditLog;
use App\Models\Enums\AccountRole;
use App\Models\InstitutionSetting;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class UserController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', User::class);

        return AccountResource::collection(User::query()->orderBy('name')->orderBy('id')->get());
    }

    public function store(SaveUserRequest $request): JsonResponse
    {
        $user = DB::transaction(function () use ($request): User {
            $this->lockInstitution();
            $actor = $this->authorizeActor($request);
            $this->assertUniqueEmail($request->validated('correo'));
            $user = new User;
            $this->applyAttributes($user, $request);
            $user->save();
            $this->audit($actor, $user, 'user.created', null);

            return $user;
        }, 3);

        return response()->json([
            'data' => new AccountResource($user),
            'session_ended' => false,
            'csrf_token' => csrf_token(),
        ], 201);
    }

    public function update(SaveUserRequest $request, User $user): JsonResponse
    {
        [$user, $endSession] = DB::transaction(function () use ($request, $user): array {
            $this->lockInstitution();
            $actor = $this->authorizeActor($request);
            $target = User::query()->lockForUpdate()->findOrFail($user->getKey());
            $this->assertUniqueEmail($request->validated('correo'), $target);
            $before = $this->auditAttributes($target);
            $wasActiveAdministrator = $target->is_active && $target->role === AccountRole::Administrator;
            $this->applyAttributes($target, $request);

            if ($wasActiveAdministrator && (! $target->is_active || $target->role !== AccountRole::Administrator)
                && User::query()->where('role', AccountRole::Administrator)->where('is_active', true)->count() <= 1) {
                throw ValidationException::withMessages(['rol' => 'Debe permanecer al menos un Administrador activo.']);
            }

            $securityChanged = $target->isDirty(['email', 'password', 'role', 'is_active']);

            if ($securityChanged) {
                $target->auth_version = $target->auth_version + 1;
                $target->remember_token = Str::random(60);
            }

            $target->save();

            if ($securityChanged) {
                DB::table('sessions')->where('user_id', $target->id)->delete();
            }

            if ($securityChanged) {
                $target->pushSubscriptions()->whereNull('revoked_at')->update(['revoked_at' => now('UTC')]);
            }

            $this->audit($actor, $target, 'user.updated', $before);

            return [$target, $securityChanged && $actor->is($target)];
        }, 3);

        if ($endSession) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return response()->json([
            'data' => new AccountResource($user),
            'session_ended' => $endSession,
            'csrf_token' => csrf_token(),
        ]);
    }

    private function lockInstitution(): void
    {
        abort_unless(
            InstitutionSetting::query()->whereKey('institution')->lockForUpdate()->first() !== null,
            503,
            'Inicializa la configuración y los catálogos antes de administrar cuentas.'
        );
    }

    private function authorizeActor(Request $request): User
    {
        $actor = User::query()->lockForUpdate()->find($request->user()?->getAuthIdentifier());
        abort_unless($actor?->is_active && $actor->role === AccountRole::Administrator
            && $request->session()->get('account_auth_version') === $actor->auth_version, 403);

        return $actor;
    }

    private function assertUniqueEmail(string $email, ?User $user = null): void
    {
        if (User::query()->where('email', $email)->when($user, fn ($query) => $query->whereKeyNot($user->getKey()))->exists()) {
            throw ValidationException::withMessages(['correo' => 'Ese correo ya está registrado.']);
        }
    }

    private function applyAttributes(User $user, SaveUserRequest $request): void
    {
        $user->name = $request->validated('nombre');
        $user->email = $request->validated('correo');
        $user->area = $request->validated('area');
        $user->role = AccountRole::from($request->validated('rol'));
        $user->is_active = $request->boolean('activo');

        if ($request->filled('password')) {
            $user->password = $request->validated('password');
        }
    }

    /** @return array{name: string, email: string, area: ?string, role: string, is_active: bool} */
    private function auditAttributes(User $user): array
    {
        return [
            'name' => $user->name,
            'email' => $user->email,
            'area' => $user->area,
            'role' => $user->role->value,
            'is_active' => $user->is_active,
        ];
    }

    /** @param array<string, mixed>|null $before */
    private function audit(User $actor, User $target, string $action, ?array $before): void
    {
        AuditLog::query()->create([
            'actor_id' => $actor->id,
            'action' => $action,
            'subject_type' => 'user',
            'subject_id' => $target->id,
            'metadata' => ['before' => $before, 'after' => $this->auditAttributes($target)],
        ]);
    }
}
