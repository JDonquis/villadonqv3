<?php

namespace Database\Seeders;

use App\Enums\UserTypeEnum;
use App\Events\StudentCreated;
use App\Models\Course;
use App\Models\Representative;
use App\Models\Section;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class StudentSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('Creando 200 estudiantes...');

        $totalStudents = 200;
        $studentsPerRepresentative = $this->generateStudentsDistribution($totalStudents);

        $courses = Course::pluck('id')->toArray();
        $sections = Section::pluck('id')->toArray();
        $sexes = ['M', 'F'];
        $relationships = ['Madre', 'Padre', 'Tutor', 'Abuela', 'Abuelo', 'Otro'];
        $professions = ['Docente', 'Ingeniero', 'Médico', 'Abogado', 'Contador', 'Comerciante', 'Empleado', 'Técnico', 'Arquitecto', 'Diseñador'];
        $workplaces = ['Escuela Central', 'Hospital Municipal', 'Tribunal de Justicia', 'Empresa Privada', 'Negociо Propio', 'Centro Comercial', 'Universidad', 'Colegio'];

        $studentCounter = 0;

        foreach ($studentsPerRepresentative as $numStudents) {
            $repName = $this->generateName();
            $repLastName = $this->generateLastName();
            $repCi = $this->generateUniqueCi();
            $repPhone = $this->generatePhone();
            $repEmail = strtolower($repName . '.' . $repLastName . rand(1, 999) . '@gmail.com');
            $profession = $professions[array_rand($professions)];
            $workplace = $workplaces[array_rand($workplaces)];
            $relationship = $relationships[array_rand($relationships)];

            $user = User::create([
                'type_user_id' => UserTypeEnum::Representative->value,
                'ci' => $repCi,
                'name' => $repName,
                'last_name' => $repLastName,
                'email' => $repEmail,
                'password' => Hash::make($repCi),
                'phone_number' => $repPhone,
                'address' => 'Dirección ' . rand(1, 500) . ' - Ciudad',
                'photo' => 'guest.webp',
            ]);

            $representative = Representative::create([
                'user_id' => $user->id,
                'profession' => $profession,
                'workplace' => $workplace,
                'relationship' => $relationship,
            ]);

            for ($i = 0; $i < $numStudents; $i++) {
                $studentCounter++;
                $studentName = $this->generateName();
                $studentLastName = $this->generateLastName();
                $studentCi = $this->generateUniqueStudentCi();
                $studentPhone = $this->generatePhone();
                $sex = $sexes[array_rand($sexes)];
                $courseId = $courses[array_rand($courses)];
                $sectionId = 1;
                $dateBirth = $this->generateDateBirth();

                $student = Student::create([
                    'representative_id' => $representative->id,
                    'course_id' => $courseId,
                    'section_id' => $sectionId,
                    'name' => $studentName,
                    'last_name' => $studentLastName,
                    'date_birth' => $dateBirth,
                    'email' => strtolower($studentName . '.' . $studentLastName . $studentCounter . '@gmail.com'),
                    'ci' => $studentCi,
                    'phone_number' => $studentPhone,
                    'sex' => $sex,
                    'previous_school' => null,
                    'photo' => 'guest.webp',
                    'status' => 1,
                ]);

                if ($studentCounter % 20 === 0) {
                    $this->command->info("{$studentCounter} estudiantes creados...");
                }

                event(new StudentCreated($student));
            }
        }

        $this->command->info("Total: {$studentCounter} estudiantes creados con sus representantes.");
    }

    private function generateStudentsDistribution(int $total): array
    {
        $distribution = [];

        $withOne = (int) ($total * 0.70);
        $withTwo = (int) ($total * 0.25);
        $withThree = $total - $withOne - $withTwo;

        for ($i = 0; $i < $withOne; $i++) {
            $distribution[] = 1;
        }
        for ($i = 0; $i < $withTwo; $i++) {
            $distribution[] = 2;
        }
        for ($i = 0; $i < $withThree; $i++) {
            $distribution[] = 3;
        }

        shuffle($distribution);

        return $distribution;
    }

    private function generateName(): string
    {
        $names = [
            'Juan',
            'Carlos',
            'Miguel',
            'José',
            'Antonio',
            'Luis',
            'Francisco',
            'Pedro',
            'Fernando',
            'Alberto',
            'Rafael',
            'Jorge',
            'Ricardo',
            'Eduardo',
            'Daniel',
            'Manuel',
            'Andrés',
            'Diego',
            'Santiago',
            'Jesús',
            'Alejandro',
            'David',
            'Oscar',
            'Roberto',
            'María',
            'Carmen',
            'Ana',
            'Rosa',
            'Margarita',
            'Isabel',
            'Juana',
            'Francisca',
            'Antonia',
            'Dolores',
            'Luisa',
            'Carmen',
            'Elizabeth',
            'Patricia',
            'Jennifer',
            'Andrea',
            'Catherine',
            'Gabriela',
            'Daniela',
            'Valentina',
            'Mariana',
            'Natalia',
            'Stephanie',
            'Carolina',
        ];

        return $names[array_rand($names)];
    }

    private function generateLastName(): string
    {
        $lastNames = [
            'García',
            'Rodríguez',
            'Martínez',
            'Hernández',
            'López',
            'González',
            'Pérez',
            'Sánchez',
            'Ramírez',
            'Torres',
            'Flores',
            'Rivera',
            'Gómez',
            'Díaz',
            'Reyes',
            'Cruz',
            'Morales',
            'Ortiz',
            'Gutiérrez',
            'Chávez',
            'Ramos',
            'Vargas',
            'Castillo',
            'Jiménez',
            'Vega',
            'Mendoza',
            'Rosas',
            'Aguilar',
            'Vargas',
            'Medina',
            'Castro',
            'Maldonado',
            'Moreno',
            'Herrera',
            'Nuñez',
            'Ibarra',
            'Peréz',
            'Guerrero',
            'León',
            'Salas',
            'Franco',
        ];

        return $lastNames[array_rand($lastNames)];
    }

    private function generateUniqueCi(): string
    {
        do {
            $ci = str_pad(rand(1000000, 99999999), 8, '0', STR_PAD_LEFT);
        } while (User::where('ci', $ci)->exists());

        return $ci;
    }

    private function generateUniqueStudentCi(): string
    {
        do {
            $ci = str_pad(rand(1000000, 99999999), 8, '0', STR_PAD_LEFT);
        } while (Student::where('ci', $ci)->exists());

        return $ci;
    }

    private function generatePhone(): string
    {
        return '04' . rand(12, 99) . rand(1000000, 9999999);
    }

    private function generateDateBirth(): string
    {
        $year = rand(2010, 2018);
        $month = rand(1, 12);
        $day = rand(1, 28);

        return $year . '-' . str_pad($month, 2, '0', STR_PAD_LEFT) . '-' . str_pad($day, 2, '0', STR_PAD_LEFT);
    }
}
