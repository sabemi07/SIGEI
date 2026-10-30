<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('push_deliveries', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('notification_id')->constrained('internal_notifications')->cascadeOnDelete();
            $table->foreignId('subscription_id')->constrained('push_subscriptions')->cascadeOnDelete();
            $table->uuid('binding_id');
            $table->enum('status', ['queued', 'sending', 'accepted', 'failed', 'expired', 'skipped'])->default('queued');
            $table->unsignedInteger('attempts')->default(0);
            $table->unsignedSmallInteger('provider_status')->nullable();
            $table->string('error_code', 80)->nullable();
            $table->dateTime('last_attempt_at', 6)->nullable();
            $table->dateTime('accepted_at', 6)->nullable();
            $table->timestamps(6);
            $table->unique(['notification_id', 'subscription_id', 'binding_id'], 'push_delivery_binding_unique');
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('push_deliveries');
    }
};
