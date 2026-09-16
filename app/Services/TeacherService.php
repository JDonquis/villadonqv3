<?php

namespace App\Services;

use App\Enums\UserTypeEnum;
use App\Models\FailedImport;
use App\Models\Matter;
use App\Models\User;
use App\Support\ErrorTranslator;
use Illuminate\Support\Facades\Log;

class TeacherService
{
    public function getTeachers(?string $search = null)
    {
        $query = User::where('type_user_id', UserTypeEnum::Teacher->value)
            ->with('matters')
            ->when($search, function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%")
                        ->orWhere('ci', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhereHas('matters', fn ($matterQuery) => $matterQuery->where('name', 'like', "%{$search}%"));
                });
            })
            ->orderBy('id', 'desc');

        return $query->get()
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
                $mappedData = $this->mapRowData($raw);
                try {
                    FailedImport::create([
                        'import_type' => 'teacher',
                        'row_number' => $rowNumber,
                        'data' => $mappedData,
                        'error_message' => ErrorTranslator::translate($e),
                    ]);
                } catch (\Exception $inner) {
                    Log::error('Failed to store failed import: '.$inner->getMessage());
                }
                $summary['errors'][] = [
                    'row' => $rowNumber,
                    'message' => ErrorTranslator::translate($e),
                ];
            }
        }

        return $summary;
    }

    private function mapRowData(array $raw): array
    {
        $data = [];
        foreach (self::TEACHER_IMPORT_MAP as $header => $field) {
            $data[$field] = $raw[$this->normalizeHeader($header)] ?? '';
        }

        return $data;
    }

    private function createTeacherFromRow(array $raw): void
    {
        $data = $this->mapRowData($raw);
        $this->createTeacherFromMappedData($data);
    }

    private function createTeacherFromMappedData(array $data): void
    {
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

        $existingUser = User::where('ci', $data['ci'])->first();

        if ($existingUser) {
            $matterIds = [];
            $matters = array_values(array_filter(array_map('trim', preg_split('/[,;]/', $data['matters'])), fn ($m) => $m !== ''));
            foreach ($matters as $matterName) {
                $matter = Matter::whereRaw('LOWER(name) = ?', [mb_strtolower($matterName)])->first();
                if (! $matter) {
                    throw new \Exception("La materia '{$matterName}' no existe.");
                }
                $matterIds[] = $matter->id;
            }

            $this->updateTeacher($existingUser, [
                'ci' => $data['ci'],
                'name' => $data['name'],
                'last_name' => $data['last_name'],
                'email' => $data['email'],
                'phone_number' => $data['phone_number'] !== '' ? $data['phone_number'] : null,
                'address' => $data['address'] !== '' ? $data['address'] : null,
                'matters' => $matterIds,
            ]);

            return;
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

    public function retryImport(int $failedImportId): void
    {
        $failedImport = FailedImport::findOrFail($failedImportId);
        $data = $failedImport->data;

        $this->createTeacherFromMappedData($data);

        $failedImport->delete();
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
