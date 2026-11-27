<?php

namespace Tests\Feature;

use App\Models\EmergencyType;
use App\Models\Location;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PublicReportAbuseTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    /** @return array<string, mixed> */
    private function report(): array
    {
        return [
            'tipo_id' => EmergencyType::query()->firstOrFail()->id,
            'ubicacion_id' => Location::query()->where('requires_details', false)->firstOrFail()->id,
            'descripcion' => 'Reporte utilizado únicamente en pruebas aisladas.',
            'solicitud_id' => Str::uuid()->toString(),
        ];
    }

    public function test_public_reporting_is_limited_per_session_without_creating_excess_reports(): void
    {
        config(['sigei.public_reports_per_session' => 1, 'sigei.public_reports_per_ip' => 60]);
        $this->getJson('/api/session')->assertOk();
        $this->withCookie(config('session.cookie'), session()->getId())->withCredentials();
        $this->postJson('/api/incidents', $this->report())->assertCreated();
        $this->postJson('/api/incidents', $this->report())->assertStatus(429)->assertHeader('Retry-After');
        $this->assertDatabaseCount('incidents', 1);
    }

    public function test_an_ip_limit_still_applies_after_starting_another_guest_session(): void
    {
        config(['sigei.public_reports_per_session' => 60, 'sigei.public_reports_per_ip' => 1]);
        $this->getJson('/api/session')->assertOk();
        $this->postJson('/api/incidents', $this->report())->assertCreated();
        $previousSession = session()->getId();
        session()->invalidate();
        session()->regenerateToken();
        $this->assertNotSame($previousSession, session()->getId());
        $this->postJson('/api/incidents', $this->report())->assertStatus(429)->assertHeader('Retry-After');
        $this->assertDatabaseCount('incidents', 1);
    }
}
