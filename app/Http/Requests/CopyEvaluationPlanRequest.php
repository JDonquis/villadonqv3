<?php

namespace App\Http\Requests;

use App\Enums\UserTypeEnum;
use App\Models\EvaluationPlan;
use App\Models\Lapse;
use App\Models\Section;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class CopyEvaluationPlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'source_id' => ['required', 'integer', 'exists:evaluation_plans,id'],
            'school_lapse_id' => ['required', 'integer', 'exists:school_lapses,id'],
            'lapse_id' => ['required', 'integer', 'exists:lapses,id'],
            'name' => ['nullable', 'string', 'max:100'],
            'section_id' => ['required', 'array', 'min:1'],
            'section_id.*' => ['required'],
            'teacher_id' => ['nullable', 'integer', 'exists:users,id'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $source = EvaluationPlan::find($this->input('source_id'));

            if (! $source) {
                return;
            }

            $lapse = Lapse::find($this->input('lapse_id'));
            if ($lapse && (int) $lapse->school_lapse_id !== (int) $this->input('school_lapse_id')) {
                $validator->errors()->add('lapse_id', 'El momento escolar no pertenece al período escolar seleccionado.');
            }

            $sectionIds = $this->input('section_id');

            if (! is_array($sectionIds) || empty($sectionIds)) {
                $validator->errors()->add('section_id', 'Debe seleccionar al menos una sección.');

                return;
            }

            if (! in_array('all', $sectionIds, true)) {
                $ids = array_values(array_filter($sectionIds, function ($v) {
                    return $v !== null && $v !== '';
                }));

                if (empty($ids)) {
                    $validator->errors()->add('section_id', 'Debe seleccionar al menos una sección.');
                } else {
                    $count = Section::whereIn('id', $ids)->count();
                    if ($count !== count($ids)) {
                        $validator->errors()->add('section_id', 'Se seleccionaron secciones inválidas.');
                    } elseif ($source->course) {
                        $courseSections = $source->course->section()->pluck('sections.id');
                        $notInCourse = array_values(array_filter($ids, fn ($id) => ! $courseSections->contains((int) $id)));

                        if ($notInCourse) {
                            $validator->errors()->add('section_id', 'Alguna(s) sección(es) no pertenecen al año escolar del plan origen.');
                        }
                    }
                }
            }

            $teacherId = $this->input('teacher_id');

            if ($teacherId === null || $teacherId === '') {
                return;
            }

            $teacher = User::find($teacherId);
            if (! $teacher || (int) $teacher->type_user_id !== UserTypeEnum::Teacher->value) {
                $validator->errors()->add('teacher_id', 'Seleccione un profesor válido.');

                return;
            }

            if (! $teacher->matters()->whereKey($source->matter_id)->exists()) {
                $validator->errors()->add('teacher_id', 'La materia del plan origen no pertenece al profesor seleccionado.');
            }
        });
    }
}
