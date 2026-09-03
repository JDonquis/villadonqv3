<?php

namespace App\Http\Requests;

use App\Enums\UserTypeEnum;
use App\Models\User;

class StoreAdminEvaluationPlanRequest extends StoreEvaluationPlanRequest
{
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'teacher_id' => ['required', 'integer', 'exists:users,id'],
        ]);
    }

    public function withValidator($validator): void
    {
        parent::withValidator($validator);

        $validator->after(function ($validator) {
            $teacher = User::find($this->input('teacher_id'));

            if (! $teacher || (int) $teacher->type_user_id !== UserTypeEnum::Teacher->value) {
                $validator->errors()->add('teacher_id', 'Seleccione un profesor válido.');
                return;
            }

            if (! $teacher->matters()->whereKey($this->input('matter_id'))->exists()) {
                $validator->errors()->add('matter_id', 'La materia no pertenece al profesor seleccionado.');
            }
        });
    }
}