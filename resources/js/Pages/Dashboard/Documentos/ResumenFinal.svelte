<script>
    import { router } from "@inertiajs/svelte";
    import DocumentTabs from "../../../components/DocumentTabs.svelte";

    export let data;
    export let filters = {};
    export let options = {};

    let schoolLapseId = filters.school_lapse_id ?? "";
    let courseId = filters.course_id ?? "";
    let sectionId = filters.section_id ?? "";

    $: schoolLapses = options.school_lapses || [];
    $: courses = options.courses || [];
    $: selectedCourse = courses.find(
        (item) => String(item.id) === String(courseId),
    );
    $: sectionOptions = selectedCourse?.sections || [];

    function params() {
        return {
            school_lapse_id: schoolLapseId,
            course_id: courseId,
            section_id: sectionId,
        };
    }

    function queryString() {
        const search = new URLSearchParams();
        for (const [key, value] of Object.entries(params())) {
            if (value !== "" && value !== null && value !== undefined) {
                search.set(key, value);
            }
        }
        return search.toString();
    }

    function applyFilters() {
        router.get("/dashboard/documentos/resumen-final", params(), {
            preserveState: false,
            preserveScroll: true,
        });
    }

    function changeCourse() {
        if (!sectionOptions.some((s) => String(s.id) === String(sectionId))) {
            sectionId = "";
        }
        applyFilters();
    }

    function download(format) {
        window.open(
            `/dashboard/documentos/resumen-final/${format}?${queryString()}`,
            "_blank",
        );
    }

    function fmt(value, decimals = 1) {
        if (value === null || value === undefined || value === "") return "—";
        return Number(value).toLocaleString("es-VE", {
            minimumFractionDigits: decimals,
            maximumFractionDigits: decimals,
        });
    }

    function avgColor(value) {
        if (value === null || value === undefined) return "text-gray-400";
        return Number(value) >= 10 ? "text-color1" : "text-red font-bold";
    }
</script>

<svelte:head>
    <title>Resumen final</title>
</svelte:head>

<DocumentTabs active="resumen" />

<div class="flex flex-col lg:flex-row lg:items-end gap-3 mb-4">
    <div class="flex-1">
        <h1 class="text-xl font-bold text-color1">Resumen final del rendimiento</h1>
        <p class="text-sm text-gray-500">
            Nota final anual por área de formación, estadísticas y profesores responsables.
        </p>
    </div>
    <div class="flex gap-2">
        <button
            type="button"
            on:click={() => download("excel")}
            class="px-4 py-2 rounded-lg bg-green text-color1 text-sm font-semibold flex items-center gap-1 hover:opacity-90"
        >
            <iconify-icon icon="mdi:microsoft-excel" /> Excel
        </button>
        <button
            type="button"
            on:click={() => download("pdf")}
            class="px-4 py-2 rounded-lg bg-color2 text-white text-sm font-semibold flex items-center gap-1 hover:opacity-90"
        >
            <iconify-icon icon="mdi:file-pdf-box" /> PDF
        </button>
    </div>
</div>

<div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-3 mb-5">
    <label class="text-xs font-semibold text-gray-600">
        Período escolar
        <select
            bind:value={schoolLapseId}
            on:change={applyFilters}
            class="mt-1 w-full rounded-lg border border-gray-200 p-2 text-sm bg-white"
        >
            {#each schoolLapses as lapse}
                <option value={lapse.id}>{lapse.label}{lapse.is_active ? " (activo)" : ""}</option>
            {/each}
        </select>
    </label>
    <label class="text-xs font-semibold text-gray-600">
        Año / Grado
        <select
            bind:value={courseId}
            on:change={changeCourse}
            class="mt-1 w-full rounded-lg border border-gray-200 p-2 text-sm bg-white"
        >
            <option value="">Seleccione…</option>
            {#each courses as course}
                <option value={course.id}>{course.name}</option>
            {/each}
        </select>
    </label>
    <label class="text-xs font-semibold text-gray-600">
        Sección
        <select
            bind:value={sectionId}
            on:change={applyFilters}
            class="mt-1 w-full rounded-lg border border-gray-200 p-2 text-sm bg-white"
        >
            <option value="">Todas</option>
            {#each sectionOptions as section}
                <option value={section.id}>{section.name}</option>
            {/each}
        </select>
    </label>
    <div class="flex items-end">
        <button
            type="button"
            on:click={applyFilters}
            class="w-full px-4 py-2 rounded-lg bg-color1 text-white text-sm font-semibold hover:opacity-90"
        >
            Aplicar filtros
        </button>
    </div>
</div>

<div class="grid grid-cols-1 xl:grid-cols-4 gap-4">
    <div class="xl:col-span-3 neumorphism rounded-xl border border-gray-100 overflow-hidden">
        <div class="px-4 py-3 border-b border-gray-100 flex flex-wrap items-center justify-between gap-2">
            <div>
                <h2 class="font-bold text-color1">
                    {data.meta?.course_name ?? "—"} · Sección {data.meta?.section_name || "Todas"}
                </h2>
                <p class="text-xs text-gray-500">
                    {data.meta?.school_lapse_label ?? "—"} · {data.meta?.total_students ?? 0} estudiantes
                </p>
            </div>
            <span class="text-xs text-gray-500">Emitido {data.meta?.generated_at}</span>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-xs">
                <thead class="bg-color1 text-white">
                    <tr>
                        <th class="px-2 py-2 text-left">N°</th>
                        <th class="px-2 py-2 text-left min-w-[180px]">Apellidos y nombres</th>
                        {#each data.matters as matter}
                            <th class="px-2 py-2 text-center" title={matter.name}>{matter.short}</th>
                        {/each}
                        <th class="px-2 py-2 text-center">Prom.</th>
                    </tr>
                </thead>
                <tbody>
                    {#each data.students as student, index}
                        <tr class="border-b border-gray-100 hover:bg-gray-50">
                            <td class="px-2 py-1.5">{String(index + 1).padStart(2, "0")}</td>
                            <td class="px-2 py-1.5 font-medium text-gray-700">{student.full_name}</td>
                            {#each data.matters as matter}
                                {@const score = student.grades[matter.id]}
                                <td class={`px-2 py-1.5 text-center ${score !== null && score !== undefined && Number(score) < 10 ? "text-red font-semibold" : ""}`}>
                                    {fmt(score)}
                                </td>
                            {/each}
                            <td class={`px-2 py-1.5 text-center font-bold ${avgColor(student.average)}`}>
                                {fmt(student.average)}
                            </td>
                        </tr>
                    {:else}
                        <tr>
                            <td colspan={data.matters.length + 3} class="text-center text-gray-400 py-8">
                                No hay estudiantes para los criterios seleccionados.
                            </td>
                        </tr>
                    {/each}
                </tbody>
                {#if data.students?.length && data.matters?.length}
                    <tfoot class="bg-gray-100 text-color1">
                        {#each [["Inscritos", "enrolled"], ["Aprobados", "approved"], ["No aprobados", "failed"], ["No cursantes", "not_taken"]] as [label, key]}
                            <tr class="border-t border-gray-200">
                                <td colspan="2" class="px-2 py-1.5 text-right uppercase text-[10px] font-semibold">{label}</td>
                                {#each data.matters as matter}
                                    <td class="px-2 py-1.5 text-center font-semibold">
                                        {data.totals?.[matter.id]?.[key] ?? 0}
                                    </td>
                                {/each}
                                <td></td>
                            </tr>
                        {/each}
                    </tfoot>
                {/if}
            </table>
        </div>
    </div>

    <div class="space-y-4">
        <div class="neumorphism rounded-xl border border-gray-100 p-4">
            <h3 class="font-bold text-color1 mb-2">Identificación del curso</h3>
            <dl class="text-xs space-y-1">
                <div class="flex justify-between gap-2"><dt class="text-gray-500">Plan</dt><dd class="text-right font-medium">{data.meta?.plan_name ?? "—"}</dd></div>
                <div class="flex justify-between gap-2"><dt class="text-gray-500">Código</dt><dd class="text-right font-medium">{data.meta?.plan_code || "—"}</dd></div>
                <div class="flex justify-between gap-2"><dt class="text-gray-500">Año</dt><dd class="text-right font-medium">{data.meta?.course_name ?? "—"}</dd></div>
                <div class="flex justify-between gap-2"><dt class="text-gray-500">Sección</dt><dd class="text-right font-medium">{data.meta?.section_name || "Todas"}</dd></div>
                <div class="flex justify-between gap-2"><dt class="text-gray-500">Estudiantes</dt><dd class="text-right font-medium">{data.meta?.total_students ?? 0}</dd></div>
            </dl>
        </div>

        <div class="neumorphism rounded-xl border border-gray-100 p-4">
            <h3 class="font-bold text-color1 mb-2">Profesores por área</h3>
            <ul class="text-xs space-y-2">
                {#each data.matters as matter}
                    <li class="border-b border-gray-100 pb-1">
                        <p class="font-semibold text-gray-700">{matter.name}</p>
                        <p class="text-gray-500">
                            {data.professors?.[matter.id]?.name || "Sin asignar"}
                            {#if data.professors?.[matter.id]?.ci}
                                · {data.professors[matter.id].ci}
                            {/if}
                        </p>
                    </li>
                {/each}
            </ul>
        </div>
    </div>
</div>
