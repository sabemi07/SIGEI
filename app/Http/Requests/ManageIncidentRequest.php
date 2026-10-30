<?php

namespace App\Http\Requests;

use App\Models\Enums\AccountRole;
use App\Models\Enums\IncidentPriority;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ManageIncidentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->is_active && $this->user()->role === AccountRole::Administrator;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $rules = [
            'version' => ['required', 'integer', 'min:0'],
            'responsable_id' => ['present', 'nullable', 'integer', Rule::exists('users', 'id')->where('role', AccountRole::Responsible->value)->where('is_active', true)],
            'prioridad' => ['required', Rule::enum(IncidentPriority::class)],
            'observacion' => ['nullable', 'string', 'max:2000'],
        ];

        foreach (['estado', 'status', 'folio', 'reportante_id', 'reporter_user_id', 'tipo_id', 'ubicacion_id', 'descripcion', 'lock_version', 'closed_at', 'closed_by'] as $field) {
            $rules[$field] = ['prohibited'];
        }

        return $rules;
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'required' => 'El campo :attribute es obligatorio.',
            'present' => 'Selecciona un Responsable o deja el caso sin asignar.',
            'integer' => 'El campo :attribute no es válido.',
            'min' => 'El campo :attribute no es válido.',
            'responsable_id.exists' => 'Selecciona una cuenta de Responsable activa.',
            'prioridad.enum' => 'Selecciona una prioridad válida.',
            'string' => 'El campo :attribute debe ser texto.',
            'max' => 'El campo :attribute no puede superar :max caracteres.',
            'prohibited' => 'El campo :attribute no está permitido.',
        ];
    }
}
