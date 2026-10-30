<?php

namespace App\Http\Requests;

use App\Models\Enums\AccountRole;
use Illuminate\Foundation\Http\FormRequest;

class CloseIncidentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->is_active && $this->user()->role === AccountRole::Administrator;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'version' => ['required', 'integer', 'min:0'],
            'observacion' => ['required', 'string', 'max:2000'],
            'estado' => ['prohibited'],
            'closed_at' => ['prohibited'],
            'closed_by' => ['prohibited'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'required' => 'El campo :attribute es obligatorio.',
            'integer' => 'La versión del incidente no es válida.',
            'min' => 'La versión del incidente no es válida.',
            'string' => 'El campo :attribute debe ser texto.',
            'max' => 'El campo :attribute no puede superar :max caracteres.',
            'prohibited' => 'El campo :attribute no está permitido.',
        ];
    }
}
