<?php

namespace App\Http\Requests;

use App\Models\InstitutionSetting;
use Illuminate\Foundation\Http\FormRequest;

class SaveInstitutionSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', InstitutionSetting::class) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'version' => ['required', 'integer', 'min:0'],
            'institucion' => ['required', 'string', 'max:150'],
            'siglas' => ['required', 'string', 'max:20'],
            'notificarNuevos' => ['required', 'boolean'],
            'notificarAsignaciones' => ['required', 'boolean'],
            'notificarSeguimiento' => ['required', 'boolean'],
            'tipos' => ['required', 'array', 'list', 'min:1', 'max:200'],
            'tipos.*' => ['required', 'array:id,nombre,activo'],
            'tipos.*.id' => ['nullable', 'integer', 'min:1', 'distinct'],
            'tipos.*.nombre' => ['required', 'string', 'max:100', 'distinct:ignore_case'],
            'tipos.*.activo' => ['required', 'boolean'],
            'ubicaciones' => ['required', 'array', 'list', 'min:1', 'max:200'],
            'ubicaciones.*' => ['required', 'array:id,nombre,activo,requiereDetalle'],
            'ubicaciones.*.id' => ['nullable', 'integer', 'min:1', 'distinct'],
            'ubicaciones.*.nombre' => ['required', 'string', 'max:150', 'distinct:ignore_case'],
            'ubicaciones.*.activo' => ['required', 'boolean'],
            'ubicaciones.*.requiereDetalle' => ['required', 'boolean'],
            'scope' => ['prohibited'],
            'updated_by' => ['prohibited'],
            'updated_at' => ['prohibited'],
            'lock_version' => ['prohibited'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'required' => 'Completa el campo :attribute.',
            'string' => 'El campo :attribute debe ser texto.',
            'integer' => 'El identificador o versión no es válido.',
            'boolean' => 'Selecciona un estado válido para :attribute.',
            'array' => 'El catálogo contiene datos no permitidos.',
            'list' => 'El catálogo debe ser una lista de opciones.',
            'max' => 'El campo :attribute supera el límite de :max.',
            'min' => 'Conserva al menos una opción en cada catálogo y utiliza identificadores válidos.',
            'distinct' => 'Hay nombres o identificadores repetidos en el catálogo.',
            'prohibited' => 'El campo :attribute no está permitido.',
        ];
    }
}
