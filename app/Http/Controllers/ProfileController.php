<?php

namespace App\Http\Controllers;

use App\Enums\UserTypeEnum;
use App\Http\Requests\ChangePasswordRequest;
use App\Http\Requests\UpdateProfileRequest;
use App\Models\Representative;
use App\Services\UserService;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Inertia\Response;

class ProfileController extends Controller
{
    private UserService $userService;

    public function __construct()
    {
        $this->userService = new UserService;
    }

    public function index(): Response
    {
        $user = auth()->user();
        $representative = Representative::where('user_id', $user->id)->first();

        return inertia('Dashboard/Perfil', [
            'data' => [
                'name' => $user->name,
                'last_name' => $user->last_name,
                'ci' => $user->ci,
                'document_type' => $user->document_type,
                'email' => $user->email,
                'phone_number' => $user->phone_number,
                'phone_number2' => $user->phone_number2,
                'address' => $user->address,
                'state' => $user->state,
                'city' => $user->city,
                'photo' => $user->photo,
                'photo_url' => $this->photoUrl($user->photo),
                'is_representative' => (int) $user->type_user_id === UserTypeEnum::Representative->value,
                'profession' => $representative?->profession,
                'workplace' => $representative?->workplace,
                'relationship' => $representative?->relationship,
            ],
        ]);
    }

    public function update(UpdateProfileRequest $request)
    {
        $user = auth()->user();
        $data = $request->validated();

        if ($request->hasFile('photo')) {
            $file = $request->file('photo');
            $name = Str::random(25).'.'.$file->extension();
            $file->move(public_path('img/photos'), $name);
            $data['photo'] = $name;
        } else {
            unset($data['photo']);
        }

        $isRepresentative = (int) $user->type_user_id === UserTypeEnum::Representative->value;

        if ($isRepresentative) {
            $representative = Representative::where('user_id', $user->id)->first();

            if ($representative) {
                $representative->update([
                    'profession' => $data['profession'] ?? null,
                    'workplace' => $data['workplace'] ?? null,
                    'relationship' => $data['relationship'] ?? null,
                ]);
            }
        }

        unset($data['profession'], $data['workplace'], $data['relationship']);

        $this->userService->updateUser($user, $data);

        return back()->with([
            'status' => true,
            'message' => 'Perfil actualizado correctamente.',
        ]);
    }

    public function changePassword(ChangePasswordRequest $request)
    {
        $user = auth()->user();

        if (! Hash::check($request->current_password, $user->password)) {
            return back()->withErrors([
                'current_password' => 'La contraseña actual es incorrecta.',
            ]);
        }

        $this->userService->updateUser($user, ['password' => Hash::make($request->new_password)]);

        return back()->with([
            'status' => true,
            'message' => 'Contraseña actualizada correctamente.',
        ]);
    }

    private function photoUrl(?string $photo): ?string
    {
        if (! $photo || $photo === 'guest.webp') {
            return null;
        }

        return asset('img/photos/'.$photo);
    }
}
