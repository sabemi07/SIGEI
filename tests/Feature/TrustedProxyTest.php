<?php

namespace Tests\Feature;

use App\Http\Middleware\IdentifyPushDevice;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\Cookie;
use Tests\TestCase;

class TrustedProxyTest extends TestCase
{
    #[DataProvider('connections')]
    public function test_https_assets_and_device_cookies_follow_only_a_local_proxy(
        string $remoteAddress,
        bool $hasForwardedHeaders,
        bool $expectedSecure,
        string $expectedHost,
        string $expectedClientIp,
    ): void {
        $headers = $hasForwardedHeaders ? [
            'X-Forwarded-Proto' => 'https',
            'X-Forwarded-Host' => 'sigei-mobile.example.test',
            'X-Forwarded-Port' => '443',
            'X-Forwarded-For' => '198.51.100.42',
        ] : [];

        $response = $this->withServerVariables(['REMOTE_ADDR' => $remoteAddress])
            ->getJson('http://localhost/api/session', $headers)
            ->assertOk()
            ->assertJsonPath('user', null);

        $scheme = $expectedSecure ? 'https' : 'http';
        $this->assertSame($expectedSecure, request()->isSecure());
        $this->assertSame($expectedClientIp, request()->ip());
        $this->assertSame("{$scheme}://{$expectedHost}/build/assets/app.js", asset('build/assets/app.js'));

        $deviceCookie = collect($response->headers->getCookies())
            ->first(fn (Cookie $cookie): bool => $cookie->getName() === IdentifyPushDevice::COOKIE);

        $this->assertNotNull($deviceCookie);
        $this->assertSame($expectedSecure, $deviceCookie->isSecure());
        $this->assertTrue($deviceCookie->isHttpOnly());
    }

    /** @return array<string, array{string, bool, bool, string, string}> */
    public static function connections(): array
    {
        return [
            'local IPv4 proxy' => ['127.0.0.1', true, true, 'sigei-mobile.example.test', '198.51.100.42'],
            'local IPv6 proxy' => ['::1', true, true, 'sigei-mobile.example.test', '198.51.100.42'],
            'normal local HTTP' => ['127.0.0.1', false, false, 'localhost', '127.0.0.1'],
            'untrusted IPv4 sender' => ['203.0.113.20', true, false, 'localhost', '203.0.113.20'],
            'untrusted IPv6 sender' => ['2001:db8::20', true, false, 'localhost', '2001:db8::20'],
        ];
    }
}
