<?php

namespace Tests\Feature;

use App\Models\Incident;
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
        $this->artisan('migrate:rollback', ['--step' => 16, '--force' => true])->assertExitCode(0);

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

    public function test_existing_incidents_receive_distinct_short_folios_and_retain_their_original_folios(): void
    {
        $incidents = Incident::factory()->count(2)->create();
        $original = $incidents->map(fn (Incident $incident): array => $incident->only(['id', 'folio', 'description', 'reported_at']));

        $this->artisan('migrate:rollback', ['--step' => 2, '--force' => true])->assertSuccessful();
        $this->assertFalse(Schema::hasColumn('incidents', 'folio_corto'));

        $this->artisan('migrate', ['--force' => true])->assertSuccessful();
        $this->assertTrue(Schema::hasColumn('incidents', 'deleted_at'));
        $aliases = [];

        foreach ($original as $attributes) {
            $incident = Incident::query()->findOrFail($attributes['id']);
            $this->assertEquals($attributes, $incident->only(array_keys($attributes)));
            $this->assertMatchesRegularExpression('/^SIG-(?=.*[0-9])(?=.*[A-Z])[0-9A-HJKMNP-TV-Z]{6}$/', $incident->folio_corto);
            $aliases[] = $incident->folio_corto;
        }

        $this->assertCount(2, array_unique($aliases));
    }
}
