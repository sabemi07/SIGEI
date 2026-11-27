<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Enums\AccountRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CreateAdministratorTest extends TestCase
{
    use RefreshDatabase;

    public function test_first_administrator_is_created_interactively_with_a_hashed_password(): void
    {
        $this->seed();
        $this->artisan('sigei:create-admin')
            ->expectsQuestion('Nombre completo', 'Administradora de pruebas')
            ->expectsQuestion('Correo electrónico', '  ADMIN@example.test  ')
            ->expectsQuestion('Área o departamento', 'Administración')
            ->expectsQuestion('Contraseña', 'clave-segura-para-pruebas')
            ->expectsQuestion('Confirmar contraseña', 'clave-segura-para-pruebas')
            ->expectsOutput('Primer Administrador creado. Ya puedes iniciar sesión con el correo indicado.')
            ->assertSuccessful();

        $user = User::query()->sole();
        $this->assertSame('admin@example.test', $user->email);
        $this->assertSame(AccountRole::Administrator, $user->role);
        $this->assertTrue($user->is_active);
        $this->assertTrue(Hash::check('clave-segura-para-pruebas', $user->password));
        $this->assertSame($user->id, AuditLog::query()->sole()->actor_id);
        $this->assertStringNotContainsString('password', json_encode(AuditLog::query()->sole()->metadata));
    }

    public function test_command_refuses_noninteractive_execution_without_creating_an_account(): void
    {
        $this->seed();
        $this->artisan('sigei:create-admin', ['--no-interaction' => true])
            ->expectsOutput('Ejecuta este comando en una terminal interactiva para introducir la contraseña de forma privada.')
            ->assertFailed();

        $this->assertDatabaseCount('users', 0);
    }

    public function test_command_refuses_if_an_active_administrator_already_exists(): void
    {
        $this->seed();
        User::factory()->administrator()->active()->create();
        $this->artisan('sigei:create-admin')
            ->expectsOutput('Ya existe un Administrador activo. Crea otras cuentas desde el módulo Usuarios.')
            ->assertFailed();

        $this->assertDatabaseCount('users', 1);
    }

    public function test_command_refuses_without_initial_configuration(): void
    {
        $this->artisan('sigei:create-admin')
            ->expectsOutput('Inicializa la configuración y los catálogos con las migraciones y el seeder antes de crear el Administrador.')
            ->assertFailed();

        $this->assertDatabaseCount('users', 0);
    }

    public function test_invalid_password_does_not_create_an_administrator(): void
    {
        $this->seed();
        $this->artisan('sigei:create-admin')
            ->expectsQuestion('Nombre completo', 'Administradora de pruebas')
            ->expectsQuestion('Correo electrónico', 'admin@example.test')
            ->expectsQuestion('Área o departamento', 'Administración')
            ->expectsQuestion('Contraseña', 'corta')
            ->expectsQuestion('Confirmar contraseña', 'corta')
            ->expectsOutput('La contraseña debe tener al menos 12 caracteres.')
            ->assertFailed();

        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_password_exceeding_bcrypt_byte_limit_is_rejected(): void
    {
        $this->seed();
        $password = str_repeat('á', 37);
        $this->artisan('sigei:create-admin')
            ->expectsQuestion('Nombre completo', 'Administradora de pruebas')
            ->expectsQuestion('Correo electrónico', 'admin@example.test')
            ->expectsQuestion('Área o departamento', 'Administración')
            ->expectsQuestion('Contraseña', $password)
            ->expectsQuestion('Confirmar contraseña', $password)
            ->expectsOutput('La contraseña es demasiado larga.')
            ->assertFailed();

        $this->assertDatabaseCount('users', 0);
    }
}
