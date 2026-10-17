<script>
    import { router } from "@inertiajs/svelte";
    import DocumentTabs from "../../../components/DocumentTabs.svelte";

    export let data = null;
    export let student_id = "";
    export let students = [];
    export let options = {};

    let search = "";
    let selectedStudentId = student_id ?? "";

    $: filteredStudents = students.filter((student) => {
        const haystack = `${student.name} ${student.ci} ${student.course_name} ${student.section_name}`.toLowerCase();
        return !search.trim() || haystack.includes(search.trim().toLowerCase());
    });

    function applyStudent() {
        if (!selectedStudentId) return;
        router.get(
            "/dashboard/documentos/certificado-notas",
            { student_id: selectedStudentId },
            { preserveState: false, preserveScroll: true },
        );
    }

    function download(format) {
        if (!selectedStudentId) return;
        window.open(
            `/dashboard/documentos/certificado-notas/${format}?student_id=${selectedStudentId}`,
            "_blank",
        );
    }

    function fmt(value) {
        if (value === null || value === undefined) return "—";
        return Number(value).toLocaleString("es-VE", {
            minimumFractionDigits: 0,
            maximumFractionDigits: 0,
        });
    }
</script>

<svelte:head>
    <title>Certificado de notas</title>
</svelte:head>

<DocumentTabs active="certificado" />

<div class="flex flex-col lg:flex-row lg:items-end gap-3 mb-4">
    <div class="flex-1">
        <h1 class="text-xl font-bold text-color1">Certificado de notas</h1>
        <p class="text-sm text-gray-500">
            Certificación individual con el historial de calificaciones por año escolar.
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

<div class="neumorphism rounded-xl border border-gray-100 p-4 mb-5 grid grid-cols-1 md:grid-cols-3 gap-3">
    <label class="text-xs font-semibold text-gray-600">
        Buscar estudiante
        <input
            bind:value={search}
            placeholder="Nombre, cédula, año o sección"
            class="mt-1 w-full rounded-lg border border-gray-200 p-2 text-sm"
        />
    </label>
    <label class="text-xs font-semibold text-gray-600 md:col-span-2">
        Estudiante
        <select
            bind:value={selectedStudentId}
            on:change={applyStudent}
            class="mt-1 w-full rounded-lg border border-gray-200 p-2 text-sm bg-white"
        >
            <option value="">Seleccione un estudiante…</option>
            {#each filteredStudents as student}
                <option value={student.id}>
                    {student.last_name}, {student.name} · {student.document_type}-{student.ci} · {student.course_name} {student.section_name}
                </option>
            {/each}
        </select>
    </label>
</div>

{#if data}
    <div class="grid grid-cols-1 xl:grid-cols-4 gap-4">
        <div class="space-y-4">
            <div class="neumorphism rounded-xl border border-gray-100 p-4">
                <h2 class="font-bold text-color1 mb-2">Datos del estudiante</h2>
                <dl class="text-xs space-y-1">
                    <div><dt class="text-gray-500">Nombre</dt><dd class="font-medium">{data.student.last_name}, {data.student.name}</dd></div>
                    <div><dt class="text-gray-500">Cédula</dt><dd class="font-medium">{data.student.document_type}-{data.student.ci}</dd></div>
                    <div><dt class="text-gray-500">Nacimiento</dt><dd class="font-medium">{data.student.birth_date_label}</dd></div>
                    <div><dt class="text-gray-500">Plan de estudio</dt><dd class="font-medium">{data.program.label}</dd></div>
                    <div><dt class="text-gray-500">Código</dt><dd class="font-medium">{data.program.code || "—"}</dd></div>
                </dl>
            </div>

            <div class="neumorphism rounded-xl border border-gray-100 p-4">
                <h2 class="font-bold text-color1 mb-2">Planteles</h2>
                <ul class="text-xs space-y-2">
                    {#each data.institutions as institution}
                        <li class="border-b border-gray-100 pb-1">
                            <p class="font-semibold text-gray-700">{institution.name}</p>
                            <p class="text-gray-500">{institution.locality} · {institution.state_code}</p>
                        </li>
                    {/each}
                </ul>
            </div>

            <div class="neumorphism rounded-xl border border-gray-100 p-4">
                <h2 class="font-bold text-color1 mb-2">Director(a)</h2>
                <p class="text-xs font-medium">{data.director.name}</p>
                <p class="text-xs text-gray-500">C.I. {data.director.ci}</p>
            </div>
        </div>

        <div class="xl:col-span-3 space-y-4">
            {#each data.years as year}
                <div class="neumorphism rounded-xl border border-gray-100 overflow-hidden">
                    <div class="px-4 py-2.5 border-b border-gray-100 flex items-center justify-between gap-2">
                        <h3 class="font-bold text-color1">{year.label}</h3>
                        <span class="text-xs text-gray-500">
                            {#if year.has_data}
                                {year.course_name} · {year.school_lapse_label ?? ""}
                            {:else}
                                Sin datos del año
                            {/if}
                        </span>
                    </div>
                    {#if year.has_data && year.subjects.length}
                        <div class="overflow-x-auto">
                            <table class="w-full text-xs">
                                <thead class="bg-gray-100 text-color1">
                                    <tr>
                                        <th class="px-3 py-2 text-left">Área de formación</th>
                                        <th class="px-3 py-2 text-center w-24">Calificación</th>
                                        <th class="px-3 py-2 text-center w-32">Letras</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {#each year.subjects as subject}
                                        <tr class="border-b border-gray-100">
                                            <td class="px-3 py-1.5">{subject.name}</td>
                                            <td class={`px-3 py-1.5 text-center font-semibold ${subject.score !== null && Number(subject.score) < 10 ? "text-red" : "text-color1"}`}>
                                                {fmt(subject.score)}
                                            </td>
                                            <td class="px-3 py-1.5 text-center text-gray-500">{subject.letters ?? "—"}</td>
                                        </tr>
                                    {/each}
                                </tbody>
                            </table>
                        </div>
                    {:else}
                        <p class="px-4 py-3 text-xs text-gray-400">
                            No hay calificaciones registradas para este año escolar.
                        </p>
                    {/if}
                </div>
            {/each}
        </div>
    </div>
{:else}
    <div class="neumorphism rounded-xl border border-gray-100 p-8 text-center text-gray-500">
        Seleccione un estudiante para visualizar el certificado.
    </div>
{/if}
