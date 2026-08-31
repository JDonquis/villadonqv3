<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Services\ExcelTemplateService;
use App\Services\LoginService;
use App\Services\UserService;
use App\Support\ErrorTranslator;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class UserController extends Controller
{
    private LoginService $loginService;

    private UserService $userService;

    public function __construct()
    {
        $this->loginService = new LoginService;
        $this->userService = new UserService;
    }

    public function index(Request $request)
    {

        $users = $this->userService->getUsers($request->all());

        return inertia('Dashboard/Personal', [
            'data' => $users,
            'filters' => [
                'search' => $request->input('search') ?? null,
            ],
        ]);
    }

    public function downloadTemplate()
    {
        return app(ExcelTemplateService::class)->user();
    }

    public function import(Request $request)
    {
        $request->validate(['file' => ['required', 'file', 'mimes:xlsx', 'max:20480']]);

        try {
            $rows = (new ExcelTemplateService)->readRows($request->file('file')->getRealPath());
            $result = $this->userService->importUsers($rows);

            if ($request->wantsJson()) {
                return response()->json($result);
            }

            return back()->with('import_summary', $result);
        } catch (Exception $e) {
            Log::error('Error al importar personal: '.$e->getMessage());

            if ($request->wantsJson()) {
                return response()->json(['error' => 'No se pudo importar el archivo: '.ErrorTranslator::translate($e)], 422);
            }

            return back()->withErrors(['import' => 'No se pudo importar el archivo: '.ErrorTranslator::translate($e)]);
        }
    }

    public function store(StoreUserRequest $request)
    {
        try {
            $transport = Mail::mailer()->getSymfonyTransport();
            if (method_exists($transport, 'start')) {
                $transport->start();
            }
        } catch (Exception $e) {
            Log::error('Error de configuración/credenciales de correo: '.$e->getMessage());

            return back()->withInput()->withErrors([
                'message' => 'No se pudo conectar con el servidor de correo. Verifique las credenciales SMTP.',
            ]);
        }

        try {
            $data = $request->validated();

            $randomPassword = bin2hex(random_bytes(8));
            $data['password'] = bcrypt($randomPassword);

            $user = $this->userService->createUser($data);
            $this->userService->sendPasswordSetupEmail($user);

            return to_route('personal.index')->with([
                'status' => true,
                'message' => 'Usuario creado exitosamente. Se ha enviado un correo para establecer la contraseña.',
            ]);
        } catch (Exception $e) {
            Log::error('Error al crear usuario: '.$e->getMessage());

            return back()->withInput()->withErrors([
                'status' => false,
                'message' => ErrorTranslator::translate($e),
            ]);
        }
    }

    public function show(int $id)
    {
        $user = $this->userService->getUserById($id);

        if (! $user) {
            return redirect()->back()->withErrors([
                'message' => 'El usuario solicitado no existe.',
            ]);
        }

        return response()->json([
            'status' => true,
            'message' => 'OK',
            'data' => $user,
        ], 200);
    }

    public function update(UpdateUserRequest $request, int $id)
    {
        $user = $this->userService->getUserById($id);

        if (! $user) {
            return response()->json([
                'status' => false,
                'message' => 'Usuario no encontrado',
            ], 404);
        }

        $data = $request->validated();

        if (isset($data['password'])) {
            $data['password'] = bcrypt($data['password']);
        }

        $user = $this->userService->updateUser($user, $data);

        return to_route('personal.index')->with([
            'status' => true,
            'message' => 'Usuario actualizado exitosamente',
        ]);
    }

    public function destroy(int $id)
    {
        $user = $this->userService->getUserById($id);

        if (! $user) {
            return redirect()->back()->withErrors([
                'message' => 'El usuario solicitado no existe.',
            ]);
        }

        $this->userService->deleteUser($user);

        return to_route('personal.index')->with([
            'status' => true,
            'message' => 'Usuario eliminado exitosamente',
        ]);
    }

    public function resendSetupEmail(int $id)
    {
        $user = $this->userService->getUserById($id);

        if (! $user) {
            return back()->withErrors([
                'message' => 'El usuario solicitado no existe.',
            ]);
        }

        if (empty($user->email)) {
            return back()->withErrors([
                'message' => 'El usuario no tiene un correo electrónico registrado.',
            ]);
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
            $this->userService->sendPasswordSetupEmail($user);

            return back()->with([
                'status' => true,
                'message' => "Correo de configuración de contraseña reenviado a {$user->email}.",
            ]);
        } catch (Exception $e) {
            Log::error('Error al reenviar el correo de configuración: '.$e->getMessage());

            return back()->withErrors([
                'message' => 'No se pudo enviar el correo. Por favor, intente nuevamente.',
            ]);
        }
    }
}
