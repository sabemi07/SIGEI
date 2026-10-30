<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class FoundationMigrationTest extends TestCase
{
    use DatabaseMigrations;

    public function test_domain_migrations_can_be_reversed_and_reapplied(): void
    {
        $user = User::factory()->create();
        $originalAttributes = $user->only(['id', 'name', 'email', 'password']);

        $this->assertTrue(Schema::hasColumn('incidents', 'lock_version'));
        $this->assertTrue(Schema::hasColumn('incidents', 'submission_key_hash'));
        $this->assertTrue(Schema::hasColumn('institution_settings', 'lock_version'));
        $this->assertTrue(Schema::hasColumn('push_subscriptions', 'binding_id'));
        $this->assertTrue(Schema::hasTable('push_deliveries'));
        $this->artisan('migrate:rollback', ['--step' => 14, '--force' => true])->assertExitCode(0);

        $this->assertTrue(Schema::hasTable('users'));
        $this->assertFalse(Schema::hasColumn('users', 'role'));
        $this->assertFalse(Schema::hasColumn('users', 'auth_version'));

        foreach (['emergency_types', 'locations', 'institution_settings', 'incidents', 'incident_assignments', 'incident_updates', 'audit_logs', 'internal_notifications', 'push_subscriptions'] as $table) {
            $this->assertFalse(Schema::hasTable($table), $table);
        }

        $this->artisan('migrate', ['--force' => true])->assertExitCode(0);
        $this->assertTrue(Schema::hasColumn('users', 'role'));
        $this->assertTrue(Schema::hasColumn('users', 'auth_version'));
        $this->assertTrue(Schema::hasTable('push_subscriptions'));
        $this->assertTrue(Schema::hasColumn('incidents', 'lock_version'));
        $this->assertTrue(Schema::hasColumn('incidents', 'submission_key_hash'));
        $this->assertTrue(Schema::hasColumn('institution_settings', 'lock_version'));
        $this->assertDatabaseHas('users', $originalAttributes);
    }
}
