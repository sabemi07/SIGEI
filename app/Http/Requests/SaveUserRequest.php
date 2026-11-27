<?php

namespace App\Http\Requests;

use App\Models\Enums\AccountRole;
use App\Models\User;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        $target = $this->route('user');

        return $target instanceof User
            ? ($this->user()?->can('update', $target) ?? false)
            : ($this->user()?->can('create', User::class) ?? false);
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('correo'))) {
            $this->merge(['correo' => mb_strtolower(trim($this->input('correo')))]);
        }
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $target = $this->route('user');
        $isUpdate = $target instanceof User;

        return [
            'nombre' => ['required', 'string', 'max:150'],
            'correo' => ['required', 'string', 'email', 'max:254', Rule::unique('users', 'email')->ignore($isUpdate ? $target->getKey() : null)],
            'area' => ['required', 'string', 'max:150'],
            'rol' => ['required', Rule::enum(AccountRole::class)],
            'activo' => ['required', 'boolean'],
            'password' => [$isUpdate ? 'nullable' : 'required', 'string', 'min:12', 'max:72', 'confirmed', function (string $attribute, mixed $value, Closure $fail): void {
                if (is_string($value) && strlen($value) > 72) {
                    $fail('La contraseña es demasiado larga.');
                }
            }],
            'password_confirmation' => ['nullable', 'string', 'max:72'],
            'id' => ['prohibited'],
            'name' => ['prohibited'],
            'email' => ['prohibited'],
            'role' => ['prohibited'],
            'is_active' => ['prohibited'],
            'remember_token' => ['prohibited'],
            'email_verified_at' => ['prohibited'],
            'auth_version' => ['prohibited'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'required' => 'El campo :attribute es obligatorio.',
            'string' => 'El campo :attribute debe ser texto.',
            'max' => 'El campo :attribute no puede superar :max caracteres.',
            'min' => 'La contraseña debe tener al menos :min caracteres.',
            'correo.email' => 'Escribe un correo electrónico válido.',
            'correo.unique' => 'Ese correo ya está registrado.',
            'rol.enum' => 'Selecciona Administrador o Responsable.',
            'activo.boolean' => 'El estado de la cuenta no es válido.',
            'password.confirmed' => 'La confirmación de contraseña no coincide.',
            'prohibited' => 'El campo :attribute no está permitido.',
        ];
    }
}
