<script>
    import { router } from "@inertiajs/svelte";
    import Modal from "../../components/Modal.svelte";
    import PlanUnitsView from "../../components/PlanUnitsView.svelte";

    export let data = [];

    $: student = data.student || {};
    $: courses = data.courses || [];
    $: moments = data.moments || [];
    $: filters = data.filters || {};
    $: subjects = data.subjects || [];

    let selectedCourseId = "";
    let selectedLapseId = "";
    let userTouched = false;

    // Keep the selects in sync with the server's filter values, but only until
    // the user makes a manual selection (avoids reverting mid-request).
    $: if (!userTouched) {
        const serverCourse = String(filters.course_id ?? student.course_id ?? "");
        const serverLapse = String(filters.lapse_id ?? "");
        if (selectedCourseId !== serverCourse) selectedCourseId = serverCourse;
        if (selectedLapseId !== serverLapse) selectedLapseId = serverLapse;
    }

    let planModal = false;
    let activePlan = null;

    const statusClasses = {
        aprobada: "bg-green-100 text-green-700",
        reprobada: "bg-red text-white",
        en_curso: "bg-yellow text-gray-800",
        sin_plan: "bg-gray-100 text-gray-500",
    };

    $: activeMomentLabel =
        moments.find((m) => String(m.id) === selectedLapseId)?.label || "";

    function reload(params) {
        router.get(`/dashboard/mis-hijos/${student.id}/materias`, params, {
            preserveState: true,
            preserveScroll: true,
        });
    }

    function changeCourse() {
        reload({ course_id: selectedCourseId, lapse_id: selectedLapseId });
    }

    function changeLapse() {
        reload({ course_id: selectedCourseId, lapse_id: selectedLapseId });
    }

    function openPlan(subject) {
        activePlan = subject.plan;
        planModal = true;
    }
</script>

<svelte:head>
    <title>Materias del Estudiante</title>
</svelte:head>

<div class="w-full max-w-[1400px] mx-auto space-y-6">
    <div>
        <h3 class="text-2xl font-bold text-color1">Materias del Estudiante</h3>
        <p class="text-sm text-gray-500">
            {student.name} {student.last_name} · C.I {student.ci} · {student.course}
            · Sección {student.section}
        </p>
    </div>

    <div class="bg-white border border-gray-200 rounded-lg p-4 flex flex-wrap items-center gap-5 md:gap-6">
        <div class="flex items-center gap-2">
            <span class="text-xs font-semibold text-gray-500 uppercase">Curso</span>
            <select
                on:change={(e) => {
                    selectedCourseId = e.target.value;
                    userTouched = true;
                    changeCourse();
                }}
                class="rounded-md border border-gray-300 px-3 py-2 text-sm"
            >
                {#each courses as course}
                    <option value={course.id} selected={String(course.id) === selectedCourseId}>{course.name}</option>
                {/each}
            </select>
        </div>

        <div class="flex items-center gap-2">
            <span class="text-xs font-semibold text-gray-500 uppercase">Momento</span>
            <select
                on:change={(e) => {
                    selectedLapseId = e.target.value;
                    userTouched = true;
                    changeLapse();
                }}
                class="rounded-md border border-gray-300 px-3 py-2 text-sm"
            >
                {#each moments as moment}
                    <option value={moment.id} selected={String(moment.id) === selectedLapseId}>{moment.label}</option>
                {/each}
            </select>
        </div>
    </div>

    {#if subjects.length === 0}
        <div class="w-full bg-white shadow-lg p-8 rounded-md text-center text-gray-400">
            Este estudiante no tiene materias para el curso y momento seleccionados.
        </div>
    {:else}
        {#each subjects as subject}
            <div class="bg-white shadow-lg rounded-md p-6">
                <div class="flex items-start justify-between gap-3 border-b border-gray-100 pb-3">
                    <div class="flex items-center gap-3">
                        <h4 class="font-bold text-gray-800">{subject.matter_name}</h4>
                        {#if subject.status}
                            <span
                                class="px-2 py-0.5 rounded text-xs font-bold {statusClasses[subject.status] || ''}"
                            >
                                {subject.status_label}
                            </span>
                        {/if}
                    </div>
                    <div class="flex items-center gap-3">
                        {#if subject.plan}
                            <button
                                on:click={() => openPlan(subject)}
                                class="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm font-semibold rounded-md border border-color1 text-color1 hover:bg-color1/5 transition"
                            >
                                <iconify-icon icon="mdi:file-document-outline"></iconify-icon>
                                Ver plan
                            </button>
                        {/if}
                    </div>
                </div>

                {#if subject.plan}
                    {#if subject.items.length}
                        <div class="overflow-x-auto mt-3">
                            <table class="w-full text-sm border border-gray-200 rounded-lg overflow-hidden">
                                <thead class="bg-gray-50">
                                    <tr>
                                        {#each subject.items as item}
                                            <th
                                                class="px-3 py-2 text-left font-semibold text-gray-700 min-w-[130px]"
                                            >
                                                <p class="whitespace-nowrap">{item.name}</p>
                                                <p class="text-xs text-gray-400 font-normal">
                                                    {item.percentage}%
                                                </p>
                                            </th>
                                        {/each}
                                            {#if subject.plan && subject.plan.rasgos_points > 0}
                                                <th class="px-3 py-2 text-left font-semibold text-gray-700">
                                                    Rasgos ({subject.plan.rasgos_points})
                                                </th>
                                            {/if}
                                        <th class="px-3 py-2 text-left font-semibold text-gray-700">
                                            Definitiva ({activeMomentLabel})
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        {#each subject.items as item}
                                            <td class="px-3 py-2">
                                                {#if item.score !== null}
                                                    <span class="font-medium text-gray-700">
                                                        {item.score}
                                                    </span>
                                                {:else}
                                                    <span class="text-gray-300">—</span>
                                                {/if}
                                            </td>
                                        {/each}
                                        {#if subject.plan && subject.plan.rasgos_points > 0}
                                            <td class="px-3 py-2">
                                                {#if subject.plan.rasgos_score !== null}
                                                    <span class="font-medium text-gray-700">{subject.plan.rasgos_score}</span>
                                                {:else}
                                                    <span class="text-gray-300">—</span>
                                                {/if}
                                            </td>
                                        {/if}
                                        <td class="px-3 py-2">
                                            {#if subject.definitive !== null}
                                                <span
                                                    class="font-bold {subject.definitive >= 10 ? 'text-green-600' : 'text-red'}"
                                                >
                                                    {subject.definitive} / 20
                                                </span>
                                            {:else}
                                                <span class="text-gray-400">En curso</span>
                                            {/if}
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    {:else}
                        <p class="text-sm text-gray-400 mt-3">
                            El plan no tiene evaluaciones para este momento.
                        </p>
                    {/if}
                {:else}
                    <p class="text-sm text-gray-400 mt-3">
                        No hay plan de evaluación aprobado para esta materia en el
                        momento seleccionado.
                    </p>
                {/if}
            </div>
        {/each}
    {/if}
</div>

<Modal bind:showModal={planModal} classes={"w-fit"}>
    {#if activePlan}
        <PlanUnitsView plan={activePlan} />
    {/if}
</Modal>