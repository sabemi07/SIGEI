<?php

namespace App\Http\Requests;

use App\Models\Enums\AccountRole;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('email'))) {
            $this->merge(['email' => Str::lower(trim($this->input('email')))]);
        }
    }

    /** @return array<string, array<int, string|Closure>> */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => [
                'required',
                'string',
                'max:72',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (is_string($value) && strlen($value) > 72) {
                        $fail('La contraseña es demasiado larga.');
                    }
                },
            ],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'email.required' => 'Escribe tu correo electrónico.',
            'email.string' => 'Escribe un correo electrónico válido.',
            'email.email' => 'Escribe un correo electrónico válido.',
            'email.max' => 'El correo electrónico es demasiado largo.',
            'password.required' => 'Escribe tu contraseña.',
            'password.string' => 'Escribe una contraseña válida.',
            'password.max' => 'La contraseña es demasiado larga.',
        ];
    }

    public function authenticate(): void
    {
        $key = $this->throttleKey();

        if (RateLimiter::tooManyAttempts($key, 5)) {
            $seconds = RateLimiter::availableIn($key);
            $message = 'Demasiados intentos. Inténtalo de nuevo en '.$seconds.' segundos.';

            throw new HttpResponseException(response()->json([
                'message' => $message,
                'errors' => ['email' => [$message]],
            ], 429)->header('Retry-After', (string) $seconds));
        }

        if (! Auth::attempt([
            'email' => $this->input('email'),
            'password' => $this->input('password'),
            'is_active' => true,
            'role' => array_column(AccountRole::cases(), 'value'),
        ])) {
            RateLimiter::hit($key, 60);

            throw ValidationException::withMessages([
                'email' => 'No fue posible iniciar sesión con los datos proporcionados.',
            ]);
        }

        RateLimiter::clear($key);
    }

    private function throttleKey(): string
    {
        return 'login:'.hash('sha256', $this->input('email').'|'.$this->ip());
    }
}
