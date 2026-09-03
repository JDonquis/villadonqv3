<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTeacherRequest;
use App\Http\Requests\UpdateTeacherRequest;
use App\Models\Matter;
use App\Models\User;
use App\Services\ExcelTemplateService;
use App\Services\TeacherService;
use App\Services\UserService;
use App\Support\ErrorTranslator;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class TeacherController extends Controller
{
    private TeacherService $teacherService;

    private UserService $userService;

    public function __construct()
    {
        $this->teacherService = new TeacherService;
        $this->userService = new UserService;
    }

    public function index(Request $request)
    {
        $matters = Matter::orderBy('name')->get()->map(fn ($m) => [
            'id' => $m->id,
            'name' => $m->name,
        ])->values();

        return inertia('Dashboard/Profesores', [
            'data' => [
                'teachers' => $this->teacherService->getTeachers($request->input('search')),
                'matters' => $matters,
            ],
            'filters' => [
                'search' => $request->input('search') ?? null,
            ],
        ]);
    }

    public function downloadTemplate()
    {
        return app(ExcelTemplateService::class)->teacher();
    }

    public function import(Request $request)
    {
        $request->validate(['file' => ['required', 'file', 'mimes:xlsx', 'max:20480']]);

        try {
            $rows = (new ExcelTemplateService)->readRows($request->file('file')->getRealPath());
            $result = $this->teacherService->importTeachers($rows);

            if ($request->wantsJson()) {
                return response()->json($result);
            }

            return back()->with('import_summary', $result);
        } catch (Exception $e) {
            Log::error('Error al importar profesores: '.$e->getMessage());

            if ($request->wantsJson()) {
                return response()->json(['error' => 'No se pudo importar el archivo: '.ErrorTranslator::translate($e)], 422);
            }

            return back()->withErrors(['import' => 'No se pudo importar el archivo: '.ErrorTranslator::translate($e)]);
        }
    }

    public function store(StoreTeacherRequest $request)
    {
        $data = $request->validated();

        $randomPassword = bin2hex(random_bytes(8));
        $data['password'] = bcrypt($randomPassword);

        try {
            $teacher = $this->teacherService->createTeacher($data);
            $this->userService->sendPasswordSetupEmail($teacher);

            return back()->with([
                'status' => true,
                'message' => 'Profesor creado exitosamente. Se ha enviado un correo para establecer la contraseña.',
            ]);
        } catch (Exception $e) {
            Log::error('Error al crear profesor: '.$e->getMessage());

            return back()->withInput()->withErrors([
                'status' => false,
                'message' => ErrorTranslator::translate($e),
            ]);
        }
    }

    public function update(UpdateTeacherRequest $request, $id)
    {
        $teacher = User::findOrFail($id);

        if (! $teacher->isTeacher()) {
            return back()->withErrors(['message' => 'El usuario no es un profesor.']);
        }

        try {
            $this->teacherService->updateTeacher($teacher, $request->validated());

            return back()->with([
                'status' => true,
                'message' => 'Profesor actualizado exitosamente.',
            ]);
        } catch (Exception $e) {
            Log::error('Error al actualizar profesor ID '.$id.': '.$e->getMessage());

            return back()->withErrors([
                'status' => false,
                'message' => ErrorTranslator::translate($e),
            ]);
        }
    }

    public function destroy($id)
    {
        $teacher = User::findOrFail($id);

        if (! $teacher->isTeacher()) {
            return back()->withErrors(['message' => 'El usuario no es un profesor.']);
        }

        try {
            $this->teacherService->deleteTeacher($teacher);

            return back()->with([
                'status' => true,
                'message' => 'Profesor eliminado exitosamente.',
            ]);
        } catch (Exception $e) {
            Log::error('Error al eliminar profesor ID '.$id.': '.$e->getMessage());

            return back()->withErrors(['message' => ErrorTranslator::translate($e)]);
        }
    }

    public function resendSetupEmail($id)
    {
        $teacher = User::findOrFail($id);

        if (! $teacher->isTeacher()) {
            return back()->withErrors(['message' => 'El usuario no es un profesor.']);
        }

        if (empty($teacher->email)) {
            return back()->withErrors(['message' => 'El profesor no tiene un correo electrónico registrado.']);
        }

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
            $this->userService->sendPasswordSetupEmail($teacher);

            return back()->with([
                'status' => true,
                'message' => "Correo de configuración de contraseña reenviado a {$teacher->email}.",
            ]);
        } catch (Exception $e) {
            Log::error('Error al reenviar el correo de configuración: '.$e->getMessage());

            return back()->withErrors([
                'message' => 'No se pudo enviar el correo. Por favor, intente nuevamente.',
            ]);
        }
    }
}
