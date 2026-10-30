<?php

namespace App\Http\Controllers;

use App\Http\Middleware\EnsureAccountIsActive;
use App\Http\Requests\CloseIncidentRequest;
use App\Http\Requests\FollowUpIncidentRequest;
use App\Http\Requests\ManageIncidentRequest;
use App\Http\Requests\StoreIncidentRequest;
use App\Http\Resources\IncidentReceiptResource;
use App\Http\Resources\IncidentResource;
use App\Models\AuditLog;
use App\Models\EmergencyType;
use App\Models\Enums\AccountRole;
use App\Models\Enums\IncidentPriority;
use App\Models\Enums\IncidentStatus;
use App\Models\Incident;
use App\Models\IncidentAssignment;
use App\Models\IncidentUpdate;
use App\Models\InstitutionSetting;
use App\Models\InternalNotification;
use App\Models\Location;
use App\Models\User;
use Closure;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class IncidentWriteController extends Controller
{
    public function store(StoreIncidentRequest $request): JsonResponse
    {
        $this->actor($request, true);
        $incident = DB::transaction(function () use ($request): Incident {
            $settings = $this->lockInstitution();
            $actor = $this->actor($request, true);
            $scope = $actor === null ? 'guest:'.$request->session()->getId() : 'user:'.$actor->id.':'.$actor->auth_version;
            $data = [
                'emergency_type_id' => $request->integer('tipo_id'),
                'location_id' => $request->integer('ubicacion_id'),
                'location_details' => $request->validated('otro_lugar'),
                'description' => $request->validated('descripcion'),
            ];
            $keyHash = $this->digest('submission:'.$request->validated('solicitud_id'));
            $scopeHash = $this->digest($scope);
            $payloadHash = hash('sha256', json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
            $existing = Incident::query()->where('submission_key_hash', $keyHash)->first();

            if ($existing !== null) {
                abort_unless(hash_equals($existing->submission_scope_hash, $scopeHash)
                    && hash_equals($existing->submission_payload_hash, $payloadHash), 409,
                    'Esta solicitud ya fue utilizada. Vuelve a abrir el formulario para registrar otro reporte.');

                return $existing;
            }

            $type = EmergencyType::query()->whereKey($data['emergency_type_id'])->lockForUpdate()->first();
            $location = Location::query()->whereKey($data['location_id'])->lockForUpdate()->first();

            if (! $type?->is_active) {
                throw ValidationException::withMessages(['tipo_id' => 'Selecciona un tipo de emergencia disponible.']);
            }

            if (! $location?->is_active) {
                throw ValidationException::withMessages(['ubicacion_id' => 'Selecciona una ubicación disponible.']);
            }

            if ($location->requires_details && ! filled($data['location_details'])) {
                throw ValidationException::withMessages(['otro_lugar' => 'Especifica dónde ocurre la emergencia.']);
            }

            if (! $location->requires_details) {
                $data['location_details'] = null;
            }

            $incident = new Incident($data + ['reporter_user_id' => $actor?->id]);
            $incident->submission_key_hash = $keyHash;
            $incident->submission_scope_hash = $scopeHash;
            $incident->submission_payload_hash = $payloadHash;
            $incident->save();
            $this->audit($incident, $actor, 'incident.created', ['status' => $incident->status->value, 'priority' => $incident->priority->value]);

            if ($settings->notify_new_incidents) {
                $recipients = User::query()->where('role', AccountRole::Administrator)->where('is_active', true)->pluck('id')->all();
                $this->notify($incident, $recipients, 'incident.created', 'Nuevo incidente registrado', 'Se recibió un reporte pendiente de revisión.');
            }

            return $incident;
        }, 3);

        return (new IncidentReceiptResource($incident))->response()->setStatusCode(201);
    }

    public function manage(ManageIncidentRequest $request, Incident $incident): IncidentResource
    {
        return $this->mutate($request, $incident, AccountRole::Administrator,
            function (Incident $incident, User $actor, ?IncidentAssignment $assignment, InstitutionSetting $settings) use ($request): void {
                $responsibleId = $request->validated('responsable_id');
                $responsibleId = $responsibleId === null ? null : (int) $responsibleId;
                $priority = IncidentPriority::from($request->validated('prioridad'));
                $note = $request->validated('observacion');
                $assignmentChanged = $assignment?->assigned_to !== $responsibleId;

                if ($responsibleId !== null && ! User::query()->whereKey($responsibleId)->where('role', AccountRole::Responsible)->where('is_active', true)->exists()) {
                    throw ValidationException::withMessages(['responsable_id' => 'Selecciona una cuenta de Responsable activa.']);
                }

                if ($incident->status === IncidentStatus::Attended && $assignmentChanged) {
                    throw ValidationException::withMessages(['responsable_id' => 'El caso atendido está pendiente de cierre; conserva su asignación.']);
                }

                if (in_array($incident->status, [IncidentStatus::InProgress, IncidentStatus::Attended], true)) {
                    if ($responsibleId === null) {
                        throw ValidationException::withMessages(['responsable_id' => 'Conserva un Responsable para este estado del incidente.']);
                    }

                    if ($priority === IncidentPriority::Unevaluated) {
                        throw ValidationException::withMessages(['prioridad' => 'Define la prioridad antes de continuar con la atención.']);
                    }
                }

                $before = ['assigned_to' => $assignment?->assigned_to, 'priority' => $incident->priority->value];
                $priorityChanged = $incident->priority !== $priority;

                if (! $assignmentChanged && ! $priorityChanged && ! filled($note)) {
                    return;
                }

                if ($assignmentChanged) {
                    if ($assignment !== null) {
                        $assignment->ended_at = now('UTC');
                        $assignment->save();
                    }

                    if ($responsibleId !== null) {
                        IncidentAssignment::query()->create([
                            'incident_id' => $incident->id,
                            'assigned_to' => $responsibleId,
                            'assigned_by' => $actor->id,
                            'assigned_at' => now('UTC'),
                        ]);
                    }
                }

                $incident->priority = $priority;
                $this->saveVersion($incident);

                if (filled($note)) {
                    $this->addUpdate($incident, $actor, $note);
                }

                $this->audit($incident, $actor, 'incident.managed', [
                    'before' => $before,
                    'after' => ['assigned_to' => $responsibleId, 'priority' => $priority->value],
                    'note_recorded' => filled($note),
                ]);

                if ($assignmentChanged) {
                    if ($responsibleId !== null && $settings->notify_assignments) {
                        $this->notify($incident, [$responsibleId], 'incident.assigned', 'Incidente asignado', 'Tienes un incidente asignado pendiente de confirmar recepción.');
                    }
                } else {
                    $this->notifyUpdate($incident, $actor, $settings);
                }
            });
    }

    public function receive(Request $request, Incident $incident): IncidentResource
    {
        $request->validate([
            'version' => ['required', 'integer', 'min:0'],
            'received_by' => ['prohibited'],
            'received_at' => ['prohibited'],
            'estado' => ['prohibited'],
        ], ['version.required' => 'La versión del incidente es obligatoria.']);

        return $this->mutate($request, $incident, AccountRole::Responsible,
            function (Incident $incident, User $actor, ?IncidentAssignment $assignment, InstitutionSetting $settings): void {
                abort_if($incident->status === IncidentStatus::Attended, 409, 'El incidente atendido está pendiente de cierre administrativo.');
                abort_if($assignment->received_at !== null, 409, 'La recepción de esta asignación ya fue confirmada.');
                $assignment->received_at = now('UTC');
                $assignment->received_by = $actor->id;
                $assignment->save();

                if ($incident->status === IncidentStatus::Pending) {
                    $incident->status = IncidentStatus::Received;
                }

                $this->saveVersion($incident);
                $this->addUpdate($incident, $actor, 'El Responsable confirmó la recepción del incidente.');
                $this->audit($incident, $actor, 'incident.received', ['assignment_id' => $assignment->id, 'status' => $incident->status->value]);
                $this->notifyUpdate($incident, $actor, $settings);
            });
    }

    public function followUp(FollowUpIncidentRequest $request, Incident $incident): IncidentResource
    {
        return $this->mutate($request, $incident, AccountRole::Responsible,
            function (Incident $incident, User $actor, ?IncidentAssignment $assignment, InstitutionSetting $settings) use ($request): void {
                abort_if($assignment->received_at === null, 409, 'Primero confirma la recepción de tu asignación.');
                $next = IncidentStatus::from($request->validated('estado'));
                $allowed = match ($incident->status) {
                    IncidentStatus::Received => [IncidentStatus::Received, IncidentStatus::InProgress],
                    IncidentStatus::InProgress => [IncidentStatus::InProgress, IncidentStatus::Attended],
                    default => [],
                };

                if (! in_array($next, $allowed, true)) {
                    throw ValidationException::withMessages(['estado' => 'El cambio de estado no está permitido.']);
                }

                if (in_array($next, [IncidentStatus::InProgress, IncidentStatus::Attended], true) && $incident->priority === IncidentPriority::Unevaluated) {
                    throw ValidationException::withMessages(['estado' => 'El Administrador debe definir la prioridad antes de continuar con la atención.']);
                }

                $before = $incident->status;
                $incident->status = $next;
                $this->saveVersion($incident);
                $this->addUpdate($incident, $actor, $request->validated('observacion'));
                $this->audit($incident, $actor, 'incident.updated', ['before_status' => $before->value, 'after_status' => $next->value]);
                $this->notifyUpdate($incident, $actor, $settings);
            });
    }

    public function close(CloseIncidentRequest $request, Incident $incident): IncidentResource
    {
        return $this->mutate($request, $incident, AccountRole::Administrator,
            function (Incident $incident, User $actor, ?IncidentAssignment $assignment, InstitutionSetting $settings) use ($request): void {
                abort_unless($incident->status === IncidentStatus::Attended, 409, 'Solo se puede cerrar un incidente cuya atención ya fue registrada.');
                $incident->status = IncidentStatus::Closed;
                $incident->closed_at = now('UTC');
                $incident->closed_by = $actor->id;
                $this->saveVersion($incident);
                $this->addUpdate($incident, $actor, $request->validated('observacion'));
                $this->audit($incident, $actor, 'incident.closed', ['closed_by' => $actor->id]);
                $this->notifyUpdate($incident, $actor, $settings);
            });
    }

    private function mutate(Request $request, Incident $boundIncident, AccountRole $role, Closure $operation): IncidentResource
    {
        $incident = DB::transaction(function () use ($request, $boundIncident, $role, $operation): Incident {
            $settings = $this->lockInstitution();
            $actor = $this->actor($request, false);
            abort_unless($actor->role === $role, 403);
            $incident = Incident::query()->lockForUpdate()->findOrFail($boundIncident->id);
            $assignment = $incident->assignments()->whereNull('ended_at')->lockForUpdate()->first();

            if ($role === AccountRole::Responsible) {
                abort_unless($assignment !== null && $assignment->assigned_to === $actor->id, 403);
            }

            abort_unless($incident->lock_version === $request->integer('version'), 409, 'El incidente cambió. Actualízalo y revisa los datos antes de guardar otra vez.');
            abort_if($incident->status === IncidentStatus::Closed, 409, 'El incidente está cerrado y solo se puede consultar.');
            $operation($incident, $actor, $assignment, $settings);

            return $incident->fresh();
        }, 3);

        return new IncidentResource($incident);
    }

    private function actor(Request $request, bool $allowGuest): ?User
    {
        $hadAccount = $request->user() !== null || $request->session()->has(Auth::guard('web')->getName());
        $actor = EnsureAccountIsActive::activeUser($request);

        if ($actor === null && ($hadAccount || ! $allowGuest)) {
            throw new HttpResponseException(response()->json([
                'message' => 'Tu sesión terminó. Revisa tu cuenta antes de continuar.',
                'csrf_token' => csrf_token(),
            ], 401));
        }

        return $actor;
    }

    private function lockInstitution(): InstitutionSetting
    {
        $settings = InstitutionSetting::query()->whereKey('institution')->lockForUpdate()->first();
        abort_if($settings === null, 503, 'La configuración institucional todavía no está preparada.');

        return $settings;
    }

    private function digest(string $value): string
    {
        return hash_hmac('sha256', $value, (string) config('app.key'));
    }

    private function saveVersion(Incident $incident): void
    {
        $incident->lock_version++;
        $incident->save();
    }

    private function addUpdate(Incident $incident, User $actor, string $note): void
    {
        IncidentUpdate::query()->create([
            'incident_id' => $incident->id,
            'author_id' => $actor->id,
            'status' => $incident->status,
            'internal_note' => $note,
        ]);
    }

    /** @param array<string, mixed> $metadata */
    private function audit(Incident $incident, ?User $actor, string $action, array $metadata): void
    {
        AuditLog::query()->create([
            'actor_id' => $actor?->id,
            'incident_id' => $incident->id,
            'action' => $action,
            'subject_type' => 'incident',
            'subject_id' => $incident->id,
            'metadata' => $metadata,
        ]);
    }

    private function notifyUpdate(Incident $incident, User $actor, InstitutionSetting $settings): void
    {
        if (! $settings->notify_updates) {
            return;
        }

        $current = $incident->assignments()->whereNull('ended_at')->value('assigned_to');
        $recipients = User::query()->where('is_active', true)->where(function ($query) use ($incident, $current): void {
            $query->where('role', AccountRole::Administrator)->orWhereIn('id', array_filter([$incident->reporter_user_id, $current]));
        })->whereKeyNot($actor->id)->pluck('id')->all();
        $this->notify($incident, $recipients, 'incident.updated', 'Actualización de incidente', 'Se registró un avance en un incidente al que tienes acceso.');
    }

    /** @param array<int, int> $recipients */
    private function notify(Incident $incident, array $recipients, string $kind, string $title, string $body): void
    {
        foreach (array_unique($recipients) as $recipient) {
            InternalNotification::query()->create([
                'user_id' => $recipient,
                'incident_id' => $incident->id,
                'kind' => $kind,
                'title' => $title,
                'body' => $body,
            ]);
        }
    }
}
