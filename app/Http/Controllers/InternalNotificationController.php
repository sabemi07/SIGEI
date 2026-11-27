<?php

namespace App\Http\Controllers;

use App\Http\Resources\IncidentAlertResource;
use App\Http\Resources\IncidentResource;
use App\Models\InternalNotification;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class InternalNotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $input = $request->validate([
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);
        $notifications = $this->visibleNotifications($request->user())
            ->with('incident:id,folio,folio_corto')->orderByDesc('created_at')->orderByDesc('id')
            ->paginate($input['per_page'] ?? 100)->withQueryString();
        $notifications->through(fn (InternalNotification $notification): array => $this->present($notification));

        return response()->json([
            'data' => $notifications->items(),
            'links' => [
                'first' => $notifications->url(1),
                'last' => $notifications->url($notifications->lastPage()),
                'prev' => $notifications->previousPageUrl(),
                'next' => $notifications->nextPageUrl(),
            ],
            'meta' => [
                'current_page' => $notifications->currentPage(),
                'last_page' => $notifications->lastPage(),
                'per_page' => $notifications->perPage(),
                'total' => $notifications->total(),
            ],
        ]);
    }

    public function showIncident(Request $request, InternalNotification $notification): IncidentResource|IncidentAlertResource
    {
        $user = $request->user();
        abort_unless($notification->user_id === $user->id, 404);
        $incident = $notification->incident;
        abort_if($incident === null, 404);

        if (Gate::forUser($user)->allows('view', $incident)) {
            return new IncidentResource($incident);
        }

        abort_unless($notification->isNewIncidentAlert(), 404);

        return new IncidentAlertResource($incident);
    }

    public function read(Request $request, InternalNotification $notification): JsonResponse
    {
        abort_unless($notification->user_id === $request->user()->id, 404);
        $notification->load('incident:id,folio,folio_corto');
        abort_if($notification->incident_id !== null && $notification->incident === null, 404);

        if ($notification->read_at === null) {
            $notification->update(['read_at' => now('UTC')]);
        }

        return response()->json(['data' => $this->present($notification)]);
    }

    public function readAll(Request $request): JsonResponse
    {
        $this->visibleNotifications($request->user())->whereNull('read_at')
            ->update(['read_at' => now('UTC')]);

        return response()->json(['ok' => true]);
    }

    /** @return Builder<InternalNotification> */
    private function visibleNotifications(User $user): Builder
    {
        return InternalNotification::query()->where('user_id', $user->id)
            ->where(fn (Builder $query): Builder => $query->whereNull('incident_id')->orWhereHas('incident'));
    }

    /** @return array<string, mixed> */
    private function present(InternalNotification $notification): array
    {
        return [
            'id' => $notification->id,
            'folio' => $notification->incident?->folio,
            'folioCorto' => $notification->incident?->folio_corto,
            'titulo' => $notification->title,
            'descripcion' => $notification->body,
            'fecha' => $notification->created_at->setTimezone(config('sigei.display_timezone'))->format('d/m/Y H:i'),
            'leida' => $notification->read_at !== null,
            ...($notification->kind === 'push.test' ? ['prueba' => true] : []),
        ];
    }
}
