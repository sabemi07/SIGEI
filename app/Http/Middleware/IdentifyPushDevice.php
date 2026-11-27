<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class IdentifyPushDevice
{
    public const COOKIE = 'sigei_push_device';

    public function handle(Request $request, Closure $next): Response
    {
        $device = $request->cookie(self::COOKIE);
        $isNew = ! is_string($device) || ! Str::isUuid($device);
        $device = $isNew ? (string) Str::uuid() : $device;
        $request->attributes->set('push_device_id', $device);
        $response = $next($request);

        if ($isNew) {
            $response->headers->setCookie(cookie(self::COOKIE, $device, 60 * 24 * 365, '/', null, $request->isSecure(), true, false, 'strict'));
        }

        return $response;
    }
}
