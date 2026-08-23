<?php

namespace App\Services;

use App\Enums\UserTypeEnum;
use App\Events\ReEnrollEvent;
use App\Events\StudentCreated;
use App\Events\StudentUpdated;
use App\Http\Resources\StudentCollection;
use App\Models\Inscription;
use App\Models\Representative;
use App\Models\SchoolLapse;
use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class StudentService
{
    private Student $studentModel;

    public function __construct()
    {
        $this->studentModel = new Student;
    }

    public function getStudentsPerCourse($request)
    {
        $courseId = $request->input('course_id') ?? 1;
        $sectionId = $request->input('section_id') ?? 1;

        $students = Student::query()
            ->where('status', '!=', 0)
            ->where('course_id', $courseId)
            ->where('section_id', $sectionId)
            ->when($request->input('search'), function ($query, $search) {
                $query->where('search', 'like', '%'.$search.'%');
                $query->orWhere('ci', 'like', '%'.$search.'%')
                    ->orWhere('name', 'like', '%'.$search.'%')
                    ->orWhere('last_name', 'like', '%'.$search.'%')
                    ->orWhereRaw("CONCAT(name, ' ', last_name) LIKE ?", ['%'.$search.'%']);
                $query->orWhereHas('representative.user', function ($q) use ($search) {
                    $q->where('name', 'like', '%'.$search.'%')
                        ->orWhere('last_name', 'like', '%'.$search.'%')
                        ->orWhere('ci', 'like', '%'.$search.'%');
                });
            })

            ->with('representative.user', 'course', 'section')
            ->get();

        $studentsCollection = new StudentCollection($students);

        return $studentsCollection;
    }

    public function create($request)
    {
        $data = $request->all();

        $existingDeleted = $this->searchDeletedStudentByCI($data['student_ci'] ?? null, $data['student_document_type'] ?? null);

        if ($existingDeleted) {
            $this->reactivateAndUpdate($data, $existingDeleted);

            return 0;
        }

        $user = User::where('ci', $data['rep_ci'])->first();

        Log::info('Creating student with data: ', $data);
        Log::info('Found user: ', ['user' => $user]);

        if (! isset($user->id)) {
            $user = $this->createUser($data);
        }

        $representative = Representative::where('user_id', $user->id)->first();

        if (! isset($representative->id)) {
            $representative = $this->createRepresentative($data, $user->id);
        }

        $student = $this->createStudent($data, $representative->id);

        $student->load('representative.user', 'course', 'section');

        // $this->createDocuments($request,$student->id);

        event(new StudentCreated($student));

        return 0;
    }

    public function searchDeletedStudentByCI($ci, $documentType = null)
    {
        if (! $ci) {
            return null;
        }

        return Student::where('status', 0)
            ->where('ci', $ci)
            ->when($documentType, function ($query) use ($documentType) {
                $query->where('document_type', $documentType);
            })
            ->with('representative.user', 'course', 'section')
            ->first();
    }

    private function reactivateAndUpdate($data, $student)
    {
        if ($student->graduate) {
            throw new \Exception('El estudiante está marcado como graduado y no puede reinscribirse.');
        }

        $user = User::where('ci', $data['rep_ci'])->first();

        if (! isset($user->id)) {
            $user = $this->createUser($data);
        }

        $representative = Representative::where('user_id', $user->id)->first();

        if (! isset($representative->id)) {
            $representative = $this->createRepresentative($data, $user->id);
        }

        $student->update([
            'representative_id' => $representative->id,
            'course_id' => $data['course_id'],
            'section_id' => $data['section_id'],
            'name' => $data['student_name'],
            'last_name' => $data['student_last_name'],
            'date_birth' => $data['student_date_birth'],
            'email' => $data['student_email'] ?? null,
            'ci' => $data['student_ci'] ?? null,
            'phone_number' => $data['student_phone_number'] ?? null,
            'sex' => $data['student_sex'] ?? null,
            'previous_school' => $data['student_previous_school'] ?? null,
            'is_exempt' => $data['is_exempt'] ?? false,
            'exemption_percentage' => $data['exemption_percentage'] ?? null,
            'exemption_observations' => $data['exemption_observations'] ?? null,
            'document_type' => $data['student_document_type'] ?? null,
            'apply_to_past_debts' => $data['apply_to_past_debts'] ?? false,
            'status' => 1,
            'graduate' => false,
        ]);

        $student->load('representative.user', 'course', 'section');

        $search = $this->generateSearch($student);
        $student->update(['search' => $search]);

        event(new StudentCreated($student));

        return 0;
    }

    public function reEnroll($data)
    {
        $student = Student::where('id', $data['student_id'])->first();

        if (! isset($student->id)) {
            throw new \Exception('Estudiante no encontrado');
        }

        if ($student->course_id == 1) { // 5to año, se gradúa
            $student->update([
                'graduate' => true,
                'status' => 0,
            ]);

            return 0;
        }

        $latestInscription = Inscription::where('student_id', $student->id)->latest()->first();

        $schoolLapse = SchoolLapse::where('id', $latestInscription->school_lapse_id)->first();

        if ($schoolLapse->status != 0) {
            throw new \Exception('No se puede reinscribir al estudiante porque el período escolar actual no está cerrado.');
        }

        $previousCourseId = $student->course_id;

        $student->update([
            'course_id' => $data['course_id'],
            'section_id' => $data['section_id'],
        ]);

        $student->load('representative.user', 'course', 'section');

        event(new ReEnrollEvent($student));

        return 0;
    }

    public function update($request, $studentId)
    {
        $data = $request->validated();

        Log::info('Updating data: ', $data);

        $representative = Representative::where('id', $data['rep_id'])->first();

        // Log::info('Updating representative with ID: ' . $representative->id);

        if (! $representative) {
            throw ValidationException::withMessages([
                'rep_id' => 'Representante no encontrado en nuestra base de datos.',
            ]);
        }

        $representative->update([

            'profession' => $data['rep_profession'] ?? null,
            'workplace' => $data['rep_workplace'] ?? null,
            'document_type' => $data['rep_document_type'] ?? null,
            'second_representative_name' => $data['second_rep_name'] ?? null,
            'second_representative_last_name' => $data['second_rep_last_name'] ?? null,
            'second_representative_ci' => $data['second_rep_ci'] ?? null,
            'second_representative_phone_number' => $data['second_rep_phone_number'] ?? null,
            'second_representative_phone_number2' => $data['second_rep_phone_number2'] ?? null,
            'second_representative_email' => $data['second_rep_email'] ?? null,
            'second_representative_profession' => $data['second_rep_profession'] ?? null,
            'second_representative_workplace' => $data['second_rep_workplace'] ?? null,
            'second_document_type' => $data['second_rep_document_type'] ?? null,
        ]);

        $user = User::where('id', $representative->user_id)->first();

        if (! isset($user->id)) {
            throw new \Exception('No se encontró el usuario asociado al representante');
        }

        $user->update([
            'name' => $data['rep_name'],
            'last_name' => $data['rep_last_name'],
            'ci' => $data['rep_ci'],
            'phone_number' => $data['rep_phone_number'],
            'phone_number2' => $data['rep_phone_number2'] ?? null,
            'email' => $data['rep_email'] ?? null,
            'password' => Hash::make($data['rep_ci']),
            'address' => $data['address'] ?? null,
            'state' => $data['state'] ?? null,
            'city' => $data['city'] ?? null,
            'document_type' => $data['rep_document_type'] ?? null,
        ]);

        $student = Student::where('id', $studentId)->first();

        if (! isset($student->id)) {
            throw new \Exception('Estudiante no encontrado');
        }

        $previousCourseId = $student->course_id;

        $previousExemptData = [
            'is_exempt' => $student->is_exempt,
            'exemption_percentage' => $student->exemption_percentage,
        ];

        $student->update([

            'representative_id' => $representative->id,
            'apply_to_past_debts' => $data['apply_to_past_debts'] ?? false,
            'course_id' => $data['course_id'],
            'section_id' => $data['section_id'],
            'name' => $data['student_name'],
            'last_name' => $data['student_last_name'],
            'date_birth' => $data['student_date_birth'],
            'email' => $data['student_email'] ?? null,
            'ci' => $data['student_ci'] ?? null,
            'phone_number' => $data['student_phone_number'] ?? null,
            'sex' => $data['student_sex'] ?? null,
            'previous_school' => $data['student_previous_school'] ?? null,
            'is_exempt' => $data['is_exempt'] ?? false,
            'exemption_percentage' => $data['exemption_percentage'] ?? null,
            'exemption_observations' => $data['exemption_observations'] ?? null,
            'document_type' => $data['student_document_type'] ?? null,
        ]);

        $student->load('representative.user', 'course', 'section');

        $search = $this->generateSearch($student);
        $student->update(['search' => $search]);

        event(new StudentUpdated($previousCourseId, $student, $previousExemptData));

        return 0;
    }

    private function createUser($data)
    {

        $newUser = User::create([
            'type_user_id' => UserTypeEnum::Representative->value,
            'name' => $data['rep_name'],
            'last_name' => $data['rep_last_name'],
            'ci' => $data['rep_ci'],
            'phone_number' => $data['rep_phone_number'] ?? null,
            'phone_number2' => $data['rep_phone_number2'] ?? null,
            'email' => $data['rep_email'] ?? null,
            'password' => Hash::make($data['rep_ci']),
            'address' => $data['address'] ?? null,
            'state' => $data['state'] ?? null,
            'city' => $data['city'] ?? null,
            'document_type' => $data['rep_document_type'] ?? null,
        ]);

        return $newUser;
    }

    private function createRepresentative($data, $userId)
    {
        $newRepresentative = Representative::create([

            'user_id' => $userId,
            'profession' => $data['rep_profession'] ?? null,
            'workplace' => $data['rep_workplace'] ?? null,
            'relationship' => $data['rep_relationship'] ?? null,
            'document_type' => $data['rep_document_type'] ?? null,
            'second_representative_relationship' => $data['second_rep_relationship'] ?? null,
            'second_representative_name' => $data['second_rep_name'] ?? null,
            'second_representative_last_name' => $data['second_rep_last_name'] ?? null,
            'second_representative_ci' => $data['second_rep_ci'] ?? null,
            'second_representative_phone_number' => $data['second_rep_phone_number'] ?? null,
            'second_representative_phone_number2' => $data['second_rep_phone_number2'] ?? null,
            'second_representative_email' => $data['second_rep_email'] ?? null,
            'second_representative_profession' => $data['second_rep_profession'] ?? null,
            'second_representative_workplace' => $data['second_rep_workplace'] ?? null,
            'second_document_type' => $data['second_rep_document_type'] ?? null,
        ]);

        return $newRepresentative;
    }

    private function createStudent($data, $representativeId)
    {
        $newStudent = Student::create([

            'representative_id' => $representativeId,
            'course_id' => $data['course_id'],
            'section_id' => $data['section_id'],
            'name' => $data['student_name'],
            'last_name' => $data['student_last_name'],
            'date_birth' => $data['student_date_birth'],
            'email' => $data['student_email'] ?? null,
            'ci' => $data['student_ci'] ?? null,
            'phone_number' => $data['student_phone_number'] ?? null,
            'sex' => $data['student_sex'] ?? null,
            'previous_school' => $data['student_previous_school'] ?? null,
            'photo' => 'guest.webp',
            'is_exempt' => $data['is_exempt'] ?? false,
            'exemption_percentage' => $data['exemption_percentage'] ?? null,
            'exemption_observations' => $data['exemption_observations'] ?? null,
            'document_type' => $data['student_document_type'] ?? null,
        ]);

        $newStudent->load('representative.user', 'course', 'section');

        $search = $this->generateSearch($newStudent);

        $newStudent->update(['search' => $search]);

        return $newStudent;
    }

    public function searchStudent($search, $id = null)
    {
        if (isset($id)) {
            $students = Student::where('id', $id)
                ->where('status', '!=', 0)
                ->with([
                    'representative.user',
                    'course',
                    'section',
                    'balances' => function ($query) {
                        // Traemos los que tengan status específicos O el más reciente
                        $query->with('schoolLapse')
                            ->oldest(); // Ordenar por fecha de creación (el más antiguo primero)
                    },
                ])
                ->get()
                ->map(function ($student) {
                    if ($student->balances->isEmpty()) {
                        $student->setRelation('balances', $student->balances()->latest()->take(1)->get());
                    }

                    return $student;
                });

            return $students;
        }

        $students = Student::where('status', '!=', 0)
            ->where(function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('ci', 'LIKE', '%'.$search.'%')
                        ->orWhere('name', 'LIKE', '%'.$search.'%')
                        ->orWhere('last_name', 'LIKE', '%'.$search.'%')
                        ->orWhereRaw("CONCAT(name, ' ', last_name) LIKE ?", ['%'.$search.'%']);
                })
                    ->orWhereHas('representative.user', function ($q) use ($search) {
                        $q->where('name', 'LIKE', '%'.$search.'%')
                            ->orWhere('last_name', 'LIKE', '%'.$search.'%')
                            ->orWhere('ci', 'LIKE', '%'.$search.'%')
                            ->orWhereRaw("CONCAT(name, ' ', last_name) LIKE ?", ['%'.$search.'%']);
                    });
            })
            ->get()
            ->map(function ($student) {
                if ($student->balances->isEmpty()) {
                    $student->setRelation('balances', $student->balances()->latest()->take(1)->get());
                }

                return $student;
            });

        return $students;
    }

    public function searchRepresentativeByCI($ci)
    {
        $user = User::where('ci', $ci)->where('type_user_id', 2)->first();

        if (! isset($user->id)) {
            return null;
        }

        $representative = Representative::where('user_id', $user->id)->first();

        if (! isset($representative->id)) {
            return null;
        }

        $data =
            [

                'rep_id' => $representative->id,
                'rep_name' => $user->name,
                'rep_last_name' => $user->last_name,
                'rep_ci' => $user->ci,
                'rep_document_type' => $user->document_type ?? null,
                'rep_phone_number' => $user->phone_number,
                'rep_phone_number2' => $user->phone_number2 ?? null,
                'rep_email' => $user->email ?? null,
                'rep_profession' => $representative->profession ?? null,
                'rep_workplace' => $representative->workplace ?? null,
                'address' => $user->address ?? null,
                'state' => $user->state ?? null,
                'city' => $user->city ?? null,

            ];

        return $data;
    }

    public function searchSecondRepresentativeByCI($ci)
    {
        $user = User::where('ci', $ci)->where('type_user_id')->first();

        if (! isset($user->id)) {
            return response()->json(['data' => null]);
        }

        $representative = Representative::where('user_id', $user->id)->first();

        if (! isset($representative->id)) {
            return response()->json(['data' => null]);
        }

        $data =
            [

                'second_rep_name' => $representative->second_representative_name ?? null,
                'second_rep_last_name' => $representative->second_representative_last_name ?? null,
                'second_rep_ci' => $representative->second_representative_ci ?? null,
                'second_rep_document_type' => $representative->second_document_type ?? null,
                'second_rep_phone_number' => $representative->second_representative_phone_number ?? null,
                'second_rep_phone_number2' => $representative->second_representative_phone_number2 ?? null,
                'second_rep_email' => $representative->second_representative_email ?? null,
                'second_rep_profession' => $representative->second_representative_profession ?? null,
                'second_rep_workplace' => $representative->second_representative_workplace ?? null,

            ];

        return $data;
    }

    public function searchRepresentative($search)
    {
        $user = User::where('type_user_id', 2)
            ->whereRaw('LOWER(name) LIKE ?', ['%'.strtolower($search).'%'])
            ->orWhereRaw('LOWER(last_name) LIKE ?', ['%'.strtolower($search).'%'])
            ->orWhereRaw('LOWER(ci) LIKE ?', ['%'.strtolower($search).'%'])
            ->with('representative')
            ->get();

        return $user;
    }

    public function delete($studentId)
    {
        $student = Student::find($studentId);

        if (! $student) {
            throw new \Exception('Estudiante no encontrado');
        }

        $student->update(['status' => 0]);

        return 0;
    }

    private function generateSearch($student)
    {
        $repName = $student->representative?->user?->name ?? '';
        $repLastName = $student->representative?->user?->last_name ?? '';
        $courseName = $student->course?->name ?? '';
        $sectionName = $student->section?->name ?? '';

        return trim($repName.' '.$repLastName.' '.$courseName.' '.$sectionName.' '
            .$student->name.' '.$student->last_name.' '.$student->date_birth.' '
            .$student->email.' '.$student->ci.' '.$student->phone_number.' '
            .$student->sex.' '.$student->previous_school);
    }
}
