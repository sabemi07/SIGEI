<?php

namespace Tests\Feature;

use App\Models\IncidentAssignment;
use App\Models\InternalNotification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationAccessTest extends TestCase
{
    use RefreshDatabase;

    private function authenticateAccount(User $user): static
    {
        return $this->actingAs($user)->withSession(['account_auth_version' => $user->refresh()->auth_version]);
    }

    private function notification(User $user, ?int $incidentId = null): InternalNotification
    {
        return InternalNotification::query()->create([
            'user_id' => $user->id,
            'incident_id' => $incidentId,
            'kind' => 'incident.updated',
            'title' => 'Caso actualizado',
            'body' => 'Consulta el caso en SIGEI.',
        ]);
    }

    public function test_notification_endpoints_require_an_active_session(): void
    {
        $notification = $this->notification(User::factory()->active()->create());
        $this->getJson('/api/notifications')->assertUnauthorized();
        $this->postJson('/api/notifications/'.$notification->id.'/read')->assertUnauthorized();
        $this->postJson('/api/notifications/read-all')->assertUnauthorized();
    }

    public function test_a_bell_only_lists_its_accounts_notifications_and_paginates(): void
    {
        $owner = User::factory()->active()->create();
        $this->notification($owner);
        $this->notification($owner);
        $private = $this->notification(User::factory()->administrator()->active()->create());
        $this->authenticateAccount($owner);

        $response = $this->getJson('/api/notifications?per_page=1')->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('meta.total', 2)->assertJsonPath('meta.last_page', 2);
        $this->assertNotSame($private->id, $response->json('data.0.id'));
        $this->assertEqualsCanonicalizing(['id', 'folio', 'titulo', 'descripcion', 'fecha', 'leida'], array_keys($response->json('data.0')));
        $this->getJson('/api/notifications?per_page=101')->assertUnprocessable();
    }

    public function test_reading_a_notification_is_idempotent_and_does_not_confirm_incident_reception(): void
    {
        $assignment = IncidentAssignment::factory()->create();
        $notification = $this->notification($assignment->assignee, $assignment->incident_id);
        $this->authenticateAccount($assignment->assignee);

        $this->postJson('/api/notifications/'.$notification->id.'/read')->assertOk()
            ->assertJsonPath('data.leida', true)->assertJsonPath('data.folio', $assignment->incident->folio);
        $readAt = $notification->refresh()->read_at->toISOString();
        $this->travel(1)->minutes();
        $this->postJson('/api/notifications/'.$notification->id.'/read')->assertOk()->assertJsonPath('data.leida', true);

        $this->assertSame($readAt, $notification->refresh()->read_at->toISOString());
        $this->assertNull($assignment->refresh()->received_at);
        $this->assertNull($assignment->received_by);
    }

    public function test_even_an_administrator_cannot_mark_another_accounts_notification_read(): void
    {
        $notification = $this->notification(User::factory()->active()->create());
        $this->authenticateAccount(User::factory()->administrator()->active()->create());

        $this->postJson('/api/notifications/'.$notification->id.'/read')->assertNotFound();
        $this->assertNull($notification->refresh()->read_at);
    }

    public function test_read_all_only_changes_the_current_accounts_unread_notifications(): void
    {
        $owner = User::factory()->active()->create();
        $first = $this->notification($owner);
        $second = $this->notification($owner);
        $other = $this->notification(User::factory()->active()->create());
        $this->authenticateAccount($owner);

        $this->postJson('/api/notifications/read-all')->assertOk()->assertJsonPath('ok', true);
        $this->assertNotNull($first->refresh()->read_at);
        $this->assertNotNull($second->refresh()->read_at);
        $this->assertNull($other->refresh()->read_at);
    }

    public function test_historical_notifications_do_not_restore_access_after_reassignment(): void
    {
        $old = IncidentAssignment::factory()->create();
        $notification = $this->notification($old->assignee, $old->incident_id);
        $old->update(['ended_at' => now()]);
        IncidentAssignment::factory()->create(['incident_id' => $old->incident_id]);
        $this->authenticateAccount($old->assignee);

        $this->getJson('/api/notifications')->assertOk()->assertJsonPath('data.0.folio', $old->incident->folio);
        $this->postJson('/api/notifications/'.$notification->id.'/read')->assertOk();
        $this->getJson('/api/incidents/'.$old->incident->folio)->assertNotFound();
    }
}
