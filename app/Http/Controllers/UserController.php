<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Services\LoginService;
use App\Services\UserService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

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

    public function store(StoreUserRequest $request)
    {
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
            Log::error("Error al crear usuario: " . $e->getMessage());

            return back()->withInput()->withErrors([
                'status' => false,
                'message' => $e->getMessage() ?: 'Ha ocurrido un error al crear el usuario. Por favor, intente nuevamente.',
            ]);
        }
    }

    public function show(int $id)
    {
        $user = $this->userService->getUserById($id);

        if (! $user) {
            return redirect()->back()->withErrors([
                'message' => 'El usuario solicitado no existe.'
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
                'message' => 'El usuario solicitado no existe.'
            ]);
        }

        $this->userService->deleteUser($user);

        return to_route('personal.index')->with([
            'status' => true,
            'message' => 'Usuario eliminado exitosamente',
        ]);
    }
}
