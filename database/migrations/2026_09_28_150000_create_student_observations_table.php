<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_observations', function (Blueprint $table) {
            // La materia/curso/sección/profesor se derivan del plan: no se duplican.
            $table->id();

            $table->foreignId('evaluation_plan_id')
                ->constrained('evaluation_plans')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->foreignId('student_id')
                ->constrained('students')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            // Autor real: profesor dueño del plan o administración que califica.
            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete()
                ->cascadeOnUpdate();

            $table->string('type', 20)->default('neutral');

            $table->text('body');

            $table->boolean('shared_with_representative')->default(false);

            // Momento en que se marcó como compartida (badge "Enviado a Repr.").
            $table->timestamp('shared_at')->nullable();

            $table->timestamps();

            $table->softDeletes();

            $table->index(
                ['evaluation_plan_id', 'student_id'],
                'student_observations_plan_student_index'
            );

            $table->index(
                ['student_id', 'shared_with_representative'],
                'student_observations_student_shared_index'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_observations');
    }
};
