<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('incidents', function (Blueprint $table) {
            $table->id();
            $table->char('folio', 30)->unique();
            $table->foreignId('emergency_type_id')->constrained()->restrictOnDelete();
            $table->foreignId('location_id')->constrained()->restrictOnDelete();
            $table->string('type_name', 100);
            $table->string('location_name', 150);
            $table->string('location_details')->nullable();
            $table->text('description')->nullable();
            $table->foreignId('reporter_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->enum('status', ['Pendiente', 'Recibida', 'En atención', 'Atendida', 'Cerrada'])->default('Pendiente');
            $table->enum('priority', ['Por evaluar', 'Alta', 'Media', 'Baja'])->default('Por evaluar');
            $table->dateTime('reported_at', 6);
            $table->dateTime('closed_at', 6)->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps(6);
            $table->index(['status', 'reported_at']);
            $table->index(['reporter_user_id', 'reported_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('incidents');
    }
};
