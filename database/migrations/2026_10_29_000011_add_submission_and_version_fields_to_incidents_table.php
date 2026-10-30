<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('incidents', function (Blueprint $table): void {
            $table->unsignedInteger('lock_version')->default(0);
            $table->char('submission_key_hash', 64)->nullable()->unique();
            $table->char('submission_scope_hash', 64)->nullable();
            $table->char('submission_payload_hash', 64)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('incidents', function (Blueprint $table): void {
            $table->dropUnique(['submission_key_hash']);
            $table->dropColumn(['lock_version', 'submission_key_hash', 'submission_scope_hash', 'submission_payload_hash']);
        });
    }
};
