<?php

namespace App\Http\Requests;

use App\Enums\StudentObservationTypeEnum;
use App\Models\EvaluationPlan;
use App\Services\StudentObservationService;
use App\Support\GradeAccess;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

abstract class ObservationRequest extends FormRequest
{
    /**
     * Plan sobre el que se opera. Cada subclase lo resuelve desde su propia fuente.
     */
    abstract public function plan(): ?EvaluationPlan;

    public function authorize(): bool
    {
        $plan = $this->plan();

        // Sin plan no hay nada que autorizar: que responda `rules()` con un 422 de
        // validación en vez de un 403 que insinuaría un problema de permisos.
        if ($plan === null) {
            return true;
        }

        return GradeAccess::canManagePlan($this->user(), $plan);
    }

    public function rules(): array
    {
        return [
            // `Rule::in` y no `Rule::enum`: el mensaje de `Enum` tiene prioridad
            // sobre `messages()`, así que no se podría traducir al español.
            'type' => ['required', Rule::in(StudentObservationTypeEnum::values())],
            'body' => ['required', 'string', 'min:3', 'max:1000'],
            'shared_with_representative' => ['nullable', 'boolean'],
            'student_id' => [
                'required',
                'integer',
                'exists:students,id',
                function (string $attribute, mixed $value, Closure $fail): void {
                    $plan = $this->plan();

                    if ($plan && ! StudentObservationService::planContainsStudent($plan, (int) $value)) {
                        $fail('El estudiante no pertenece al curso ni a la sección del plan.');
                    }
                },
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'type.required' => 'Selecciona el tipo de observación.',
            'type.in' => 'El tipo de observación no es válido.',
            'body.required' => 'Escribe el texto de la observación.',
            'body.min' => 'La observación debe tener al menos 3 caracteres.',
            'body.max' => 'La observación no puede superar los 1000 caracteres.',
            'student_id.required' => 'Selecciona un estudiante.',
            'student_id.exists' => 'El estudiante no existe.',
        ];
    }

    public function type(): StudentObservationTypeEnum
    {
        return StudentObservationTypeEnum::from($this->input('type'));
    }

    public function isShared(): bool
    {
        return $this->boolean('shared_with_representative');
    }
}
