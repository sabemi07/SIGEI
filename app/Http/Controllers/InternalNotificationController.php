<?php

namespace App\Http\Controllers;

use App\Models\InternalNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InternalNotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $input = $request->validate([
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);
        $notifications = InternalNotification::query()->where('user_id', $request->user()->id)
            ->with('incident:id,folio')->orderByDesc('created_at')->orderByDesc('id')
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

    public function read(Request $request, InternalNotification $notification): JsonResponse
    {
        abort_unless($notification->user_id === $request->user()->id, 404);

        if ($notification->read_at === null) {
            $notification->update(['read_at' => now('UTC')]);
        }

        return response()->json(['data' => $this->present($notification->load('incident:id,folio'))]);
    }

    public function readAll(Request $request): JsonResponse
    {
        InternalNotification::query()->where('user_id', $request->user()->id)->whereNull('read_at')
            ->update(['read_at' => now('UTC')]);

        return response()->json(['ok' => true]);
    }

    /** @return array<string, mixed> */
    private function present(InternalNotification $notification): array
    {
        return [
            'id' => $notification->id,
            'folio' => $notification->incident?->folio,
            'titulo' => $notification->title,
            'descripcion' => $notification->body,
            'fecha' => $notification->created_at->setTimezone(config('sigei.display_timezone'))->format('d/m/Y H:i'),
            'leida' => $notification->read_at !== null,
            ...($notification->kind === 'push.test' ? ['prueba' => true] : []),
        ];
    }
}
