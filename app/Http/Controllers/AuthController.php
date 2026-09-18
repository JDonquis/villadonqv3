<?php

namespace App\Http\Controllers;

use App\Enums\UserTypeEnum;
use App\Http\Requests\ForgotPasswordRequest;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\SetupPasswordRequest;
use App\Models\PasswordSetupToken;
use App\Models\User;
use App\Services\LoginService;
use App\Services\UserService;
use App\Support\ErrorTranslator;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Laravel\Socialite\Facades\Socialite;

class AuthController extends Controller
{
    private LoginService $loginService;

    private UserService $userService;

    public function __construct()
    {
        $this->loginService = new LoginService;
        $this->userService = new UserService;
    }

    public function login(LoginRequest $request)
    {
        $dataUser = ['email' => $request->email, 'password' => trim($request->password)];
        Log::info('Login attempt for user: '.$request->email);
        if (! $this->loginService->tryLoginOrFail($dataUser)) {
            return redirect('/')->withErrors(['data' => 'Datos incorrectos, intente nuevamente']);
        }

        $user = auth()->user();
        $dataUser = $user->toArray();

        $token = $this->loginService->generateToken($dataUser);
        $permissionsArray = $this->userService->getPermissions($user->id);
        $permissionsWithFormat = $this->userService->formatToPermissions($permissionsArray);

        return Inertia::location($this->roleRedirect($user));
    }

    public function logout(Request $request)
    {
        Auth::logout();

        return redirect()->route('login');
    }

    public function redirectToGoogle()
    {
        return Socialite::driver('google')->redirect();
    }

    public function handleGoogleCallback()
    {
        try {
            $googleUser = Socialite::driver('google')->user();
        } catch (\Throwable $e) {
            Log::error('Google login callback error: '.$e->getMessage());

            return redirect('/')->withErrors(['data' => 'No se pudo iniciar sesión con Google. Intenta nuevamente.']);
        }

        $user = User::where('email', mb_strtolower($googleUser->getEmail()))->first();

        if (! $user) {
            return redirect('/')->withErrors(['data' => 'No existe una cuenta registrada con este correo. Contacta al administrador.']);
        }

        if (! $user->provider) {
            $user->provider = 'google';
            $user->provider_id = $googleUser->getId();
            $user->save();
        }

        Auth::login($user);

        $this->loginService->generateToken($user->toArray());

        return Inertia::location($this->roleRedirect($user));
    }

    private function roleRedirect(User $user)
    {
        return match ($user->type_user_id) {
            UserTypeEnum::Representative->value => '/dashboard/mis-hijos',
            UserTypeEnum::Teacher->value => '/dashboard/mis-planes',
            default => '/dashboard',
        };
    }

    public function changePassword(Request $request)
    {
        $data = [
            'oldPassword' => $request->oldPassword,
            'newPassword' => $request->newPassword,
            'confirmPassword' => $request->confirmPassword,
        ];

        try {
            $this->loginService->tryChangePassword($data);

            return response()->json([
                'status' => true,
                'message' => 'Contraseña cambiada',
            ], 200);
        } catch (Exception $e) {

            return response()->json([
                'status' => false,
                'message' => ErrorTranslator::translate($e),
            ], 500);
        }
    }

    public function username()
    {
        return 'ci';
    }

    public function failLogin()
    {
        return 'No tiene los permisos para ingresar a esta url';
    }

    public function showForgotPassword()
    {
        return inertia('ForgotPassword');
    }

    public function requestResetPassword(ForgotPasswordRequest $request)
    {
        $email = $request->email;
        $user = User::where('email', $email)->first();

        if ($user && $user->email) {
            $this->userService->sendPasswordResetEmail($user);
        }

        return back()->with('success', 'Si el correo está registrado, recibirás un enlace para restablecer tu contraseña.');
    }

    public function showSetupPassword(Request $request)
    {
        $token = $request->query('token');

        if (! $token) {
            return redirect('/')->with('error', 'Token no proporcionado');
        }

        $tokenRecord = PasswordSetupToken::findValidToken($token);

        if (! $tokenRecord) {
            return redirect('/')->with('error', 'Token inválido o expirado');
        }

        return inertia('SetupPassword', ['token' => $token]);
    }

    public function setupPassword(SetupPasswordRequest $request)
    {
        $tokenRecord = PasswordSetupToken::findValidToken($request->token);

        if (! $tokenRecord) {
            return back()->with('error', 'Token inválido o expirado');
        }

        $user = $tokenRecord->user;
        $user->password = bcrypt($request->password);
        $user->save();

        $tokenRecord->markAsUsed();

        return redirect('/')->with('success', 'Contraseña establecida exitosamente. Ahora puedes iniciar sesión.');
    }
}
