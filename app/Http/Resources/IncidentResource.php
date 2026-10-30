<?php

namespace App\Http\Resources;

use App\Models\Enums\IncidentStatus;
use App\Models\IncidentUpdate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class IncidentResource extends IncidentReceiptResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $this->resource->loadMissing(['currentAssignment.assignee', 'reporter', 'updates.author']);
        $assignment = $this->currentAssignment;
        $reporterOnly = $request->query('scope') === 'mine';
        $internal = ! $reporterOnly && $request->user() !== null
            && Gate::forUser($request->user())->allows('viewInternal', $this->resource);
        $updates = $this->updates->sortBy('id')->values();
        $gate = Gate::forUser($request->user());

        $data = parent::toArray($request) + [
            'reportanteId' => $this->reporter_user_id,
            'reportante' => $this->reporter?->name ?? 'Reporte público sin identificación',
            'responsableId' => $assignment?->assigned_to,
            'responsable' => $assignment?->assignee?->name ?? 'Sin asignar',
            'version' => (int) ($this->lock_version ?? 0),
            'recepcionConfirmadaPor' => $assignment?->received_by,
            'recepcionConfirmadaEn' => $assignment?->received_at?->toISOString(),
            'seguimientos' => $updates->map(function (IncidentUpdate $update) use ($internal): array {
                $data = [
                    'id' => $update->id,
                    'estado' => $update->status->value,
                    'fecha' => $update->created_at->setTimezone(config('sigei.display_timezone'))->format('d/m/Y H:i'),
                ];

                if ($internal) {
                    $data += [
                        'autor' => $update->author?->name ?? 'Cuenta institucional',
                        'autorId' => $update->author_id,
                        'observacion' => $update->internal_note ?? '',
                    ];
                }

                return $data;
            })->all(),
            'permisos' => [
                'gestionar' => ! $reporterOnly && $gate->allows('assign', $this->resource),
                'confirmarRecepcion' => ! $reporterOnly && $gate->allows('confirmReceipt', $this->resource),
                'seguimiento' => ! $reporterOnly && $gate->allows('followUp', $this->resource)
                    && $assignment?->received_at !== null
                    && in_array($this->status, [IncidentStatus::Received, IncidentStatus::InProgress], true),
                'cerrar' => ! $reporterOnly && $gate->allows('close', $this->resource),
                'verInterno' => $internal,
            ],
        ];

        if ($internal) {
            $data['observaciones'] = $updates->last()?->internal_note ?? '';
        }

        return $data;
    }
}
