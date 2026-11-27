<?php

namespace Database\Seeders;

use App\Models\EmergencyType;
use App\Models\InstitutionSetting;
use App\Models\Location;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SigeiCatalogSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            InstitutionSetting::query()->firstOrCreate(['scope' => 'institution'], [
                'name' => 'Institución por configurar',
                'abbreviation' => 'SIGEI',
                'notify_new_incidents' => true,
                'notify_assignments' => true,
                'notify_updates' => true,
            ]);

            if (EmergencyType::query()->doesntExist()) {
                foreach ([
                    'Emergencia médica',
                    'Situación de riesgo',
                    'Altercado',
                    'Persona intoxicada',
                    'Otro incidente',
                ] as $name) {
                    EmergencyType::query()->create(['name' => $name]);
                }
            }

            if (Location::query()->doesntExist()) {
                foreach ([
                    'Edificio A · Planta baja',
                    'Edificio A · Planta alta',
                    'Edificio B · Aulas',
                    'Edificio C · Pasillos',
                    'Laboratorio',
                    'Biblioteca',
                    'Cafetería',
                    'Estacionamiento',
                    'Cancha deportiva',
                    'Otro lugar',
                ] as $name) {
                    Location::query()->create([
                        'name' => $name,
                        'requires_details' => $name === 'Otro lugar',
                    ]);
                }
            }
        });
    }
}
