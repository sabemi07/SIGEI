<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('public-incidents', function (Request $request): array {
            $response = fn (Request $request, array $headers): JsonResponse => response()->json([
                'message' => 'Se alcanzó el límite temporal de reportes. Espera el tiempo indicado antes de reintentar.',
            ], 429, $headers);

            return [
                Limit::perMinute(max(1, config('sigei.public_reports_per_session')))
                    ->by('incident-session:'.hash('sha256', $request->session()->getId()))->response($response),
                Limit::perMinute(max(1, config('sigei.public_reports_per_ip')))
                    ->by('incident-ip:'.hash('sha256', (string) $request->ip()))->response($response),
            ];
        });
    }
}
