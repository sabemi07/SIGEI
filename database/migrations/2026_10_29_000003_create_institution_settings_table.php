<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('institution_settings', function (Blueprint $table) {
            $table->enum('scope', ['institution'])->default('institution')->primary();
            $table->string('name', 150);
            $table->string('abbreviation', 20);
            $table->boolean('notify_new_incidents')->default(true);
            $table->boolean('notify_assignments')->default(true);
            $table->boolean('notify_updates')->default(true);
            $table->foreignId('updated_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('institution_settings');
    }
};
