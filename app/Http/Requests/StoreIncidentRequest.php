<?php

namespace App\Http\Requests;

use App\Models\Location;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreIncidentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('solicitud_id'))) {
            $this->merge(['solicitud_id' => strtolower($this->input('solicitud_id'))]);
        }
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $location = is_scalar($this->input('ubicacion_id')) ? Location::query()->find($this->input('ubicacion_id')) : null;
        $rules = [
            'tipo_id' => ['required', 'integer', 'min:1', 'exists:emergency_types,id'],
            'ubicacion_id' => ['required', 'integer', 'min:1', 'exists:locations,id'],
            'otro_lugar' => [Rule::requiredIf($location?->requires_details === true), 'nullable', 'string', 'max:255'],
            'descripcion' => ['nullable', 'string', 'max:1000'],
            'solicitud_id' => ['required', 'string', 'uuid'],
            'website' => ['prohibited'],
        ];

        foreach (['id', 'folio', 'estado', 'status', 'prioridad', 'priority', 'responsable', 'responsable_id', 'assigned_to', 'reportante', 'reportante_id', 'reporter_user_id', 'reported_at', 'fecha', 'hora', 'closed_at', 'closed_by', 'lock_version', 'version', 'type_name', 'location_name', 'observaciones', 'seguimientos', 'submission_key_hash', 'submission_scope_hash', 'submission_payload_hash'] as $field) {
            $rules[$field] = ['prohibited'];
        }

        return $rules;
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'required' => 'El campo :attribute es obligatorio.',
            'integer' => 'Selecciona una opción válida para :attribute.',
            'exists' => 'Selecciona una opción disponible para :attribute.',
            'min' => 'Selecciona una opción válida para :attribute.',
            'string' => 'El campo :attribute debe ser texto.',
            'max' => 'El campo :attribute no puede superar :max caracteres.',
            'uuid' => 'La solicitud no es válida. Vuelve a abrir el formulario.',
            'prohibited' => 'El campo :attribute no está permitido.',
        ];
    }
}
