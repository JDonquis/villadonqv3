<?php

namespace App\Services;

use App\Enums\UserTypeEnum;
use App\Models\User;

class TeacherService
{
    public function getTeachers()
    {
        return User::where('type_user_id', UserTypeEnum::Teacher->value)
            ->with('matters')
            ->orderBy('id', 'desc')
            ->get()
            ->map(function ($teacher) {
                return [
                    'id' => $teacher->id,
                    'name' => $teacher->name,
                    'last_name' => $teacher->last_name,
                    'ci' => $teacher->ci,
                    'email' => $teacher->email,
                    'phone_number' => $teacher->phone_number,
                    'address' => $teacher->address,
                    'matter_ids' => $teacher->matters->pluck('id')->map(fn ($id) => (int) $id)->values(),
                    'matters' => $teacher->matters->map(fn ($matter) => $matter->name)->values(),
                ];
            })
            ->values();
    }

    public function createTeacher(array $data): User
    {
        $user = User::create([
            'type_user_id' => UserTypeEnum::Teacher->value,
            'ci' => $data['ci'],
            'name' => $data['name'],
            'last_name' => $data['last_name'],
            'email' => $data['email'],
            'phone_number' => $data['phone_number'] ?? null,
            'address' => $data['address'] ?? null,
            'password' => $data['password'] ?? bcrypt($data['ci']),
        ]);

        if (! empty($data['matters'])) {
            $user->matters()->sync($data['matters']);
        }

        return $user;
    }

    public function updateTeacher(User $teacher, array $data): User
    {
        $teacher->update([
            'ci' => $data['ci'],
            'name' => $data['name'],
            'last_name' => $data['last_name'],
            'email' => $data['email'],
            'phone_number' => $data['phone_number'] ?? null,
            'address' => $data['address'] ?? null,
        ]);

        $teacher->matters()->sync($data['matters'] ?? []);

        return $teacher;
    }

    public function deleteTeacher(User $teacher): void
    {
        $teacher->delete();
    }
}
