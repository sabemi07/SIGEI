<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class IncidentReceiptResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $reportedAt = $this->reported_at->setTimezone(config('sigei.display_timezone'));

        return [
            'folio' => $this->folio,
            'tipo' => $this->type_name,
            'ubicacion' => $this->location_name.(filled($this->location_details) ? ' · '.$this->location_details : ''),
            'fecha' => $reportedAt->format('d/m/Y'),
            'hora' => $reportedAt->format('H:i'),
            'reportadoEn' => $this->reported_at->toISOString(),
            'descripcion' => filled($this->description) ? $this->description : 'Sin descripción adicional',
            'estado' => $this->status->value,
            'prioridad' => $this->priority->value,
        ];
    }
}
