<script>
    import Modal from "../../components/Modal.svelte";

    export let students = [];

    let selectedSubject = null;
    let showModal = false;

    const statusClasses = {
        aprobada: "bg-green-100 text-green-700",
        reprobada: "bg-red text-white",
        en_curso: "bg-yellow text-gray-800",
        sin_plan: "bg-gray-100 text-gray-500",
    };

    function openSubject(student, subject) {
        selectedSubject = { ...subject, student_name: student.name + " " + student.last_name };
        showModal = true;
    }
</script>

<svelte:head>
    <title>Mis Hijos</title>
</svelte:head>

<div class="w-full max-w-[1400px] mx-auto space-y-6">
    <h3 class="text-2xl font-bold text-color1">Mis Hijos</h3>

    {#if students.length === 0}
        <div
            class="w-full bg-white shadow-lg p-8 rounded-md text-center text-gray-400"
        >
            No tienes hijos inscritos.
        </div>
    {:else}
        {#each students as student, i}
            <div class="bg-white shadow-lg rounded-md p-6">
                <div class="flex flex-wrap items-center gap-4 border-b border-gray-100 pb-4">
                    <div
                        class="w-12 h-12 rounded-full bg-color1/10 flex items-center justify-center text-xl font-bold text-color1"
                    >
                        {student.name?.[0]}{student.last_name?.[0]}
                    </div>
                    <div>
                        <p class="font-bold text-gray-800 capitalize">
                            {student.name} {student.last_name}
                        </p>
                        <p class="text-sm text-gray-500">
                            C.I {student.ci} · {student.course} · Sección
                            {student.section}
                        </p>
                    </div>
                    <span
                        class="ml-auto text-xs px-2 py-1 bg-color1/10 text-color1 rounded-full"
                    >
                        {student.subjects?.length ?? 0} materias
                    </span>
                </div>

                <div class="mt-4">
                    {#if student.subjects?.length}
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-3">
                            {#each student.subjects as subject}
                                <button
                                    on:click={() => openSubject(student, subject)}
                                    class="text-left border border-gray-200 rounded-lg p-4 hover:border-color1 hover:shadow transition cursor-pointer"
                                >
                                    <div class="flex items-center justify-between">
                                        <p class="font-semibold text-gray-800">
                                            {subject.matter_name}
                                        </p>
                                        <span
                                            class="px-2 py-0.5 rounded text-xs font-bold {statusClasses[subject.status] || ''}"
                                        >
                                            {subject.status_label}
                                        </span>
                                    </div>
                                    <div class="mt-2 flex items-center justify-between">
                                        <span class="text-xs text-gray-400">
                                            {subject.lapse_label || "—"}
                                        </span>
                                        {#if subject.definitive !== null}
                                            <span class="text-sm font-bold text-gray-700">
                                                {subject.definitive} / 20
                                            </span>
                                        {/if}
                                    </div>
                                </button>
                            {/each}
                        </div>
                    {:else}
                        <p class="text-sm text-gray-400">
                            Este estudiante aún no tiene materias asignadas para
                            el período actual.
                        </p>
                    {/if}
                </div>
            </div>
        {/each}
    {/if}
</div>

<Modal bind:showModal classes={"w-fit"}>
    {#if selectedSubject}
        <div class="px-5 py-2 min-w-[560px]">
            <h3 class="text-xl font-bold text-color1 mb-1">
                {selectedSubject.matter_name}
            </h3>
            <p class="text-sm text-gray-500 mb-1">
                Estudiante: {selectedSubject.student_name}
            </p>

            {#if selectedSubject.plan}
                <p class="text-sm text-gray-600 mb-1">
                    <span class="font-semibold">Plan:</span>
                    {selectedSubject.plan.name}
                </p>
                <p class="text-sm text-gray-600 mb-1">
                    <span class="font-semibold">Profesor:</span>
                    {selectedSubject.plan.teacher_name || "—"}
                </p>
                <p class="text-sm text-gray-600 mb-3">
                    <span class="font-semibold">Período:</span>
                    {selectedSubject.plan.school_lapse_label}
                    {#if selectedSubject.plan.lapse_label}
                        · {selectedSubject.plan.lapse_label}
                    {/if}
                </p>

                <table class="w-full text-sm border border-gray-200 rounded-lg overflow-hidden">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="text-left px-3 py-2">Evaluación</th>
                            <th class="text-left px-3 py-2">%</th>
                            <th class="text-left px-3 py-2">Fecha</th>
                            <th class="text-left px-3 py-2">Nota</th>
                        </tr>
                    </thead>
                    <tbody>
                        {#each selectedSubject.plan.items as item}
                            <tr class="border-t border-gray-100">
                                <td class="px-3 py-1.5">{item.name}</td>
                                <td class="px-3 py-1.5">{item.percentage}%</td>
                                <td class="px-3 py-1.5">{item.date || "—"}</td>
                                <td class="px-3 py-1.5">
                                    {#if item.score !== null}
                                        {item.score}
                                    {:else}
                                        <span class="text-gray-300">—</span>
                                    {/if}
                                </td>
                            </tr>
                        {/each}
                        <tr class="border-t border-gray-200 font-semibold">
                            <td class="px-3 py-1.5" colspan="3">
                                Definitiva ({selectedSubject.lapse_label})
                            </td>
                            <td class="px-3 py-1.5">
                                {#if selectedSubject.definitive !== null}
                                    {selectedSubject.definitive} / 20
                                {:else}
                                    <span class="text-gray-400">En curso</span>
                                {/if}
                            </td>
                        </tr>
                    </tbody>
                </table>
            {:else}
                <p class="text-sm text-gray-500 mt-2">
                    No hay un plan de evaluación aprobado para esta materia en
                    el momento actual.
                </p>
            {/if}
        </div>
    {/if}
</Modal>
