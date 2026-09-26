<?php

namespace App\Services;

use App\Models\Matter;

class MatterService
{
    public function getMatters()
    {
        return Matter::withCount('teachers')
            ->orderBy('name')
            ->get()
            ->map(fn ($matter) => [
                'id' => $matter->id,
                'name' => $matter->name,
                'literary_grading_secondary' => (bool) $matter->literary_grading_secondary,
                'teachers_count' => $matter->teachers_count,
            ])
            ->values();
    }

    public function createMatter(array $data): Matter
    {
        return Matter::create([
            'name' => $data['name'],
            'literary_grading_secondary' => $data['literary_grading_secondary'],
        ]);
    }

    public function updateMatter(Matter $matter, array $data): Matter
    {
        $matter->update([
            'name' => $data['name'],
            'literary_grading_secondary' => $data['literary_grading_secondary'],
        ]);

        return $matter;
    }

    public function deleteMatter(Matter $matter): void
    {
        $matter->delete();
    }
}
