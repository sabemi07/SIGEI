<?php

namespace App\Http\Controllers;

use App\Http\Resources\IncidentResource;
use App\Models\Enums\AccountRole;
use App\Models\Incident;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class IncidentReadController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $input = $request->validate([
            'scope' => ['sometimes', Rule::in(['all', 'assigned', 'mine'])],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);
        $scope = $input['scope'] ?? 'all';
        $user = $request->user();
        $query = Incident::query()->with(['currentAssignment.assignee', 'reporter', 'updates.author']);

        if ($scope === 'mine') {
            $query->where('reporter_user_id', $user->id);
        } elseif ($scope === 'assigned') {
            $query->whereHas('currentAssignment', fn (Builder $query) => $query->where('assigned_to', $user->id));
        } elseif ($user->role !== AccountRole::Administrator) {
            $query->where(function (Builder $query) use ($user): void {
                $query->where('reporter_user_id', $user->id)
                    ->orWhereHas('currentAssignment', fn (Builder $query) => $query->where('assigned_to', $user->id));
            });
        }

        return IncidentResource::collection(
            $query->orderByDesc('reported_at')->orderByDesc('id')->paginate($input['per_page'] ?? 100)->withQueryString()
        );
    }

    public function show(Request $request, Incident $incident): IncidentResource
    {
        abort_unless(Gate::forUser($request->user())->allows('view', $incident), 404);

        return new IncidentResource($incident->load(['currentAssignment.assignee', 'reporter', 'updates.author']));
    }

    public function responsibles(): JsonResponse
    {
        Gate::authorize('viewAny', User::class);

        return response()->json([
            'data' => User::query()->where('role', AccountRole::Responsible)->where('is_active', true)
                ->orderBy('name')->orderBy('id')->get()->map(fn (User $user): array => [
                    'id' => $user->id,
                    'nombre' => $user->name,
                    'rol' => $user->role->value,
                    'activo' => $user->is_active,
                ])->all(),
        ]);
    }
}
