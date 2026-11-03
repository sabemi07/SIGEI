<?php

use App\Services\IncidentFolio;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('incidents', 'folio_corto')) {
            Schema::table('incidents', function (Blueprint $table): void {
                $table->char('folio_corto', 10)->nullable();
            });
        }

        if (! Schema::hasIndex('incidents', ['folio_corto'], 'unique')) {
            Schema::table('incidents', function (Blueprint $table): void {
                $table->unique('folio_corto');
            });
        }

        DB::transaction(function (): void {
            $connection = DB::connection();
            $connection->table('institution_settings')->where('scope', 'institution')->lockForUpdate()->first();

            $connection->table('incidents')->whereNull('folio_corto')->select('id')->eachById(function (object $incident) use ($connection): void {
                $connection->table('incidents')->where('id', $incident->id)->update([
                    'folio_corto' => IncidentFolio::nextShort($connection),
                ]);
            });
        }, 3);

    }

    public function down(): void
    {
        Schema::table('incidents', function (Blueprint $table): void {
            $table->dropUnique(['folio_corto']);
            $table->dropColumn('folio_corto');
        });
    }
};
