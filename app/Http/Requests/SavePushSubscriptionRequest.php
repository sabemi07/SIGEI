<?php

namespace App\Http\Requests;

use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class SavePushSubscriptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->is_active ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'cuenta_esperada' => ['required', 'integer', 'min:1'],
            'endpoint' => ['required', 'string', 'max:4096', 'url:https', function (string $attribute, mixed $value, Closure $fail): void {
                $url = is_string($value) ? parse_url($value) : false;
                $host = mb_strtolower($url['host'] ?? '');
                $allowed = collect(config('sigei.push.endpoint_hosts'))->contains(fn (string $pattern): bool => str_starts_with($pattern, '*.') ? Str::endsWith($host, substr($pattern, 1)) : $host === $pattern);

                if (! $allowed || isset($url['user']) || isset($url['pass']) || isset($url['fragment']) || ($url['port'] ?? 443) !== 443) {
                    $fail('El servicio Push de este navegador no está admitido o su dirección no es válida.');
                }
            }],
            'keys' => ['required', 'array:p256dh,auth'],
            'keys.p256dh' => ['required', 'string', function (string $attribute, mixed $value, Closure $fail): void {
                $decoded = $this->decodeKey($value);

                if ($decoded === null || strlen($decoded) !== 65 || $decoded[0] !== "\x04") {
                    $fail('La clave pública del dispositivo no es válida.');
                }
            }],
            'keys.auth' => ['required', 'string', function (string $attribute, mixed $value, Closure $fail): void {
                if (strlen($this->decodeKey($value) ?? '') !== 16) {
                    $fail('La clave de autenticación del dispositivo no es válida.');
                }
            }],
            'expirationTime' => ['nullable', 'integer', 'gt:'.now('UTC')->getTimestampMs(), 'max:253402300799000'],
            'contentEncoding' => ['required', 'in:aes128gcm'],
            'user_id' => ['prohibited'],
            'device_id' => ['prohibited'],
            'binding_id' => ['prohibited'],
            'session_hash' => ['prohibited'],
            'auth_version' => ['prohibited'],
        ];
    }

    private function decodeKey(mixed $value): ?string
    {
        if (! is_string($value) || preg_match('/^[A-Za-z0-9_-]+={0,2}$/D', $value) !== 1 || strlen($value) > 100) {
            return null;
        }

        $decoded = base64_decode(strtr($value, '-_', '+/'), true);

        return $decoded === false ? null : $decoded;
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'required' => 'Faltan datos de la suscripción del navegador.',
            'prohibited' => 'La cuenta y el dispositivo se determinan en el servidor.',
            'url' => 'La dirección Push debe usar HTTPS.',
            'expirationTime.gt' => 'La suscripción del navegador ya caducó.',
            'expirationTime.integer' => 'La caducidad de la suscripción no es válida.',
            'max' => 'Los datos de la suscripción superan el límite permitido.',
            'in' => 'El formato de la suscripción no está admitido.',
            'array' => 'Los datos de las claves de suscripción no son válidos.',
        ];
    }
}
