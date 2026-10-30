<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('push_subscriptions')->orderBy('id')->eachById(function (object $subscription): void {
            DB::table('push_subscriptions')->where('id', $subscription->id)->update([
                'endpoint' => Crypt::encryptString($subscription->endpoint),
                'public_key' => Crypt::encryptString($subscription->public_key),
                'auth_token' => Crypt::encryptString($subscription->auth_token),
                'revoked_at' => $subscription->revoked_at ?? now('UTC'),
            ]);
        });

        Schema::table('push_subscriptions', function (Blueprint $table): void {
            $table->uuid('binding_id')->nullable();
            $table->char('session_hash', 64)->nullable();
            $table->unsignedInteger('auth_version')->nullable();
            $table->uuid('active_device_id')->nullable()->virtualAs('CASE WHEN revoked_at IS NULL THEN device_id ELSE NULL END');
            $table->unique('active_device_id');
        });
    }

    public function down(): void
    {
        Schema::table('push_subscriptions', function (Blueprint $table): void {
            $table->dropUnique(['active_device_id']);
            $table->dropColumn(['active_device_id', 'binding_id', 'session_hash', 'auth_version']);
        });

        DB::table('push_subscriptions')->orderBy('id')->eachById(function (object $subscription): void {
            DB::table('push_subscriptions')->where('id', $subscription->id)->update([
                'endpoint' => Crypt::decryptString($subscription->endpoint),
                'public_key' => Crypt::decryptString($subscription->public_key),
                'auth_token' => Crypt::decryptString($subscription->auth_token),
            ]);
        });
    }
};
