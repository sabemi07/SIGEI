<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\Enums\AccountRole;
use App\Models\InstitutionSetting;
use App\Models\User;
use Closure;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class CreateAdministrator extends Command
{
    protected $signature = 'sigei:create-admin';

    protected $description = 'Crea de forma interactiva el primer Administrador activo de SIGEI';

    public function handle(): int
    {
        if (! $this->input->isInteractive()) {
            $this->error('Ejecuta este comando en una terminal interactiva para introducir la contraseña de forma privada.');

            return self::FAILURE;
        }

        if (! InstitutionSetting::query()->whereKey('institution')->exists()) {
            $this->error('Inicializa la configuración y los catálogos con las migraciones y el seeder antes de crear el Administrador.');

            return self::FAILURE;
        }

        if ($this->activeAdministratorExists()) {
            $this->error('Ya existe un Administrador activo. Crea otras cuentas desde el módulo Usuarios.');

            return self::FAILURE;
        }

        $data = [
            'nombre' => trim((string) $this->ask('Nombre completo')),
            'correo' => mb_strtolower(trim((string) $this->ask('Correo electrónico'))),
            'area' => trim((string) $this->ask('Área o departamento')),
            'password' => $this->secret('Contraseña', false),
            'password_confirmation' => $this->secret('Confirmar contraseña', false),
        ];

        $validator = Validator::make($data, [
            'nombre' => ['required', 'string', 'max:150'],
            'correo' => ['required', 'email', 'max:254', 'unique:users,email'],
            'area' => ['required', 'string', 'max:150'],
            'password' => ['required', 'string', 'min:12', 'max:72', 'confirmed', function (string $attribute, mixed $value, Closure $fail): void {
                if (is_string($value) && strlen($value) > 72) {
                    $fail('La contraseña es demasiado larga.');
                }
            }],
        ], [
            'required' => 'El campo :attribute es obligatorio.',
            'max' => 'El campo :attribute no puede superar :max caracteres.',
            'password.min' => 'La contraseña debe tener al menos 12 caracteres.',
            'password.confirmed' => 'La confirmación de contraseña no coincide.',
            'correo.email' => 'Escribe un correo electrónico válido.',
            'correo.unique' => 'Ese correo ya está registrado.',
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $message) {
                $this->error($message);
            }

            return self::FAILURE;
        }

        $user = DB::transaction(function () use ($data): ?User {
            $setting = InstitutionSetting::query()->whereKey('institution')->lockForUpdate()->first();

            if ($setting === null || $this->activeAdministratorExists()
                || User::query()->where('email', $data['correo'])->exists()) {
                return null;
            }

            $user = new User;
            $user->name = $data['nombre'];
            $user->email = $data['correo'];
            $user->area = $data['area'];
            $user->password = $data['password'];
            $user->role = AccountRole::Administrator;
            $user->is_active = true;
            $user->save();

            AuditLog::query()->create([
                'actor_id' => $user->id,
                'action' => 'user.bootstrap_administrator_created',
                'subject_type' => 'user',
                'subject_id' => $user->id,
                'metadata' => ['role' => AccountRole::Administrator->value, 'is_active' => true],
            ]);

            return $user;
        }, 3);

        if ($user === null) {
            $this->error('No se creó la cuenta: la configuración o las cuentas cambiaron durante el proceso. Revisa el estado y vuelve a intentarlo.');

            return self::FAILURE;
        }

        $this->info('Primer Administrador creado. Ya puedes iniciar sesión con el correo indicado.');

        return self::SUCCESS;
    }

    private function activeAdministratorExists(): bool
    {
        return User::query()->where('role', AccountRole::Administrator)->where('is_active', true)->exists();
    }
}
