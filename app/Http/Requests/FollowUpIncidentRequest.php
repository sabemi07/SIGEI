<?php

namespace App\Http\Requests;

use App\Models\Enums\AccountRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FollowUpIncidentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->is_active && $this->user()->role === AccountRole::Responsible;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'version' => ['required', 'integer', 'min:0'],
            'estado' => ['required', Rule::in(['Recibida', 'En atención', 'Atendida'])],
            'observacion' => ['required', 'string', 'max:1000'],
            'responsable_id' => ['prohibited'],
            'prioridad' => ['prohibited'],
            'reportante_id' => ['prohibited'],
            'received_by' => ['prohibited'],
            'received_at' => ['prohibited'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'required' => 'El campo :attribute es obligatorio.',
            'integer' => 'La versión del incidente no es válida.',
            'min' => 'La versión del incidente no es válida.',
            'estado.in' => 'Selecciona un estado válido.',
            'string' => 'El campo :attribute debe ser texto.',
            'max' => 'El campo :attribute no puede superar :max caracteres.',
            'prohibited' => 'El campo :attribute no está permitido.',
        ];
    }
}
