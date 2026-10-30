<?php

namespace Tests\Feature;

use Illuminate\Support\Str;
use Tests\TestCase;

class PushKeysTest extends TestCase
{
    public function test_key_generation_is_private_and_does_not_replace_existing_keys(): void
    {
        $path = storage_path('framework/testing/sigei-test-keys-'.Str::uuid().'.json');
        config(['sigei.push.key_file' => $path]);

        try {
            $this->artisan('sigei:push-keys')->assertSuccessful();
            $original = file_get_contents($path);
            $keys = json_decode($original, true);
            $public = base64_decode(strtr($keys['public_key'], '-_', '+/'), true);
            $private = base64_decode(strtr($keys['private_key'], '-_', '+/'), true);
            $this->assertSame(65, strlen($public));
            $this->assertSame("\x04", $public[0]);
            $this->assertSame(32, strlen($private));
            $this->artisan('sigei:push-keys')->assertFailed();
            $this->assertSame($original, file_get_contents($path));
        } finally {
            if (is_file($path)) {
                unlink($path);
            }
        }
    }
}
