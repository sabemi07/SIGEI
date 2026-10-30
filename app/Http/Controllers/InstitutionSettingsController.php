<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveInstitutionSettingsRequest;
use App\Http\Resources\InstitutionSettingsResource;
use App\Models\AuditLog;
use App\Models\EmergencyType;
use App\Models\Enums\AccountRole;
use App\Models\InstitutionSetting;
use App\Models\Location;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class InstitutionSettingsController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', InstitutionSetting::class);

        return DB::transaction(function () use ($request): JsonResponse {
            $settings = InstitutionSetting::query()->findOrFail('institution');

            return response()->json(['data' => (new InstitutionSettingsResource($settings))->resolve($request)]);
        });
    }

    public function update(SaveInstitutionSettingsRequest $request): JsonResponse
    {
        return DB::transaction(function () use ($request): JsonResponse {
            $settings = InstitutionSetting::query()->whereKey('institution')->lockForUpdate()->first();
            abort_if($settings === null, 503, 'Inicializa la configuración antes de editarla.');
            $actor = User::query()->lockForUpdate()->find($request->user()?->getAuthIdentifier());
            abort_unless($actor?->is_active && $actor->role === AccountRole::Administrator
                && $request->session()->get('account_auth_version') === $actor->auth_version, 403);
            abort_unless($request->integer('version') === $settings->lock_version, 409,
                'La configuración cambió mientras la editabas. Conserva tus cambios y recarga la versión guardada antes de volver a editar.');

            $before = (new InstitutionSettingsResource($settings))->resolve($request);
            $this->validateCatalog($request->validated('tipos'), EmergencyType::class, 'tipos');
            $this->validateCatalog($request->validated('ubicaciones'), Location::class, 'ubicaciones');

            $settings->name = $request->validated('institucion');
            $settings->abbreviation = $request->validated('siglas');
            $settings->notify_new_incidents = $request->boolean('notificarNuevos');
            $settings->notify_assignments = $request->boolean('notificarAsignaciones');
            $settings->notify_updates = $request->boolean('notificarSeguimiento');
            $catalogChanged = $this->saveCatalog($request->validated('tipos'), EmergencyType::class);
            $catalogChanged = $this->saveCatalog($request->validated('ubicaciones'), Location::class) || $catalogChanged;

            if ($settings->isDirty() || $catalogChanged) {
                $settings->updated_by = $actor->id;
                $settings->lock_version++;
                $settings->save();
                AuditLog::query()->create([
                    'actor_id' => $actor->id,
                    'action' => 'settings.updated',
                    'subject_type' => 'institution_settings',
                    'metadata' => [
                        'before' => $before,
                        'after' => (new InstitutionSettingsResource($settings))->resolve($request),
                    ],
                ]);
            }

            return response()->json(['data' => (new InstitutionSettingsResource($settings))->resolve($request)]);
        }, 3);
    }

    /**
     * @param  list<array{id?: ?int, nombre: string, activo: bool, requiereDetalle?: bool}>  $items
     * @param  class-string<EmergencyType>|class-string<Location>  $model
     */
    private function validateCatalog(array $items, string $model, string $field): void
    {
        if (! collect($items)->contains(fn (array $item): bool => (bool) $item['activo'])) {
            throw ValidationException::withMessages([$field => 'Conserva al menos una opción activa en cada catálogo.']);
        }

        $storedIds = $model::query()->orderBy('id')->pluck('id')->all();
        $submittedIds = array_map('intval', array_filter(array_column($items, 'id'), fn (mixed $id): bool => $id !== null));
        sort($submittedIds);

        if ($storedIds !== $submittedIds) {
            throw ValidationException::withMessages([$field => 'El catálogo está incompleto o contiene identificadores desconocidos. Recarga la configuración; desactiva las opciones en lugar de eliminarlas.']);
        }
    }

    /**
     * @param  list<array{id?: ?int, nombre: string, activo: bool, requiereDetalle?: bool}>  $items
     * @param  class-string<EmergencyType>|class-string<Location>  $model
     */
    private function saveCatalog(array $items, string $model): bool
    {
        $changed = false;

        foreach ($items as $index => $item) {
            $entry = isset($item['id']) ? $model::query()->findOrFail($item['id']) : new $model;
            $duplicate = $model::query()->where('name', $item['nombre']);

            if ($entry->exists) {
                $duplicate->whereKeyNot($entry->getKey());
            }

            if ($duplicate->exists()) {
                $field = $model === Location::class ? 'ubicaciones' : 'tipos';
                throw ValidationException::withMessages(["{$field}.{$index}.nombre" => 'Ese nombre ya existe, incluso entre las opciones inactivas. Edita o reactiva la opción existente.']);
            }

            $entry->name = $item['nombre'];
            $entry->is_active = (bool) $item['activo'];

            if ($entry instanceof Location) {
                $entry->requires_details = (bool) $item['requiereDetalle'];
            }

            if (! $entry->exists || $entry->isDirty()) {
                $entry->save();
                $changed = true;
            }
        }

        return $changed;
    }
}
