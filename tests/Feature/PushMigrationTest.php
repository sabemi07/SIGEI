<?php

namespace Tests\Feature;

use App\Models\PushSubscription;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class PushMigrationTest extends TestCase
{
    use DatabaseMigrations;

    public function test_existing_subscription_material_survives_encryption_and_rollback_but_requires_relinking(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $this->artisan('migrate:rollback', ['--step' => 1, '--force' => true])->assertSuccessful();
        $device = (string) Str::uuid();

        foreach ([$user, $other] as $index => $account) {
            $endpoint = 'https://fcm.googleapis.com/legacy-'.$index;
            DB::table('push_subscriptions')->insert([
                'user_id' => $account->id, 'device_id' => $device,
                'endpoint' => $endpoint, 'endpoint_hash' => hash('sha256', $endpoint),
                'public_key' => 'legacy-public-key', 'auth_token' => 'legacy-auth-token',
                'content_encoding' => 'aes128gcm', 'created_at' => now('UTC'), 'updated_at' => now('UTC'),
            ]);
        }

        $this->artisan('migrate', ['--force' => true])->assertSuccessful();
        $subscription = PushSubscription::query()->where('user_id', $user->id)->sole();
        $this->assertSame('https://fcm.googleapis.com/legacy-0', $subscription->endpoint);
        $this->assertSame('legacy-public-key', $subscription->public_key);
        $this->assertSame('legacy-auth-token', $subscription->auth_token);
        $this->assertSame(hash('sha256', $subscription->endpoint), $subscription->endpoint_hash);
        $this->assertSame(2, PushSubscription::query()->whereNotNull('revoked_at')->count());
        $this->assertNull($subscription->binding_id);
        $this->assertNotSame('legacy-auth-token', DB::table('push_subscriptions')->where('id', $subscription->id)->value('auth_token'));
        $this->artisan('migrate:rollback', ['--step' => 1, '--force' => true])->assertSuccessful();
        $this->assertSame('legacy-auth-token', DB::table('push_subscriptions')->where('id', $subscription->id)->value('auth_token'));
        $this->assertSame('https://fcm.googleapis.com/legacy-0', DB::table('push_subscriptions')->where('id', $subscription->id)->value('endpoint'));
        $this->artisan('migrate', ['--force' => true])->assertSuccessful();
    }
}
