<?php

namespace Database\Seeders;

use App\Models\Module;
use Illuminate\Database\Seeder;

class ModuleSeeder extends Seeder
{
    /**
     * Módulos a los que puede acceder un administrador.
     * Este es el listado que se muestra como checkboxes en Personal.
     */
    public const MODULES = [
        ['slug' => 'configuracion', 'name' => 'Configuración', 'icon' => 'uil:setting', 'order' => 1],
        ['slug' => 'matricula', 'name' => 'Matrícula', 'icon' => 'mdi:school', 'order' => 2],
        ['slug' => 'pagos', 'name' => 'Pagos', 'icon' => 'streamline:payment-10-solid', 'order' => 3],
        ['slug' => 'estados-cuenta', 'name' => 'Estados de Cuenta', 'icon' => 'mdi:finance', 'order' => 4],
        ['slug' => 'personal', 'name' => 'Personal', 'icon' => 'ph:users', 'order' => 5],
        ['slug' => 'profesores', 'name' => 'Profesores', 'icon' => 'mdi:account-tie', 'order' => 6],
        ['slug' => 'materias', 'name' => 'Materias', 'icon' => 'mdi:book-open-variant', 'order' => 7],
        ['slug' => 'planes-evaluacion', 'name' => 'Planes de Evaluación', 'icon' => 'mdi:clipboard-check-outline', 'order' => 8],
        ['slug' => 'horarios', 'name' => 'Horarios', 'icon' => 'mdi:calendar-clock', 'order' => 9],
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach (self::MODULES as $module) {
            Module::updateOrCreate(
                ['slug' => $module['slug']],
                $module
            );
        }
    }
}
