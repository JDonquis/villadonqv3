<script>
    import { router } from "@inertiajs/svelte";
    import { displayAlert } from "../../stores/alertStore";
    import Alert from "../../components/Alert.svelte";

    export let data;

    const MAX_SCORE = 20;
    const PASSING_SCORE = 10;

    let schoolLapseId = data.filters?.school_lapse_id ?? "";
    let courseId = data.filters?.course_id ?? "";
    let lapseId = data.filters?.lapse_id ?? "";

    let points = {};
    let saving = false;
    let lastKey = "";
    let baseline = "";

    let search = "";
    let sectionFilter = "";

    $: selectedSchoolLapse =
        data.school_lapses?.find((l) => String(l.id) === String(schoolLapseId)) ||
        null;
    $: momentOptions = selectedSchoolLapse?.lapses || [];

    $: sheetData = data.sheet || null;
    $: matters = sheetData?.matters || [];
    $: students = sheetData?.students || [];

    $: sectionOptions = [
        ...new Set(students.map((s) => s.section_name).filter(Boolean)),
    ].sort();

    // Reinicia los puntos locales cuando cambia la sábana o el momento.
    $: {
        const key = sheetData
            ? `${sheetData.sheet.id}-${sheetData.selected_lapse_id}`
            : "";
        if (key !== lastKey) {
            lastKey = key;
            points = {};
            if (sheetData) {
                for (const student of students) {
                    points[student.id] = {};
                    for (const matter of matters) {
                        points[student.id][matter.id] = Number(
                            student.grades[matter.id]?.points ?? 0,
                        );
                    }
                }
            }
            baseline = JSON.stringify(points);
        }
    }

    // Solo se ofrece guardar cuando hay cambios sin guardar (igual que en MisEstudiantes).
    $: dirty = JSON.stringify(points) !== baseline;

    // ===== Búsqueda (cliente) =====
    $: filteredStudents = students.filter((student) => {
        const haystack =
            `${student.name} ${student.last_name} ${student.ci}`.toLowerCase();
        const matchesSearch =
            !search.trim() || haystack.includes(search.trim().toLowerCase());
        const matchesSection =
            !sectionFilter || student.section_name === sectionFilter;
        return matchesSearch && matchesSection;
    });

    function fmt(value) {
        if (value === null || value === undefined || value === "") return "—";
        return Number(value).toFixed(2);
    }

    function cellFinal(student, matter) {
        const grade = student.grades[matter.id];
        if (!grade || grade.definitive === null) return null;
        const pts = Number(points[student.id]?.[matter.id] ?? 0);
        return Math.min(MAX_SCORE, grade.definitive + pts);
    }

    function finalFor(student, matter) {
        return cellFinal(student, matter);
    }

    function studentStats(student) {
        const finals = matters
            .map((matter) => cellFinal(student, matter))
            .filter((v) => v !== null);

        if (!finals.length) {
            return { average: null };
        }

        const average = finals.reduce((a, b) => a + b, 0) / finals.length;

        return { average };
    }

    function matterAverage(matterId) {
        const vals = students
            .map((student) => {
                const grade = student.grades[matterId];
                if (!grade || grade.definitive === null) return null;
                const pts = Number(points[student.id]?.[matterId] ?? 0);
                return Math.min(MAX_SCORE, grade.definitive + pts);
            })
            .filter((v) => v !== null);

        if (!vals.length) return null;
        return vals.reduce((a, b) => a + b, 0) / vals.length;
    }

    $: matterAverages = matters.map((matter) => ({
        id: matter.id,
        avg: matterAverage(matter.id),
    }));

    $: overallAverage = (() => {
        const vals = matterAverages.map((m) => m.avg).filter((v) => v !== null);
        if (!vals.length) return null;
        return vals.reduce((a, b) => a + b, 0) / vals.length;
    })();

    function studentInitials(student) {
        const first = (student.name || "").trim().charAt(0);
        const last = (student.last_name || "").trim().charAt(0);
        return (first + last).toUpperCase() || "?";
    }

    function matterAbbr(name) {
        return (name || "").trim().slice(0, 3).toUpperCase();
    }

    function isFailing(value) {
        return value !== null && value < PASSING_SCORE;
    }

    function updatePoints(studentId, matterId, raw) {
        const parsed = raw === "" ? 0 : Number(raw);
        const clamped = Math.max(
            0,
            Math.min(
                Number(sheetData?.max_adjustment ?? 0),
                Number.isFinite(parsed) ? parsed : 0,
            ),
        );
        if (!points[studentId]) points[studentId] = {};
        points[studentId][matterId] = clamped;
        points = points;
        return clamped;
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
                    baseline = JSON.stringify(points);
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

    function printPdf() {
        if (!sheetData) return;
        const params = new URLSearchParams();
        if (schoolLapseId) params.set("school_lapse_id", schoolLapseId);
        if (courseId) params.set("course_id", courseId);
        if (lapseId) params.set("lapse_id", lapseId);
        if (sectionFilter) params.set("section", sectionFilter);
        window.open(
            `/dashboard/sabana-notas/pdf?${params.toString()}`,
            "_blank",
        );
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
</div>

<!-- BEGIN: Barra de filtros -->
<section
    class="bg-white rounded-2xl p-4 border border-grayBlue/50 shadow-sm mb-4"
>
    <div class="flex flex-col lg:flex-row lg:items-end justify-between gap-4">
        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-3 flex-1">
            <div>
                <label
                    class="block text-[11px] font-bold text-gray-500 uppercase tracking-wider mb-1"
                    for="sabana-periodo">Período escolar</label
                >
                <select
                    id="sabana-periodo"
                    class="w-full text-xs font-semibold bg-background border border-grayBlue/60 text-color1 rounded-xl py-2 px-3 focus:bg-white focus:ring-2 focus:ring-color2/25 focus:border-color2 transition-all cursor-pointer outline-none"
                    value={String(schoolLapseId || "")}
                    on:change={(e) =>
                        applyFilter("school_lapse_id", e.target.value)}
                >
                    <option value="">Seleccione...</option>
                    {#each data.school_lapses as lapse}
                        <option value={String(lapse.id)}>{lapse.label}</option>
                    {/each}
                </select>
            </div>

            <div>
                <label
                    class="block text-[11px] font-bold text-gray-500 uppercase tracking-wider mb-1"
                    for="sabana-anio">Año / Grado</label
                >
                <select
                    id="sabana-anio"
                    class="w-full text-xs font-semibold bg-background border border-grayBlue/60 text-color1 rounded-xl py-2 px-3 focus:bg-white focus:ring-2 focus:ring-color2/25 focus:border-color2 transition-all cursor-pointer outline-none"
                    value={String(courseId || "")}
                    on:change={(e) => applyFilter("course_id", e.target.value)}
                >
                    <option value="">Seleccione...</option>
                    {#each data.courses as course}
                        <option value={String(course.id)}>{course.name}</option>
                    {/each}
                </select>
            </div>

            <div>
                <label
                    class="block text-[11px] font-bold text-gray-500 uppercase tracking-wider mb-1"
                    for="sabana-seccion">Sección</label
                >
                <select
                    id="sabana-seccion"
                    class="w-full text-xs font-semibold bg-background border border-grayBlue/60 text-color1 rounded-xl py-2 px-3 focus:bg-white focus:ring-2 focus:ring-color2/25 focus:border-color2 transition-all cursor-pointer outline-none disabled:opacity-60"
                    disabled={!sectionOptions.length}
                    bind:value={sectionFilter}
                >
                    <option value="">Todas</option>
                    {#each sectionOptions as section}
                        <option value={section}>{section}</option>
                    {/each}
                </select>
            </div>

            <div>
                <label
                    class="block text-[11px] font-bold text-gray-500 uppercase tracking-wider mb-1"
                    for="sabana-momento">Momento / Lapso</label
                >
                <select
                    id="sabana-momento"
                    class="w-full text-xs font-semibold bg-background border border-grayBlue/60 text-color1 rounded-xl py-2 px-3 focus:bg-white focus:ring-2 focus:ring-color2/25 focus:border-color2 transition-all cursor-pointer outline-none disabled:opacity-60"
                    value={String(lapseId || "")}
                    disabled={!momentOptions.length}
                    on:change={(e) => applyFilter("lapse_id", e.target.value)}
                >
                    <option value="">Seleccione...</option>
                    {#each momentOptions as mom}
                        <option value={String(mom.id)}>{mom.label}</option>
                    {/each}
                </select>
            </div>
        </div>

        {#if sheetData}
            <div class="flex items-center gap-2 self-start lg:self-auto">
                <span
                    class="hidden sm:inline-flex items-center px-3 py-1.5 rounded-xl bg-green/15 text-color1 text-xs font-semibold border border-green/30"
                >
                    <span class="relative flex h-2 w-2 mr-2">
                        <span
                            class="animate-ping absolute inline-flex h-full w-full rounded-full bg-green opacity-75"
                        ></span>
                        <span
                            class="relative inline-flex rounded-full h-2 w-2 bg-green"
                        ></span>
                    </span>
                    {filteredStudents.length} estudiantes
                </span>
            </div>
        {/if}
    </div>
</section>
<!-- END: Barra de filtros -->

{#if !sheetData}
    <div
        class="bg-white border border-grayBlue/50 rounded-2xl p-10 text-center space-y-4 shadow-sm"
    >
        <iconify-icon
            icon="mdi:table-large"
            class="text-5xl text-grayBlue"
        ></iconify-icon>
        <p class="text-sm text-gray-500">
            No hay una sábana de notas para este período escolar y año.
        </p>
        <div class="flex justify-center">
            <button
                type="button"
                class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl bg-color1 text-white text-sm font-semibold hover:bg-color2 active:scale-95 transition-all shadow-sm"
                on:click={createSheet}
            >
                <iconify-icon icon="mdi:plus" class="text-base"></iconify-icon>
                Crear sábana de notas
            </button>
        </div>
    </div>
{:else}
    <!-- BEGIN: Matriz de la sábana -->
    <section
        class="bg-white rounded-2xl border border-grayBlue/50 shadow-sm overflow-hidden flex flex-col"
    >
        <!-- Toolbar -->
        <div
            class="p-4 border-b border-grayBlue/40 flex flex-col md:flex-row md:items-center justify-between gap-3"
        >
            <div class="relative w-full md:w-72">
                <iconify-icon
                    icon="mdi:magnify"
                    class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-base"
                ></iconify-icon>
                <input
                    type="text"
                    placeholder="Buscar por alumno o cédula..."
                    class="w-full pl-9 pr-3 py-1.5 text-xs bg-background border border-grayBlue/60 text-color1 rounded-xl focus:bg-white focus:ring-2 focus:ring-color2/25 focus:border-color2 transition-all outline-none placeholder:text-gray-400"
                    bind:value={search}
                />
            </div>

            <div class="flex items-center flex-wrap gap-2">
                <button
                    type="button"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold rounded-lg bg-color2/10 text-color2 border border-color2/30 hover:bg-color2/20 transition-colors"
                    on:click={printPdf}
                >
                    <iconify-icon
                        icon="mdi:file-pdf-box"
                        class="text-base"
                    ></iconify-icon>
                    Imprimir PDF
                </button>
                <button
                    type="button"
                    class="px-3 py-1.5 text-xs font-semibold rounded-lg bg-red/10 text-red border border-red/30 hover:bg-red/20 transition-colors"
                    on:click={deleteSheet}
                >
                    Eliminar sábana
                </button>
            </div>
        </div>

        <!-- Tabla -->
        <div class="overflow-x-auto relative max-h-[70vh]">
            <table class="w-full border-collapse text-left text-xs">
                <thead
                    class="bg-color1 text-white uppercase tracking-wide text-[10px] font-bold sticky top-0 z-30"
                >
                    <tr>
                        <th
                            class="py-2 px-3 text-center bg-color1 w-12 border-r border-white/10"
                            style="position:sticky;left:0;z-index:40;"
                            scope="col">Nº</th
                        >
                        <th
                            class="py-2 px-3 bg-color1 w-28 border-r border-white/10 whitespace-nowrap"
                            style="position:sticky;left:48px;z-index:40;"
                            scope="col">Cédula</th
                        >
                        <th
                            class="py-2 px-4 bg-color1 min-w-[220px] border-r border-white/10"
                            style="position:sticky;left:160px;z-index:40;"
                            scope="col">Estudiante</th
                        >

                        {#each matters as matter}
                            <th
                                class="py-2 px-2 text-center w-20 bg-color1 border-r border-white/10"
                                scope="col"
                            >
                                <div class="font-bold">
                                    {matterAbbr(matter.name)}
                                </div>
                                <span
                                    class="block text-[8px] font-normal normal-case leading-tight text-white/60"
                                    >{matter.name}</span
                                >
                            </th>
                        {/each}

                        <th
                            class="py-2 px-3 text-center w-28 bg-color2 border-r border-white/10"
                            scope="col">Promedio</th
                        >
                    </tr>
                </thead>

                <tbody class="divide-y divide-grayBlue/40 font-medium">
                    {#each filteredStudents as student, i}
                        {@const stats = studentStats(student)}
                        <tr class="group hover:bg-color4/10 transition-colors">
                            <td
                                class="py-3 px-3 text-center bg-white group-hover:bg-color4/10 border-r border-grayBlue/40 font-bold text-gray-400"
                                style="position:sticky;left:0;z-index:20;"
                            >
                                {String(i + 1).padStart(2, "0")}
                            </td>
                            <td
                                class="py-3 px-3 font-mono bg-white group-hover:bg-color4/10 border-r border-grayBlue/40 text-gray-600 whitespace-nowrap"
                                style="position:sticky;left:48px;z-index:20;"
                            >
                                {student.ci || "—"}
                            </td>
                            <td
                                class="py-3 px-4 bg-white group-hover:bg-color4/10 border-r border-grayBlue/40"
                                style="position:sticky;left:160px;z-index:20;"
                            >
                                <div class="flex items-center space-x-2.5">
                                    <div
                                        class="w-7 h-7 rounded-full bg-color4/25 text-color2 font-bold flex items-center justify-center text-[10px] flex-shrink-0"
                                    >
                                        {studentInitials(student)}
                                    </div>
                                    <div>
                                        <div
                                            class="font-bold text-color1 leading-tight"
                                        >
                                            {student.last_name}, {student.name}
                                        </div>
                                        <div class="text-[10px] text-gray-400">
                                            Regular • {student.section_name ||
                                                "—"}
                                        </div>
                                    </div>
                                </div>
                            </td>

                            {#each matters as matter}
                                {@const grade = student.grades[matter.id]}
                                {@const final = finalFor(student, matter)}
                                <td
                                    class="py-2 px-2 text-center border-r border-grayBlue/30 {isFailing(
                                        final,
                                    )
                                        ? 'bg-red/10'
                                        : ''}"
                                >
                                    {#if !grade || grade.definitive === null}
                                        <span class="text-grayBlue">—</span>
                                    {:else}
                                        <div
                                            class="flex flex-col items-center gap-1"
                                        >
                                            <span
                                                class="text-[9px] text-gray-400 leading-none"
                                                >Def {fmt(grade.definitive)}</span
                                            >
                                            <input
                                                type="number"
                                                min="0"
                                                max={sheetData.max_adjustment}
                                                step="1"
                                                value={points[student.id]?.[
                                                    matter.id
                                                ] ?? 0}
                                                on:input={(e) => {
                                                    const el =
                                                        e.currentTarget;
                                                    const clamped =
                                                        updatePoints(
                                                            student.id,
                                                            matter.id,
                                                            el.value,
                                                        );
                                                    el.value =
                                                        String(clamped);
                                                }}
                                                class="w-16 h-9 rounded-xl border-2 border-color2/40 bg-color4/10 text-center text-base font-black text-color2 shadow-sm transition-all focus:border-color2 focus:bg-white focus:ring-2 focus:ring-color2/30 focus:outline-none"
                                                title="Puntos extra (0 a {sheetData.max_adjustment})"
                                            />
                                            <span
                                                class="font-black text-sm {isFailing(
                                                    final,
                                                )
                                                    ? 'text-red'
                                                    : 'text-color2'}"
                                            >
                                                {fmt(final)}
                                            </span>
                                        </div>
                                    {/if}
                                </td>
                            {/each}

                            <td
                                class="py-3 px-3 text-center border-r border-color2/15 bg-color2/5"
                            >
                                {#if stats.average === null}
                                    <span class="text-grayBlue">—</span>
                                {:else}
                                    <span
                                        class={`inline-flex items-center gap-1 px-2.5 py-1 rounded-xl text-xs font-bold border ${
                                            stats.average >= PASSING_SCORE
                                                ? "bg-green/20 text-color1 border-green/30"
                                                : "bg-red/10 text-red border-red/20"
                                        }`}
                                    >
                                        <span>{fmt(stats.average)}</span>
                                        {#if stats.average < PASSING_SCORE}
                                            <span
                                                class="text-[10px] font-black uppercase tracking-tight text-red"
                                                >Aplazado</span
                                            >
                                        {/if}
                                    </span>
                                {/if}
                            </td>
                        </tr>
                    {/each}
                </tbody>

                {#if filteredStudents.length && matters.length}
                    <tfoot
                        class="bg-background border-t-2 border-grayBlue/60 font-bold text-color1"
                    >
                        <tr>
                            <td
                                class="py-3 px-4 text-right uppercase tracking-wider text-[11px] text-gray-500 border-r border-grayBlue/40"
                                colspan="3"
                            >
                                Promedio por Asignatura:
                            </td>
                            {#each matterAverages as ma}
                                <td
                                    class="py-3 px-2 text-center border-r border-grayBlue/40 text-color2"
                                >
                                    {fmt(ma.avg)}
                                </td>
                            {/each}
                            <td
                                class="py-3 px-3 text-center border-r border-color2/20 bg-color2/15 text-color2 font-extrabold text-sm"
                            >
                                {fmt(overallAverage)}
                            </td>
                        </tr>
                    </tfoot>
                {/if}
            </table>
        </div>

        {#if students.length === 0}
            <div class="p-8 text-center text-sm text-gray-500">
                Este año no tiene estudiantes matriculados.
            </div>
        {:else if filteredStudents.length === 0}
            <div class="p-8 text-center text-sm text-gray-500">
                No se encontraron estudiantes con los filtros aplicados.
            </div>
        {/if}

        <!-- Footer: conteo -->
        <div
            class="px-5 py-3.5 border-t border-grayBlue/40 flex items-center justify-between gap-3"
        >
            <div class="text-xs text-gray-500 font-medium">
                Mostrando <span class="font-bold text-color1"
                    >{filteredStudents.length}</span
                >
                de <span class="font-bold text-color1">{students.length}</span>
                estudiantes cargados en la sábana
            </div>
        </div>
    </section>
    <!-- END: Matriz de la sábana -->

    <p class="text-xs text-gray-400 mt-3">
        Nota definitiva del momento seleccionado más los puntos extra (0 a {sheetData.max_adjustment}),
        sin superar {MAX_SCORE}. Presione «Guardar puntos» para aplicar los cambios.
    </p>

    {#if dirty}
        <div
            class="fixed bottom-8 right-10 gap-3 flex justify-end max-w-[600px] ml-auto items-center z-40"
        >
            <button
                on:click={savePoints}
                class="max-w-[300px] animated-button flex items-center gap-10 hover:shadow-lg"
                disabled={saving}
            >
                {#if saving}
                    <span class="text">Guardando...</span>
                {:else}
                    <span class="text">Guardar puntos</span>
                    <span class="circle"></span>
                {/if}
            </button>
        </div>
    {/if}
{/if}
