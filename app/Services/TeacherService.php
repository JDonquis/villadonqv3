<?php

namespace App\Services;

use App\Enums\UserTypeEnum;
use App\Models\Matter;
use App\Models\User;
use App\Support\ErrorTranslator;

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

    public function importTeachers(array $rows): array
    {
        $summary = ['created' => 0, 'errors' => []];

        foreach ($rows as $entry) {
            $rowNumber = $entry['row'];
            $raw = $entry['data'];

            try {
                $this->createTeacherFromRow($raw);
                $summary['created']++;
            } catch (\Exception $e) {
                $summary['errors'][] = [
                    'row' => $rowNumber,
                    'message' => ErrorTranslator::translate($e),
                ];
            }
        }

        return $summary;
    }

    private function createTeacherFromRow(array $raw): void
    {
        $data = [];
        foreach (self::TEACHER_IMPORT_MAP as $header => $field) {
            $data[$field] = $raw[$this->normalizeHeader($header)] ?? '';
        }

        $required = [
            'ci' => 'la cédula',
            'name' => 'el nombre',
            'last_name' => 'el apellido',
            'email' => 'el correo',
        ];
        foreach ($required as $field => $label) {
            if (empty($data[$field])) {
                throw new \Exception("Falta {$label}.");
            }
        }

        if (! filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            throw new \Exception("El correo '{$data['email']}' no es válido.");
        }

        if (User::where('ci', $data['ci'])->exists()) {
            throw new \Exception("La cédula {$data['ci']} ya está registrada.");
        }

        if (User::where('email', $data['email'])->exists()) {
            throw new \Exception("El correo '{$data['email']}' ya está registrado.");
        }

        $matterIds = [];
        $matters = array_values(array_filter(array_map('trim', preg_split('/[,;]/', $data['matters'])), fn ($m) => $m !== ''));
        foreach ($matters as $matterName) {
            $matter = Matter::whereRaw('LOWER(name) = ?', [mb_strtolower($matterName)])->first();
            if (! $matter) {
                throw new \Exception("La materia '{$matterName}' no existe.");
            }
            $matterIds[] = $matter->id;
        }

        $this->createTeacher([
            'ci' => $data['ci'],
            'name' => $data['name'],
            'last_name' => $data['last_name'],
            'email' => $data['email'],
            'phone_number' => $data['phone_number'] !== '' ? $data['phone_number'] : null,
            'address' => $data['address'] !== '' ? $data['address'] : null,
            'password' => bcrypt($data['ci']),
            'matters' => $matterIds,
        ]);
    }

    private function normalizeHeader($header): string
    {
        return preg_replace('/\s+/', ' ', strtolower(trim((string) $header)));
    }

    private const TEACHER_IMPORT_MAP = [
        'Cédula' => 'ci',
        'Nombre' => 'name',
        'Apellido' => 'last_name',
        'Email' => 'email',
        'Teléfono' => 'phone_number',
        'Dirección' => 'address',
        'Materias (separadas por coma)' => 'matters',
    ];
}
