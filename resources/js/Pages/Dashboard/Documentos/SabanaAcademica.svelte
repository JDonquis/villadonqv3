<script>
    import { router } from "@inertiajs/svelte";
    import DocumentTabs from "../../../components/DocumentTabs.svelte";

    export let data;
    export let filters = {};
    export let options = {};

    let schoolLapseId = filters.school_lapse_id ?? "";
    let courseId = filters.course_id ?? "";
    let sectionId = filters.section_id ?? "";
    let lapseId = filters.lapse_id ?? "";

    $: schoolLapses = options.school_lapses || [];
    $: courses = options.courses || [];
    $: selectedSchoolLapse = schoolLapses.find(
        (item) => String(item.id) === String(schoolLapseId),
    );
    $: lapseOptions = selectedSchoolLapse?.lapses || [];
    $: selectedCourse = courses.find(
        (item) => String(item.id) === String(courseId),
    );
    $: sectionOptions = selectedCourse?.sections || [];

    function params() {
        return {
            school_lapse_id: schoolLapseId,
            course_id: courseId,
            section_id: sectionId,
            lapse_id: lapseId,
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
        router.get("/dashboard/documentos/sabana-academica", params(), {
            preserveState: false,
            preserveScroll: true,
        });
    }

    function changeSchoolLapse() {
        lapseId = "";
        applyFilters();
    }

    function changeCourse() {
        if (!sectionOptions.some((s) => String(s.id) === String(sectionId))) {
            sectionId = "";
        }
        applyFilters();
    }

    function download(format) {
        window.open(
            `/dashboard/documentos/sabana-academica/${format}?${queryString()}`,
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

    function isFail(value) {
        return value !== null && Number(value) < 10;
    }
</script>

<svelte:head>
    <title>Sábana académica</title>
</svelte:head>

<DocumentTabs active="sabana" />

<div class="flex flex-col lg:flex-row lg:items-end gap-3 mb-4">
    <div class="flex-1">
        <h1 class="text-xl font-bold text-color1">Sábana académica</h1>
        <p class="text-sm text-gray-500">
            Calificaciones por área de formación, promedio y posición por estudiante.
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

<div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-5 gap-3 mb-5">
    <label class="text-xs font-semibold text-gray-600">
        Período escolar
        <select
            bind:value={schoolLapseId}
            on:change={changeSchoolLapse}
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

    <label class="text-xs font-semibold text-gray-600">
        Momento
        <select
            bind:value={lapseId}
            on:change={applyFilters}
            class="mt-1 w-full rounded-lg border border-gray-200 p-2 text-sm bg-white"
        >
            <option value="">Último momento</option>
            {#each lapseOptions as lapse}
                <option value={lapse.id}>{lapse.label}</option>
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

<div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-5">
    <div class="neumorphism rounded-xl border border-gray-100 p-3">
        <p class="text-[11px] uppercase text-gray-500 font-semibold">Estudiantes</p>
        <p class="text-2xl font-bold text-color1">{data.stats?.enrolled ?? 0}</p>
    </div>
    <div class="neumorphism rounded-xl border border-gray-100 p-3">
        <p class="text-[11px] uppercase text-gray-500 font-semibold">Promedio general</p>
        <p class="text-2xl font-bold text-color1">{fmt(data.stats?.average, 2)}</p>
    </div>
    <div class="neumorphism rounded-xl border border-gray-100 p-3">
        <p class="text-[11px] uppercase text-gray-500 font-semibold">Aprobados</p>
        <p class="text-2xl font-bold text-green">{data.stats?.approved ?? 0}</p>
    </div>
    <div class="neumorphism rounded-xl border border-gray-100 p-3">
        <p class="text-[11px] uppercase text-gray-500 font-semibold">Aplazados</p>
        <p class="text-2xl font-bold text-red">{data.stats?.failed ?? 0}</p>
    </div>
</div>

<div class="neumorphism rounded-xl border border-gray-100 overflow-hidden">
    <div class="px-4 py-3 border-b border-gray-100 flex flex-wrap items-center justify-between gap-2">
        <div>
            <h2 class="font-bold text-color1">
                {data.meta?.course_name ?? "—"} · Sección {data.meta?.section_name || "Todas"}
            </h2>
            <p class="text-xs text-gray-500">
                {data.meta?.school_lapse_label ?? "—"} · {data.meta?.lapse_label ?? "—"} · Emitido {data.meta?.generated_at}
            </p>
        </div>
        <span class="text-xs text-gray-500">{data.students?.length ?? 0} registros</span>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full text-xs">
            <thead class="bg-color1 text-white">
                <tr>
                    <th class="px-2 py-2 text-left">N°</th>
                    <th class="px-2 py-2 text-left min-w-[190px]">Alumno</th>
                    {#each data.matters as matter}
                        <th class="px-2 py-2 text-center" title={matter.name}>{matter.short}</th>
                    {/each}
                    <th class="px-2 py-2 text-center">Pro.</th>
                    <th class="px-2 py-2 text-center">Pos.</th>
                    <th class="px-2 py-2 text-center">Apr.</th>
                    <th class="px-2 py-2 text-center">Apl.</th>
                </tr>
            </thead>
            <tbody>
                {#each data.students as student, index}
                    <tr class="border-b border-gray-100 hover:bg-gray-50">
                        <td class="px-2 py-1.5">{String(index + 1).padStart(2, "0")}</td>
                        <td class="px-2 py-1.5 font-medium text-gray-700">{student.full_name}</td>
                        {#each data.matters as matter}
                            {@const grade = student.grades[matter.id]}
                            <td class:text-red={isFail(grade?.score)} class="px-2 py-1.5 text-center">
                                {grade?.score !== null && grade?.score !== undefined ? fmt(grade.score) : "—"}
                            </td>
                        {/each}
                        <td class="px-2 py-1.5 text-center font-bold text-color2">{fmt(student.average)}</td>
                        <td class="px-2 py-1.5 text-center">{student.position ?? "—"}</td>
                        <td class="px-2 py-1.5 text-center text-green font-semibold">{student.approved_count}</td>
                        <td class:text-red={student.failed_count > 0} class="px-2 py-1.5 text-center font-semibold">
                            {student.failed_count}
                        </td>
                    </tr>
                {:else}
                    <tr>
                        <td colspan={data.matters.length + 6} class="text-center text-gray-400 py-8">
                            No hay estudiantes para los criterios seleccionados.
                        </td>
                    </tr>
                {/each}
            </tbody>
            {#if data.students?.length && data.matters?.length}
                <tfoot class="bg-gray-100 font-semibold text-color1">
                    <tr>
                        <td colspan="2" class="px-2 py-2 text-right uppercase text-[10px]">Promedio por área</td>
                        {#each data.matters as matter}
                            <td class="px-2 py-2 text-center">{fmt(data.matter_stats?.[matter.id]?.average)}</td>
                        {/each}
                        <td class="px-2 py-2 text-center">{fmt(data.stats?.average)}</td>
                        <td colspan="3"></td>
                    </tr>
                </tfoot>
            {/if}
        </table>
    </div>
</div>

<p class="text-[11px] text-gray-500 mt-3">
    {#each data.matters as matter, index}
        <strong>{matter.short}</strong> = {matter.name}{index < data.matters.length - 1 ? " · " : ""}
    {/each}
</p>
