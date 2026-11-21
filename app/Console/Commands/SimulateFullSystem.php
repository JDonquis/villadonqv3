<?php

namespace App\Console\Commands;

use App\Enums\EvaluationPlanStatusEnum;
use App\Enums\UserTypeEnum;
use App\Models\Course;
use App\Models\CourseSection;
use App\Models\Lapse;
use App\Models\Matter;
use App\Models\Quota;
use App\Models\Representative;
use App\Models\Schedule;
use App\Models\SchoolLapse;
use App\Models\Section;
use App\Models\Student;
use App\Models\User;
use App\Services\DocumentService;
use App\Services\EvaluationPlanService;
use App\Services\ReportService;
use App\Services\StudentGradeService;
use App\Services\StudentService;
use App\Services\TeacherService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Database\Seeders\AccountPaymentSeeder;
use Database\Seeders\CourseSectionSeeder;
use Database\Seeders\CourseSeeder;
use Database\Seeders\MainConfigSeeder;
use Database\Seeders\MatterSeeder;
use Database\Seeders\ModuleSeeder;
use Database\Seeders\PaymentConceptSeeder;
use Database\Seeders\PaymentMethodSeeder;
use Database\Seeders\PlanDeEstudioCodeSeeder;
use Database\Seeders\RequestStatusSeeder;
use Database\Seeders\SectionSeeder;
use Database\Seeders\TypeDocumentSeeder;
use Database\Seeders\TypeUserSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class SimulateFullSystem extends Command
{
    protected $signature = 'demo:full-system
        {--students=2 : Estudiantes por sección y curso}
        {--admins=2 : Cantidad de administradores a crear}
        {--matters=1,2,3,4,5 : IDs de materias separadas por coma, o "all"}
        {--period-end= : Fecha final del año simulado (Y-m-d). Por defecto hoy}
        {--keep-data : No reinicia la base de datos; agrega la simulación a los datos actuales}
        {--skip-documents : No genera los PDF de muestra}
        {--force : No pide confirmación para borrar los datos actuales}';

    protected $description = 'Simula un año escolar completo con datos normales: personal, estudiantes, planes, notas, horarios y documentos.';

    private const PASSWORD = '12345678';

    private const PERIODS_PER_DAY = 7;

    private StudentService $studentService;

    private EvaluationPlanService $planService;

    private StudentGradeService $gradeService;

    private TeacherService $teacherService;

    private array $names = [
        'Ana', 'Luis', 'María', 'Carlos', 'Sofía', 'Diego', 'Valeria', 'José',
        'Camila', 'Andrés', 'Gabriela', 'Miguel', 'Daniela', 'Jesús', 'Paola',
        'Ricardo', 'Elena', 'Fernando', 'Lucía', 'Manuel', 'Isabel', 'Pedro',
    ];

    private array $lastNames = [
        'García', 'Rodríguez', 'Martínez', 'Hernández', 'López', 'González',
        'Pérez', 'Sánchez', 'Ramírez', 'Torres', 'Flores', 'Rivera',
        'Gómez', 'Díaz', 'Morales', 'Castro', 'Vargas', 'Ramos',
    ];

    public function handle(): int
    {
        $this->studentService = new StudentService;
        $this->planService = new EvaluationPlanService;
        $this->gradeService = new StudentGradeService;
        $this->teacherService = new TeacherService;

        if (! $this->option('keep-data')) {
            if (! $this->option('force')) {
                if (! $this->input->isInteractive()) {
                    $this->error('Esto elimina todos los datos actuales. Ejecuta con --force para continuar.');

                    return self::FAILURE;
                }

                if (! $this->confirm('Esto eliminará TODOS los datos actuales y creará una simulación nueva. ¿Continuar?', false)) {
                    $this->warn('Operación cancelada.');

                    return self::SUCCESS;
                }
            }

            $this->warn('Reiniciando la base de datos...');
            Artisan::call('migrate:fresh', ['--force' => true]);
            $this->info('Base de datos reiniciada.');
        }

        try {
            $this->ensureBaseData();
        } catch (Throwable $e) {
            $this->error('No se pudieron preparar los datos base: '.$e->getMessage());

            return self::FAILURE;
        }

        $matters = $this->resolveMatters();
        if ($matters->isEmpty()) {
            $this->error('No hay materias disponibles para simular.');

            return self::FAILURE;
        }

        $studentsPerSection = max(1, (int) $this->option('students'));
        $adminsCount = max(1, (int) $this->option('admins'));
        $periodEnd = $this->option('period-end')
            ? Carbon::parse($this->option('period-end'))->startOfDay()
            : Carbon::today();

        $previousBroadcast = config('broadcasting.default');
        config(['broadcasting.default' => 'null']);

        try {
            $result = DB::transaction(function () use ($matters, $studentsPerSection, $adminsCount, $periodEnd) {
                [$schoolLapse, $lapses, $sections] = $this->resolvePeriod($periodEnd);
                $this->ensureQuotas($schoolLapse, $studentsPerSection, $sections->count());

                $admins = $this->createAdmins($adminsCount);
                $allMatters = $this->mattersForSimulation($matters);
                $teachers = $this->createTeachers($allMatters);
                $studentsBySection = $this->createStudents($sections, $studentsPerSection);

                $plans = $this->createPlansAndGrades(
                    $schoolLapse,
                    $lapses,
                    $sections,
                    $studentsBySection,
                    $teachers,
                    $admins[0],
                    $matters->pluck('id')->all()
                );

                $schedules = $this->createSchedules(
                    $schoolLapse,
                    $sections,
                    $studentsBySection,
                    $teachers,
                    $allMatters
                );

                return compact('schoolLapse', 'lapses', 'sections', 'admins', 'teachers', 'studentsBySection', 'plans', 'schedules');
            });
        } catch (Throwable $e) {
            $this->error('La simulación falló y fue revertida: '.$e->getMessage());

            return self::FAILURE;
        } finally {
            config(['broadcasting.default' => $previousBroadcast]);
        }

        $documents = [];
        if (! $this->option('skip-documents')) {
            $documents = $this->generateDocuments($result['studentsBySection'], $result['schoolLapse'], $result['lapses']);
        }

        $this->printSummary($result, $documents);

        return self::SUCCESS;
    }

    private function ensureBaseData(): void
    {
        $seeders = [
            TypeUserSeeder::class => 'type_users',
            UserSeeder::class => 'users',
            CourseSeeder::class => 'courses',
            SectionSeeder::class => 'sections',
            CourseSectionSeeder::class => 'course_sections',
            RequestStatusSeeder::class => 'request_status',
            TypeDocumentSeeder::class => 'type_documents',
            MainConfigSeeder::class => 'main_configs',
            PaymentMethodSeeder::class => 'payment_methods',
            AccountPaymentSeeder::class => 'account_payments',
        ];

        foreach ($seeders as $seeder => $table) {
            if (DB::table($table)->count() === 0) {
                app($seeder)->run();
            }
        }

        app(ModuleSeeder::class)->run();
        app(MatterSeeder::class)->run();
        app(PaymentConceptSeeder::class)->run();
        app(PlanDeEstudioCodeSeeder::class)->run();
    }

    private function resolveMatters()
    {
        $raw = strtolower(trim((string) $this->option('matters')));

        if ($raw === 'all') {
            return Matter::orderBy('id')->get();
        }

        $ids = collect(explode(',', $raw))
            ->map(fn ($value) => (int) trim($value))
            ->filter(fn ($value) => $value > 0)
            ->unique()
            ->values();

        $matters = Matter::whereIn('id', $ids)->orderBy('id')->get();

        if ($matters->isEmpty()) {
            $matters = Matter::orderBy('id')->take(5)->get();
            $this->warn('No se encontraron las materias indicadas; se usarán: '.$matters->pluck('name')->join(', '));
        }

        return $matters;
    }

    private function mattersForSimulation($selectedMatters)
    {
        $ids = $selectedMatters->pluck('id')->all();
        $courseMatterIds = DB::table('courses_matters')->pluck('matter_id')->unique()->all();

        $allIds = collect($ids)->merge($courseMatterIds)->unique()->values();

        return Matter::whereIn('id', $allIds)->orderBy('id')->get();
    }

    private function resolvePeriod(Carbon $periodEnd): array
    {
        $start = $periodEnd->copy()->subYear()->addDay();

        SchoolLapse::query()->update(['status' => 0]);

        $schoolLapse = SchoolLapse::create([
            'start' => $start->toDateString(),
            'end' => $periodEnd->toDateString(),
            'status' => 1,
        ]);

        $this->ensureLapses($schoolLapse);

        $sections = collect(['A', 'B'])
            ->map(fn ($name) => Section::firstOrCreate(['name' => $name]))
            ->values();

        foreach (Course::orderBy('id')->pluck('id') as $courseId) {
            foreach ($sections as $section) {
                CourseSection::firstOrCreate([
                    'course_id' => $courseId,
                    'section_id' => $section->id,
                ]);
            }
        }

        $this->info("Período escolar #{$schoolLapse->id} creado: {$schoolLapse->start} → {$schoolLapse->end}");
        $this->info('Secciones por año: '.$sections->pluck('name')->join(' y '));

        return [$schoolLapse, $schoolLapse->lapses()->orderBy('number')->get(), $sections];
    }

    private function ensureLapses(SchoolLapse $schoolLapse): void
    {
        $seedLapses = new SeedLapses;

        foreach ($seedLapses->buildLapseRanges($schoolLapse) as $number => $range) {
            Lapse::firstOrCreate(
                [
                    'school_lapse_id' => $schoolLapse->id,
                    'number' => $number,
                ],
                [
                    'start' => $range['start'],
                    'end' => $range['end'],
                ]
            );
        }
    }

    private function ensureQuotas(SchoolLapse $schoolLapse, int $studentsPerSection, int $sectionsCount): void
    {
        foreach (Course::orderBy('id')->get() as $course) {
            $quota = Quota::firstOrCreate(
                [
                    'school_lapse_id' => $schoolLapse->id,
                    'course_id' => $course->id,
                ],
                [
                    'assigned' => 500,
                    'accepted' => 0,
                    'remaining' => 500,
                ]
            );

            $needed = (int) $quota->accepted + ($studentsPerSection * $sectionsCount) + 5;
            $assigned = max((int) $quota->assigned, $needed);

            $quota->update([
                'assigned' => $assigned,
                'remaining' => max(0, $assigned - (int) $quota->accepted),
            ]);
        }
    }

    private function createAdmins(int $count): array
    {
        $admins = [];

        for ($index = 0; $index < $count; $index++) {
            $name = $this->names[$index % count($this->names)];
            $lastName = $this->lastNames[$index % count($this->lastNames)];
            $email = $this->uniqueEmail($name.'.'.$lastName.'.direccion');

            $admins[] = User::create([
                'type_user_id' => UserTypeEnum::Administrator->value,
                'ci' => $this->randomCi('users'),
                'name' => $name,
                'last_name' => $lastName,
                'email' => $email,
                'password' => Hash::make(self::PASSWORD),
                'phone_number' => '0414'.random_int(1000000, 9999999),
                'address' => 'Oficina administrativa',
                'photo' => 'guest.webp',
                'email_verified_status' => true,
                'is_admin' => true,
            ]);
        }

        return $admins;
    }

    private function createTeachers($matters): array
    {
        $teachers = [];

        foreach ($matters as $index => $matter) {
            $name = $this->names[($index * 3) % count($this->names)];
            $lastName = $this->lastNames[($index * 5) % count($this->lastNames)];
            $email = $this->uniqueEmail($name.'.'.$lastName.'.docente');

            $teacher = $this->teacherService->createTeacher([
                'ci' => $this->randomCi('users'),
                'name' => $name,
                'last_name' => $lastName,
                'email' => $email,
                'phone_number' => '0424'.random_int(1000000, 9999999),
                'address' => 'Área docente',
                'password' => Hash::make(self::PASSWORD),
                'matters' => [$matter->id],
            ]);

            $teachers[$matter->id] = $teacher;
        }

        return $teachers;
    }

    private function createStudents($sections, int $perSection): array
    {
        $studentsBySection = [];

        foreach (Course::orderBy('id')->get() as $course) {
            foreach ($sections as $section) {
                $repName = $this->names[($course->id + $section->id) % count($this->names)];
                $repLastName = $this->lastNames[($course->id + $section->id * 2) % count($this->lastNames)];

                $repUser = User::create([
                    'type_user_id' => UserTypeEnum::Representative->value,
                    'ci' => $this->randomCi('users'),
                    'name' => $repName,
                    'last_name' => $repLastName,
                    'email' => $this->uniqueEmail($repName.'.'.$repLastName.'.representante'),
                    'password' => Hash::make(self::PASSWORD),
                    'phone_number' => '0416'.random_int(1000000, 9999999),
                    'address' => 'Comunidad educativa',
                    'state' => 'Falcón',
                    'city' => 'Coro',
                    'document_type' => 'V',
                    'photo' => 'guest.webp',
                    'email_verified_status' => true,
                ]);

                $representative = Representative::create([
                    'user_id' => $repUser->id,
                    'profession' => 'Profesional',
                    'workplace' => 'Empresa privada',
                    'relationship' => $section->id % 2 === 0 ? 'Padre' : 'Madre',
                    'document_type' => 'V',
                ]);

                for ($index = 1; $index <= $perSection; $index++) {
                    $studentName = $this->names[($course->id + $section->id + $index * 4) % count($this->names)];
                    $studentLastName = $this->lastNames[($course->id * 2 + $section->id + $index * 3) % count($this->lastNames)];
                    $studentEmail = $this->uniqueEmail($studentName.'.'.$studentLastName.'.alumno');
                    $studentCi = $this->randomCi('students');

                    $this->studentService->create(Request::create('/demo', 'POST', [
                        'course_id' => $course->id,
                        'section_id' => $section->id,
                        'student_name' => $studentName,
                        'student_last_name' => $studentLastName,
                        'student_date_birth' => $this->birthDate($course->id, $index),
                        'student_email' => $studentEmail,
                        'student_ci' => $studentCi,
                        'student_phone_number' => '0412'.random_int(1000000, 9999999),
                        'student_sex' => $index % 2 === 0 ? 'F' : 'M',
                        'student_previous_school' => null,
                        'student_document_type' => 'V',
                        'rep_name' => $repUser->name,
                        'rep_last_name' => $repUser->last_name,
                        'rep_ci' => $repUser->ci,
                        'rep_email' => $repUser->email,
                        'rep_phone_number' => $repUser->phone_number,
                        'rep_profession' => $representative->profession,
                        'rep_workplace' => $representative->workplace,
                        'rep_relationship' => $representative->relationship,
                        'rep_document_type' => $representative->document_type,
                        'address' => $repUser->address,
                        'state' => $repUser->state,
                        'city' => $repUser->city,
                        'is_exempt' => false,
                    ]));

                    $student = Student::where('ci', $studentCi)->first();

                    if (! $student) {
                        throw new RuntimeException('No se pudo crear el estudiante para '.$course->name.' '.$section->name.'.');
                    }

                    $student->update(['search' => $this->buildSearch($student)]);
                    $studentsBySection[$course->id][$section->id][] = $student;
                }
            }
        }

        return $studentsBySection;
    }

    private function createPlansAndGrades(
        SchoolLapse $schoolLapse,
        $lapses,
        $sections,
        array $studentsBySection,
        array $teachers,
        User $admin,
        array $selectedMatterIds
    ): array {
        $plans = 0;
        $grades = 0;
        $publications = 0;

        foreach ($studentsBySection as $courseId => $sectionStudents) {
            $courseMatterIds = DB::table('courses_matters')
                ->where('course_id', $courseId)
                ->pluck('matter_id')
                ->all();

            $matterIds = collect($courseMatterIds)
                ->merge($selectedMatterIds)
                ->unique()
                ->filter(fn ($id) => isset($teachers[$id]))
                ->values();

            foreach ($sections as $section) {
                $students = $sectionStudents[$section->id] ?? [];

                if ($students === []) {
                    continue;
                }

                foreach ($matterIds as $matterId) {
                    $teacher = $teachers[$matterId] ?? null;
                    $matter = Matter::find($matterId);

                    if (! $teacher || ! $matter) {
                        continue;
                    }

                    foreach ($lapses as $lapse) {
                        $plan = $this->planService->createPlan($teacher->id, [
                            'matter_id' => $matterId,
                            'school_lapse_id' => $schoolLapse->id,
                            'lapse_id' => $lapse->id,
                            'course_id' => $courseId,
                            'section_id' => $section->id,
                            'description' => 'Plan de evaluación de '.$matter->name.' para '.$this->momentLabel($lapse).'.',
                            'rasgos_points' => 2,
                            'status' => EvaluationPlanStatusEnum::Approved->value,
                            'approved_by' => $admin->id,
                            'approved_at' => now(),
                            'units' => $this->buildUnits($lapse, $matter),
                        ]);

                        $plans++;

                        $plan->load('items');
                        $items = $plan->items;

                        if ($items->isEmpty()) {
                            continue;
                        }

                        $gradeRows = [];
                        $rasgoRows = [];

                        foreach ($students as $studentPosition => $student) {
                            foreach ($items as $itemPosition => $item) {
                                $score = 11 + (($studentPosition + $itemPosition + $lapse->number + $courseId + $matterId) % 10);

                                $gradeRows[] = [
                                    'plan_item_id' => $item->id,
                                    'student_id' => $student->id,
                                    'score' => $score,
                                ];
                            }

                            $rasgoRows[] = [
                                'student_id' => $student->id,
                                'rasgos_score' => ($studentPosition + $lapse->number + $matterId) % 3,
                            ];
                        }

                        $grades += $this->gradeService->saveGrades(
                            $plan->id,
                            $gradeRows,
                            $rasgoRows,
                            $teacher->id
                        );

                        $this->gradeService->publishGrades($plan->id, $teacher->id);
                        $publications++;
                    }
                }
            }
        }

        return compact('plans', 'grades', 'publications');
    }

    private function buildUnits(Lapse $lapse, Matter $matter): array
    {
        $start = Carbon::parse($lapse->start);
        $end = Carbon::parse($lapse->end);
        $days = max(1, $start->diffInDays($end));

        $date = fn (float $fraction) => $start->copy()->addDays((int) round($days * $fraction))->toDateString();

        return [
            [
                'unit_number' => 1,
                'name' => 'Unidad 1: Fundamentos',
                'topics' => [
                    [
                        'name' => 'Diagnóstico de '.$matter->name,
                        'assessment_type' => 'Prueba diagnóstica',
                        'percentage' => 20,
                        'points' => 4,
                        'scheduled_date' => $date(0.15),
                        'description' => 'Evaluación inicial de saberes previos.',
                    ],
                    [
                        'name' => 'Taller práctico',
                        'assessment_type' => 'Taller',
                        'percentage' => 20,
                        'points' => 4,
                        'scheduled_date' => $date(0.35),
                        'description' => 'Actividad práctica en clase.',
                    ],
                ],
            ],
            [
                'unit_number' => 2,
                'name' => 'Unidad 2: Aplicación',
                'topics' => [
                    [
                        'name' => 'Proyecto integrador',
                        'assessment_type' => 'Proyecto',
                        'percentage' => 25,
                        'points' => 5,
                        'scheduled_date' => $date(0.60),
                        'description' => 'Proyecto de investigación y exposición.',
                    ],
                    [
                        'name' => 'Evaluación final',
                        'assessment_type' => 'Examen',
                        'percentage' => 25,
                        'points' => 5,
                        'scheduled_date' => $date(0.85),
                        'description' => 'Prueba escrita de cierre del momento.',
                    ],
                ],
            ],
        ];
    }

    private function createSchedules(
        SchoolLapse $schoolLapse,
        $sections,
        array $studentsBySection,
        array $teachers,
        $allMatters
    ): int {
        $created = 0;
        $slotCount = 5 * self::PERIODS_PER_DAY;
        $courseIds = array_keys($studentsBySection);
        $sectionIndex = 0;

        foreach ($courseIds as $courseId) {
            $courseMatterIds = DB::table('courses_matters')
                ->where('course_id', $courseId)
                ->pluck('matter_id')
                ->filter(fn ($id) => isset($teachers[$id]))
                ->values();

            if ($courseMatterIds->isEmpty()) {
                $courseMatterIds = $allMatters
                    ->pluck('id')
                    ->filter(fn ($id) => isset($teachers[$id]))
                    ->values();
            }

            foreach ($sections as $section) {
                if (! isset($studentsBySection[$courseId][$section->id])) {
                    continue;
                }

                $schedule = Schedule::updateOrCreate(
                    [
                        'school_lapse_id' => $schoolLapse->id,
                        'course_id' => $courseId,
                        'section_id' => $section->id,
                    ],
                    [
                        'recess_start' => '09:30',
                        'recess_duration_minutes' => 30,
                    ]
                );

                $schedule->classes()->delete();

                foreach ($courseMatterIds->values() as $matterPosition => $matterId) {
                    $slot = ($sectionIndex + $matterPosition) % $slotCount;
                    $day = ($slot % 5) + 1;
                    $hour = 7 + intdiv($slot, 5);
                    $start = sprintf('%02d:00:00', $hour);
                    $end = sprintf('%02d:50:00', $hour);

                    $schedule->classes()->create([
                        'day' => $day,
                        'start_time' => $start,
                        'end_time' => $end,
                        'matter_id' => $matterId,
                        'teacher_id' => $teachers[$matterId]->id,
                        'order' => $matterPosition,
                    ]);
                }

                $sectionIndex++;
                $created++;
            }
        }

        return $created;
    }

    private function generateDocuments(array $studentsBySection, SchoolLapse $schoolLapse, $lapses): array
    {
        $student = collect($studentsBySection)->flatten(2)->first();

        if (! $student) {
            return [];
        }

        $student->load(['course', 'section', 'representative.user']);

        $directory = storage_path('app/demo-documents');
        File::ensureDirectoryExists($directory);

        $slug = $student->ci ?: $student->id;
        $lapseId = $schoolLapse->status ? null : $lapses->last()?->id;
        $documents = [];

        try {
            $reportService = new ReportService;
            $reportData = $reportService->getStudentReportData($student->id, $lapseId);

            $boleta = Pdf::loadView('reportes.boleta', $reportData)
                ->setPaper('letter')
                ->setOption('isRemoteEnabled', true);

            $boletaPath = $directory.'/boleta-'.$slug.'.pdf';
            File::put($boletaPath, $boleta->output());
            $documents['Boleta'] = $boletaPath;

            $certificado = Pdf::loadView('reportes.certificado', $reportData)
                ->setPaper('letter')
                ->setOption('isRemoteEnabled', true);

            $certificadoPath = $directory.'/certificado-'.$slug.'.pdf';
            File::put($certificadoPath, $certificado->output());
            $documents['Certificado'] = $certificadoPath;
        } catch (Throwable $e) {
            $this->warn('No se pudo generar la boleta/certificado: '.$e->getMessage());
        }

        try {
            $certificadoNotasData = (new DocumentService)->getCertificadoNotas($student->id);

            $certificadoNotas = Pdf::loadView('reportes.documentos.certificado-notas', ['data' => $certificadoNotasData])
                ->setPaper('legal', 'portrait')
                ->setOption('isRemoteEnabled', true);

            $certificadoNotasPath = $directory.'/certificado-notas-'.$slug.'.pdf';
            File::put($certificadoNotasPath, $certificadoNotas->output());
            $documents['Certificado de notas'] = $certificadoNotasPath;
        } catch (Throwable $e) {
            $this->warn('No se pudo generar el certificado de notas: '.$e->getMessage());
        }

        return $documents;
    }

    private function printSummary(array $result, array $documents): void
    {
        $schoolLapse = $result['schoolLapse'];
        $students = collect($result['studentsBySection'])->flatten(2);

        $this->newLine();
        $this->info('Simulación completada.');
        $this->table(['Dato', 'Valor'], [
            ['Período escolar', "#{$schoolLapse->id} ({$schoolLapse->start} → {$schoolLapse->end})"],
            ['Secciones', $result['sections']->pluck('name')->join(', ')],
            ['Cursos', Course::count()],
            ['Administradores', count($result['admins'])],
            ['Profesores', count($result['teachers'])],
            ['Estudiantes', $students->count()],
            ['Planes creados', $result['plans']['plans']],
            ['Notas guardadas', $result['plans']['grades']],
            ['Publicaciones de notas', $result['plans']['publications']],
            ['Horarios generados', $result['schedules']],
        ]);

        $this->table(['Acceso', 'Correo', 'Contraseña'], [
            ['Administrador', $result['admins'][0]->email, self::PASSWORD],
            ['Profesor', reset($result['teachers'])->email, self::PASSWORD],
            ['Representante', User::where('type_user_id', UserTypeEnum::Representative->value)->value('email'), self::PASSWORD],
        ]);

        $this->line('Todos los usuarios generados usan la contraseña <fg=green>'.self::PASSWORD.'</>.');

        if ($documents !== []) {
            $this->newLine();
            $this->info('Documentos generados:');
            foreach ($documents as $label => $path) {
                $this->line("- {$label}: {$path}");
            }
        } elseif (! $this->option('skip-documents')) {
            $this->warn('No se generaron documentos de muestra.');
        }

        $lapseQuery = $schoolLapse->status ? '' : '?lapse_id='.$result['lapses']->last()?->id;

        $this->newLine();
        $this->line('Boleta: /dashboard/reportes/boleta/'.$students->first()->id.$lapseQuery);
        $this->line('Certificado: /dashboard/reportes/certificado/'.$students->first()->id.$lapseQuery);
        $this->line('Certificado de notas: /dashboard/documentos/certificado-notas?student_id='.$students->first()->id);
    }

    private function randomCi(string $table): string
    {
        do {
            $ci = (string) ($table === 'students'
                ? random_int(25000000, 26999999)
                : random_int(8000000, 24999999));

            $exists = $table === 'students'
                ? Student::where('ci', $ci)->exists()
                : User::where('ci', $ci)->exists();
        } while ($exists);

        return $ci;
    }

    private function uniqueEmail(string $prefix): string
    {
        $base = strtolower(Str::ascii(Str::slug($prefix, '.')));
        $base = preg_replace('/[^a-z0-9.]/', '', $base) ?: 'usuario';
        $email = $base.'@gmail.com';
        $counter = 1;

        while (User::where('email', $email)->exists() || Student::where('email', $email)->exists()) {
            $email = $base.$counter.'@gmail.com';
            $counter++;
        }

        return $email;
    }

    private function birthDate(int $courseId, int $index): string
    {
        $ages = [
            1 => 17, 2 => 16, 3 => 15, 4 => 14, 5 => 13,
            6 => 11, 7 => 10, 8 => 9, 9 => 8, 10 => 7, 11 => 6,
            12 => 5, 13 => 4, 14 => 3,
        ];

        $age = $ages[$courseId] ?? 12;
        $year = Carbon::now()->year - $age;
        $month = (($courseId + $index) % 12) + 1;
        $day = (($courseId * 3 + $index) % 27) + 1;

        return sprintf('%04d-%02d-%02d', $year, $month, $day);
    }

    private function momentLabel(?Lapse $lapse): string
    {
        $ordinals = [1 => '1er', 2 => '2do', 3 => '3er'];

        return ($ordinals[$lapse?->number] ?? $lapse?->number).' Momento';
    }

    private function buildSearch(Student $student): string
    {
        $student->loadMissing('representative.user', 'course', 'section');

        return trim(
            ($student->representative?->user?->name ?? '').' '.
            ($student->representative?->user?->last_name ?? '').' '.
            ($student->course?->name ?? '').' '.
            ($student->section?->name ?? '').' '.
            $student->name.' '.$student->last_name.' '.$student->date_birth.' '.
            $student->email.' '.$student->ci.' '.$student->phone_number
        );
    }
}
