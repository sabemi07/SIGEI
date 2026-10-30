<?php

use App\Http\Controllers\AuthSessionController;
use App\Http\Controllers\IncidentReadController;
use App\Http\Controllers\IncidentWriteController;
use App\Http\Controllers\InstitutionSettingsController;
use App\Http\Controllers\InternalNotificationController;
use App\Http\Controllers\PublicSettingsController;
use App\Http\Controllers\PushSubscriptionController;
use App\Http\Controllers\UserController;
use App\Http\Middleware\IdentifyPushDevice;
use App\Http\Middleware\PreventApiCaching;
use Illuminate\Support\Facades\Route;

Route::view('/', 'app');
Route::view('/demo', 'app', ['runtime' => 'demo']);

Route::prefix('api')->middleware([PreventApiCaching::class, IdentifyPushDevice::class])->group(function (): void {
    Route::get('/session', [AuthSessionController::class, 'show'])->block();
    Route::post('/login', [AuthSessionController::class, 'store'])->middleware('throttle:20,1')->block();
    Route::post('/logout', [AuthSessionController::class, 'destroy'])->block();
    Route::get('/settings/public', PublicSettingsController::class);
    Route::get('/push/notifications/{notification}', [PushSubscriptionController::class, 'notification']);
    Route::post('/incidents', [IncidentWriteController::class, 'store'])->middleware('throttle:public-incidents');

    Route::middleware('account.active')->group(function (): void {
        Route::get('/push', [PushSubscriptionController::class, 'show']);
        Route::post('/push', [PushSubscriptionController::class, 'store'])->middleware('throttle:20,1')->block();
        Route::delete('/push', [PushSubscriptionController::class, 'destroy'])->block();
        Route::post('/push/test', [PushSubscriptionController::class, 'test'])->middleware('throttle:push-test')->block();
        Route::get('/settings', [InstitutionSettingsController::class, 'show']);
        Route::put('/settings', [InstitutionSettingsController::class, 'update']);
        Route::get('/users', [UserController::class, 'index']);
        Route::post('/users', [UserController::class, 'store']);
        Route::patch('/users/{user}', [UserController::class, 'update']);
        Route::get('/responsibles', [IncidentReadController::class, 'responsibles']);
        Route::get('/incidents', [IncidentReadController::class, 'index']);
        Route::get('/incidents/{incident:folio}', [IncidentReadController::class, 'show']);
        Route::post('/incidents/{incident:folio}/management', [IncidentWriteController::class, 'manage']);
        Route::post('/incidents/{incident:folio}/receipt', [IncidentWriteController::class, 'receive']);
        Route::post('/incidents/{incident:folio}/updates', [IncidentWriteController::class, 'followUp']);
        Route::post('/incidents/{incident:folio}/close', [IncidentWriteController::class, 'close']);
        Route::get('/notifications', [InternalNotificationController::class, 'index']);
        Route::post('/notifications/read-all', [InternalNotificationController::class, 'readAll']);
        Route::post('/notifications/{notification}/read', [InternalNotificationController::class, 'read']);
    });
});
