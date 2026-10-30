<?php

namespace App\Http\Controllers;

use App\Models\EmergencyType;
use App\Models\InstitutionSetting;
use App\Models\Location;

class PublicSettingsController extends Controller
{
    /** @return array<string, mixed> */
    public function __invoke(): array
    {
        $settings = InstitutionSetting::query()->find('institution');

        return [
            'institucion' => $settings?->name ?? 'Institución por configurar',
            'siglas' => $settings?->abbreviation ?? 'SIGEI',
            'tipos' => EmergencyType::query()->where('is_active', true)->orderBy('id')->get()
                ->map(fn (EmergencyType $type): array => ['id' => $type->id, 'nombre' => $type->name])->all(),
            'ubicaciones' => Location::query()->where('is_active', true)->orderBy('id')->get()
                ->map(fn (Location $location): array => [
                    'id' => $location->id,
                    'nombre' => $location->name,
                    'requiereDetalle' => $location->requires_details,
                ])->all(),
        ];
    }
}
