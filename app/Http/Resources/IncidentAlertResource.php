<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Support\Arr;

class IncidentAlertResource extends IncidentReceiptResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return Arr::only(parent::toArray($request), ['folio', 'folioCorto', 'tipo', 'ubicacion', 'fecha', 'hora'])
            + ['soloResumen' => true];
    }
}
