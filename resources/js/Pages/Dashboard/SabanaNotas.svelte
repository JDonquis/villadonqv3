<script>
    import { router } from "@inertiajs/svelte";
    import { displayAlert } from "../../stores/alertStore";
    import Alert from "../../components/Alert.svelte";

    export let data;

    const MAX_SCORE = 20;

    let schoolLapseId = data.filters?.school_lapse_id ?? "";
    let courseId = data.filters?.course_id ?? "";
    let lapseId = data.filters?.lapse_id ?? "";

    let points = {};
    let saving = false;
    let lastKey = "";

    $: selectedSchoolLapse =
        data.school_lapses?.find((l) => String(l.id) === String(schoolLapseId)) ||
        null;
    $: momentOptions = selectedSchoolLapse?.lapses || [];

    $: sheetData = data.sheet || null;

    // Reinicia los puntos locales cuando cambia la sábana o el momento cargado.
    $: {
        const key = sheetData ? `${sheetData.sheet.id}-${sheetData.selected_lapse_id}` : "";
        if (key !== lastKey) {
            lastKey = key;
            points = {};
            if (sheetData) {
                for (const student of sheetData.students) {
                    points[student.id] = {};
                    for (const matter of sheetData.matters) {
                        points[student.id][matter.id] = Number(
                            student.grades[matter.id]?.points ?? 0,
                        );
                    }
                }
            }
        }
    }

    function fmt(value) {
        if (value === null || value === undefined || value === "") return "—";
        return Number(value).toFixed(2);
    }

    function finalFor(student, matter) {
        const grade = student.grades[matter.id];
        if (!grade || grade.definitive === null) return null;
        const pts = Number(points[student.id]?.[matter.id] ?? 0);
        return Math.min(MAX_SCORE, grade.definitive + pts);
    }

    function applyFilter(key, value) {
        const params = {
            school_lapse_id:
                key === "school_lapse_id" ? value : schoolLapseId,
            course_id: key === "course_id" ? value : courseId,
            lapse_id: key === "lapse_id" ? value : lapseId,
        };
        if (key === "school_lapse_id") {
            params.lapse_id = "";
        }
        router.get("/dashboard/sabana-notas", params, {
            preserveState: false,
            preserveScroll: true,
        });
    }

    function createSheet() {
        if (!schoolLapseId || !courseId) {
            displayAlert({
                type: "error",
                message: "Seleccione el período escolar y el año.",
            });
            return;
        }
        router.post(
            "/dashboard/sabana-notas",
            {
                school_lapse_id: schoolLapseId,
                course_id: courseId,
                lapse_id: lapseId,
            },
            {
                preserveScroll: true,
                onSuccess: () =>
                    displayAlert({
                        type: "success",
                        message: "Sábana de notas creada correctamente",
                    }),
                onError: (errors) =>
                    displayAlert({
                        type: "error",
                        message: errors.message || "No se pudo crear la sábana",
                    }),
            },
        );
    }

    function savePoints() {
        if (!sheetData) return;
        const adjustments = [];
        for (const student of sheetData.students) {
            for (const matter of sheetData.matters) {
                const grade = student.grades[matter.id];
                if (!grade || grade.definitive === null) continue;
                adjustments.push({
                    student_id: student.id,
                    matter_id: matter.id,
                    points: Number(points[student.id]?.[matter.id] ?? 0),
                });
            }
        }
        saving = true;
        router.post(
            `/dashboard/sabana-notas/${sheetData.sheet.id}/puntos`,
            { adjustments },
            {
                preserveScroll: true,
                onSuccess: () => {
                    saving = false;
                    displayAlert({
                        type: "success",
                        message: "Puntos guardados correctamente",
                    });
                },
                onError: (errors) => {
                    saving = false;
                    displayAlert({
                        type: "error",
                        message: errors.message || "No se pudieron guardar los puntos",
                    });
                },
                onFinish: () => (saving = false),
            },
        );
    }

    function deleteSheet() {
        if (!sheetData) return;
        if (!confirm("¿Eliminar esta sábana de notas?")) return;
        router.delete(`/dashboard/sabana-notas/${sheetData.sheet.id}`, {
            preserveScroll: true,
        });
    }
</script>

<svelte:head>
    <title>Sábana de Notas</title>
</svelte:head>

<Alert />

<div class="flex justify-between items-center mb-3 flex-wrap gap-2">
    <h2 class="text-xl md:text-2xl font-bold text-color1 sm:hidden">
        Sábana de Notas
    </h2>
    {#if sheetData}
        <div class="hidden sm:flex sm:ml-auto gap-2">
            <button
                class="animated-button w-fitcontent"
                disabled={saving}
                on:click={savePoints}
            >
                <span class="text">{saving ? "Guardando..." : "Guardar puntos"}</span>
                <span class="circle"></span>
            </button>
            <button
                type="button"
                class="px-3 py-2 text-sm rounded-md bg-red/10 text-red border border-red/30 hover:bg-red/20"
                on:click={deleteSheet}
            >
                Eliminar sábana
            </button>
        </div>
    {/if}
</div>

<div
    class="flex flex-wrap gap-3 mb-4 bg-white border border-gray-200 rounded-lg p-3"
>
    <div class="flex items-center gap-2">
        <label class="text-sm font-semibold text-gray-600">Período escolar</label>
        <select
            class="rounded-md border border-gray-300 px-3 py-2 text-sm"
            value={String(schoolLapseId || "")}
            on:change={(e) => applyFilter("school_lapse_id", e.target.value)}
        >
            <option value="">Seleccione...</option>
            {#each data.school_lapses as lapse}
                <option value={String(lapse.id)}>{lapse.label}</option>
            {/each}
        </select>
    </div>
    <div class="flex items-center gap-2">
        <label class="text-sm font-semibold text-gray-600">Año</label>
        <select
            class="rounded-md border border-gray-300 px-3 py-2 text-sm"
            value={String(courseId || "")}
            on:change={(e) => applyFilter("course_id", e.target.value)}
        >
            <option value="">Seleccione...</option>
            {#each data.courses as course}
                <option value={String(course.id)}>{course.name}</option>
            {/each}
        </select>
    </div>
    <div class="flex items-center gap-2">
        <label class="text-sm font-semibold text-gray-600">Momento</label>
        <select
            class="rounded-md border border-gray-300 px-3 py-2 text-sm"
            value={String(lapseId || "")}
            on:change={(e) => applyFilter("lapse_id", e.target.value)}
        >
            <option value="">Seleccione...</option>
            {#each momentOptions as mom}
                <option value={String(mom.id)}>{mom.label}</option>
            {/each}
        </select>
    </div>
</div>

{#if !sheetData}
    <div
        class="bg-white border border-gray-200 rounded-xl p-8 text-center space-y-4"
    >
        <iconify-icon
            icon="mdi:table-large"
            class="text-5xl text-gray-300"
        ></iconify-icon>
        <p class="text-sm text-gray-500">
            No hay una sábana de notas para este período escolar y año.
        </p>
        <button class="animated-button w-fitcontent" on:click={createSheet}>
            <span class="text">Crear sábana de notas</span>
            <span class="circle"></span>
        </button>
    </div>
{:else}
    <div class="bg-white border border-gray-200 rounded-xl overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full text-xs md:text-sm border-collapse">
                <thead>
                    <tr class="bg-color1 text-white">
                        <th
                            class="sticky left-0 z-10 bg-color1 px-3 py-2 text-left whitespace-nowrap"
                        >
                            Estudiante
                        </th>
                        <th class="px-3 py-2 text-left">Sección</th>
                        {#each sheetData.matters as matter}
                            <th
                                class="px-2 py-2 text-center align-bottom min-w-[110px]"
                            >
                                {matter.name}
                            </th>
                        {/each}
                    </tr>
                </thead>
                <tbody>
                    {#each sheetData.students as student, i}
                        <tr class={i % 2 ? "bg-gray-50/70" : "bg-white"}>
                            <td
                                class="sticky left-0 z-10 px-3 py-2 font-semibold text-gray-800 whitespace-nowrap {i %
                                2
                                    ? 'bg-gray-50'
                                    : 'bg-white'}"
                            >
                                {student.last_name}, {student.name}
                            </td>
                            <td class="px-3 py-2 text-gray-500 whitespace-nowrap">
                                {student.section_name || "—"}
                            </td>
                            {#each sheetData.matters as matter}
                                {@const grade = student.grades[matter.id]}
                                <td class="px-2 py-2 text-center border-l border-gray-100">
                                    {#if !grade || grade.definitive === null}
                                        <span class="text-gray-300">—</span>
                                    {:else}
                                        <div class="flex flex-col items-center gap-1">
                                            <span class="text-gray-500">
                                                Def: {fmt(grade.definitive)}
                                            </span>
                                            <input
                                                type="number"
                                                min="0"
                                                max={sheetData.max_adjustment}
                                                step="1"
                                                value={points[student.id]?.[matter.id] ?? 0}
                                                on:input={(e) => {
                                                    if (!points[student.id]) {
                                                        points[student.id] = {};
                                                    }
                                                    const v = e.target.value === ""
                                                        ? 0
                                                        : Number(e.target.value);
                                                    points[student.id][matter.id] = Math.max(
                                                        0,
                                                        Math.min(sheetData.max_adjustment, v),
                                                    );
                                                    points = points;
                                                }}
                                                class="w-16 rounded-md border border-gray-300 px-2 py-1 text-center text-sm"
                                                title="Puntos extra (0 a {sheetData.max_adjustment})"
                                            />
                                            <span
                                                class="font-bold text-color1 {finalFor(
                                                    student,
                                                    matter,
                                                ) >= MAX_SCORE
                                                    ? 'text-emerald-600'
                                                    : ''}"
                                            >
                                                {fmt(finalFor(student, matter))}
                                            </span>
                                        </div>
                                    {/if}
                                </td>
                            {/each}
                        </tr>
                    {/each}
                </tbody>
            </table>
        </div>
        {#if sheetData.students.length === 0}
            <div class="p-8 text-center text-sm text-gray-500">
                Este año no tiene estudiantes matriculados.
            </div>
        {/if}
    </div>

    <div class="sm:hidden flex justify-end mt-4">
        <button class="animated-button w-fitcontent" disabled={saving} on:click={savePoints}>
            <span class="text">{saving ? "Guardando..." : "Guardar puntos"}</span>
            <span class="circle"></span>
        </button>
    </div>

    <p class="text-xs text-gray-400 mt-3">
        Nota definitiva del momento seleccionado. Los puntos extra (0 a {sheetData.max_adjustment})
        se suman a la definitiva sin superar {MAX_SCORE}.
    </p>
{/if}
