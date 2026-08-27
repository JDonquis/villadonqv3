<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreateStudentRequest;
use App\Http\Requests\ReEnrollStudentRequest;
use App\Http\Requests\StoreDocumentRequest;
use App\Http\Requests\UpdateStudentRequest;
use App\Http\Resources\CourseSectionCollection;
use App\Http\Resources\StudentResource;
use App\Models\Course;
use App\Models\CourseSection;
use App\Models\Section;
use App\Models\Student;
use App\Services\StudentService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class StudentController extends Controller
{
    private StudentService $studentService;

    public function __construct()
    {
        $this->studentService = new StudentService;
    }

    public function index(Request $request)
    {

        $courses = Course::all();
        $sections = Section::all();

        $course_sections = new CourseSectionCollection(CourseSection::with('section', 'course')->get());
        $studentsPerCourse = $this->studentService->getStudentsPerCourse($request);

        return inertia(
            'Dashboard/Matricula',
            [
                'data' => [
                    'courses' => $courses,
                    'sections' => $sections,
                    'course_sections' => $course_sections,
                    'students' => $studentsPerCourse,
                    'filters' => [
                        'course_id' => $request->input('course_id') ?? 1,
                        'section_id' => $request->input('section_id') ?? 1,
                        'search' => $request->input('search') ?? null,
                    ],
                ],

            ]
        );
    }

    public function store(CreateStudentRequest $request)
    {
        DB::beginTransaction();

        try {
            $transport = Mail::mailer()->getSymfonyTransport();
            if (method_exists($transport, 'start')) {
                $transport->start();
            }
        } catch (Exception $e) {
            Log::error('Error de configuración/credenciales de correo: '.$e->getMessage());

            return back()->withErrors([
                'message' => 'No se pudo conectar con el servidor de correo. Verifique las credenciales SMTP.',
            ]);
        }

        try {

            $this->studentService->create($request);

            DB::commit();

            return redirect('/dashboard/matricula?course_id='.$request->course_id.'&section_id='.$request->section_id);
        } catch (Exception $e) {

            DB::rollback();

            Log::error('Error al crear estudiante: '.$e->getMessage());

            return redirect('/dashboard/matricula?course_id='.$request->course_id.'&section_id='.$request->section_id)->withErrors(['message' => $e->getMessage()]);
        }
    }

    public function update(UpdateStudentRequest $request, $id)
    {

        DB::beginTransaction();

        try {

            Log::info('Iniciando actualización de estudiante ID: '.$id);

            $this->studentService->update($request, $id);

            DB::commit();

            return redirect('/dashboard/matricula?course_id='.$request->course_id.'&section_id='.$request->section_id);
        } catch (Exception $e) {

            DB::rollback();

            Log::error('Error al actualizar estudiante ID '.$id.': '.$e->getMessage());

            return redirect('/dashboard/matricula?course_id='.$request->course_id.'&section_id='.$request->section_id)->withErrors(['message' => $e->getMessage()]);
        }
    }

    public function destroy(Request $request, $studentId)
    {
        try {
            Log::info('Iniciando eliminación de estudiante ID: '.$studentId);

            $this->studentService->delete($studentId);

            Log::info('Estudiante ID '.$studentId.' eliminado correctamente');

            return redirect('/dashboard/matricula');
        } catch (Exception $e) {
            Log::error('Error al eliminar estudiante ID '.$studentId.': '.$e->getMessage());

            return redirect('/dashboard/matricula')->withErrors(['message' => 'Ha ocurrido un error al eliminar el estudiante. Por favor, intente más tarde.']);
        }
    }

    public function reEnrollment(ReEnrollStudentRequest $request)
    {
        DB::beginTransaction();

        try {
            $this->studentService->reEnroll($request->validated());

            DB::commit();

            return redirect('/dashboard/matricula');
        } catch (Exception $e) {
            DB::rollback();

            Log::error('Error al reinscribir estudiante ID '.$request->student_id.': '.$e->getMessage());

            return redirect()->back()->withErrors(['status' => false,  'message' => $e->getMessage()]);
        }
    }

    public function show($id)
    {
        $student = Student::with('representative.user', 'course', 'section')->findOrFail($id);

        return inertia('Dashboard/DetalleEstudiante', [
            'data' => $this->studentService->getStudentDetails($student),
        ]);
    }

    public function storeDocument(StoreDocumentRequest $request)
    {
        try {
            $this->studentService->storeDocument($request);

            return redirect()->back();
        } catch (Exception $e) {
            Log::error('Error al adjuntar documento: '.$e->getMessage());

            return redirect()->back()->withErrors(['message' => $e->getMessage()]);
        }
    }

    public function destroyDocument($id)
    {
        try {
            $this->studentService->destroyDocument($id);

            return redirect()->back();
        } catch (Exception $e) {
            Log::error('Error al eliminar documento ID '.$id.': '.$e->getMessage());

            return redirect()->back()->withErrors(['message' => $e->getMessage()]);
        }
    }

    public function searchStudent(Request $request)
    {
        $search = $request->input('search');
        $id = $request->input('id');
        $info = $this->studentService->searchStudent($search, $id);

        return response()->json($info);
    }

    public function searchDeletedStudent($ci)
    {
        $student = $this->studentService->searchDeletedStudentByCI($ci);

        if (! $student) {
            return response()->json(['found' => false, 'student' => null]);
        }

        return response()->json([
            'found' => true,
            'student' => new StudentResource($student),
            'graduate' => (bool) $student->graduate,
        ]);
    }

    public function searchRepresentativeByCI($ci)
    {
        $info = $this->studentService->searchRepresentativeByCI($ci);

        return response()->json($info);
    }

    public function searchSecondRepresentativeByCI($ci)
    {
        $info = $this->studentService->searchSecondRepresentativeByCI($ci);

        return response()->json($info);
    }

    public function searchRepresentative($search)
    {
        $info = $this->studentService->searchRepresentative($search);

        return response()->json($info);
    }
}
