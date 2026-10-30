<?php

namespace App\Http\Resources;

use App\Models\EmergencyType;
use App\Models\Location;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InstitutionSettingsResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'version' => $this->lock_version,
            'institucion' => $this->name,
            'siglas' => $this->abbreviation,
            'notificarNuevos' => $this->notify_new_incidents,
            'notificarAsignaciones' => $this->notify_assignments,
            'notificarSeguimiento' => $this->notify_updates,
            'tipos' => EmergencyType::query()->orderBy('id')->get()->map(fn (EmergencyType $type): array => [
                'id' => $type->id,
                'nombre' => $type->name,
                'activo' => $type->is_active,
            ])->all(),
            'ubicaciones' => Location::query()->orderBy('id')->get()->map(fn (Location $location): array => [
                'id' => $location->id,
                'nombre' => $location->name,
                'activo' => $location->is_active,
                'requiereDetalle' => $location->requires_details,
            ])->all(),
        ];
    }
}
