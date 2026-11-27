<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('incident_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('incident_id')->constrained()->restrictOnDelete();
            $table->foreignId('assigned_to')->constrained('users')->restrictOnDelete();
            $table->foreignId('assigned_by')->constrained('users')->restrictOnDelete();
            $table->dateTime('assigned_at', 6);
            $table->dateTime('ended_at', 6)->nullable();
            $table->dateTime('received_at', 6)->nullable();
            $table->foreignId('received_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->unsignedBigInteger('open_incident_id')->nullable()
                ->virtualAs('CASE WHEN ended_at IS NULL THEN incident_id ELSE NULL END')->unique();
            $table->timestamps(6);
            $table->index(['assigned_to', 'ended_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('incident_assignments');
    }
};
