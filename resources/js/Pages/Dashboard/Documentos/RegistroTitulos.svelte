<script>
    import { router } from "@inertiajs/svelte";
    import DocumentTabs from "../../../components/DocumentTabs.svelte";
    import { displayAlert } from "../../../stores/alertStore";

    export let data;
    export let filters = {};
    export let options = {};

    let schoolLapseId = filters.school_lapse_id ?? "";
    let courseId = filters.course_id ?? "";
    let sectionId = filters.section_id ?? "";
    let search = filters.search ?? "";

    let prefix = "AB";
    let start = 1;
    let serials = {};
    let observations = {};
    let savingId = null;
    let lastSnapshot = "";

    $: schoolLapses = options.school_lapses || [];
    $: courses = options.courses || [];
    $: selectedCourse = courses.find(
        (item) => String(item.id) === String(courseId),
    );
    $: sectionOptions = selectedCourse?.sections || [];
    $: snapshot = (data.students || [])
        .map((student) => `${student.id}:${student.serial ?? ""}:${student.observations ?? ""}`)
        .join("|");

    // Sincroniza los valores locales cuando cambia la data del servidor.
    $: if (snapshot !== lastSnapshot) {
        lastSnapshot = snapshot;
        const nextSerials = {};
        const nextObservations = {};
        for (const student of data.students || []) {
            nextSerials[student.id] = student.serial ?? "";
            nextObservations[student.id] = student.observations ?? "";
        }
        serials = nextSerials;
        observations = nextObservations;
    }

    function params() {
        return {
            school_lapse_id: schoolLapseId,
            course_id: courseId,
            section_id: sectionId,
            search: search,
        };
    }

    function queryString() {
        const values = new URLSearchParams();
        for (const [key, value] of Object.entries(params())) {
            if (value !== "" && value !== null && value !== undefined) {
                values.set(key, value);
            }
        }
        return values.toString();
    }

    function applyFilters() {
        router.get("/dashboard/documentos/registro-titulos", params(), {
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
            `/dashboard/documentos/registro-titulos/${format}?${queryString()}`,
            "_blank",
        );
    }

    function generateSerials() {
        if (!schoolLapseId || !courseId) {
            alert("Seleccione el período escolar y el año.");
            return;
        }

        router.post(
            "/dashboard/documentos/registro-titulos/generar-seriales",
            {
                school_lapse_id: schoolLapseId,
                course_id: courseId,
                section_id: sectionId,
                prefix,
                start,
            },
            {
                preserveScroll: true,
                onSuccess: () =>
                    displayAlert({
                        type: "success",
                        message: "Seriales generados correctamente.",
                    }),
                onError: (errors) =>
                    displayAlert({
                        type: "error",
                        message:
                            errors.message || "No se pudieron generar los seriales.",
                    }),
            },
        );
    }

    function saveSerial(student) {
        savingId = student.id;
        router.patch(
            `/dashboard/documentos/registro-titulos/${student.id}/serial`,
            {
                title_serial: serials[student.id] ?? "",
                title_observations: observations[student.id] ?? "",
            },
            {
                preserveScroll: true,
                onSuccess: () =>
                    displayAlert({
                        type: "success",
                        message: "Serial actualizado correctamente.",
                    }),
                onError: (errors) =>
                    displayAlert({
                        type: "error",
                        message: errors.message || "No se pudo guardar el serial.",
                    }),
                onFinish: () => (savingId = null),
            },
        );
    }

    function fmtDate(student) {
        return `${student.birth_day}/${student.birth_month}/${student.birth_year}`;
    }
</script>

<svelte:head>
    <title>Registro de títulos</title>
</svelte:head>

<DocumentTabs active="titulos" />

<div class="flex flex-col lg:flex-row lg:items-end gap-3 mb-4">
    <div class="flex-1">
        <h1 class="text-xl font-bold text-color1">Registro de títulos</h1>
        <p class="text-sm text-gray-500">
            Hoja oficial de egresados con seriales, datos personales y autoridades educativas.
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

<div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-5 gap-3 mb-4">
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
    <label class="text-xs font-semibold text-gray-600 xl:col-span-2">
        Buscar
        <div class="flex gap-2 mt-1">
            <input
                bind:value={search}
                on:keydown={(event) => event.key === "Enter" && applyFilters()}
                placeholder="Nombre o cédula"
                class="w-full rounded-lg border border-gray-200 p-2 text-sm"
            />
            <button
                type="button"
                on:click={applyFilters}
                class="px-4 py-2 rounded-lg bg-color1 text-white text-sm font-semibold hover:opacity-90"
            >
                Buscar
            </button>
        </div>
    </label>
</div>

<div class="neumorphism rounded-xl border border-gray-100 p-4 mb-4 flex flex-col lg:flex-row lg:items-end gap-3">
    <div class="flex-1">
        <p class="text-xs font-semibold text-gray-600 uppercase">Generación masiva de seriales</p>
        <p class="text-xs text-gray-500 mt-1">
            Asigna seriales a los estudiantes que aún no tienen uno. Los seriales existentes no se modifican.
        </p>
    </div>
    <label class="text-xs font-semibold text-gray-600 w-28">
        Prefijo
        <input
            bind:value={prefix}
            maxlength="6"
            class="mt-1 w-full rounded-lg border border-gray-200 p-2 text-sm uppercase"
        />
    </label>
    <label class="text-xs font-semibold text-gray-600 w-32">
        Iniciar en
        <input
            type="number"
            min="1"
            bind:value={start}
            class="mt-1 w-full rounded-lg border border-gray-200 p-2 text-sm"
        />
    </label>
    <button
        type="button"
        on:click={generateSerials}
        class="px-4 py-2 rounded-lg bg-color1 text-white text-sm font-semibold hover:opacity-90 flex items-center gap-1"
    >
        <iconify-icon icon="mdi:counter" /> Generar seriales
    </button>
</div>

<div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-5">
    <div class="neumorphism rounded-xl border border-gray-100 p-3">
        <p class="text-[11px] uppercase text-gray-500 font-semibold">Total egresados</p>
        <p class="text-2xl font-bold text-color1">{data.meta?.total ?? 0}</p>
    </div>
    <div class="neumorphism rounded-xl border border-gray-100 p-3">
        <p class="text-[11px] uppercase text-gray-500 font-semibold">Con serial</p>
        <p class="text-2xl font-bold text-green">{(data.students || []).filter((s) => s.serial).length}</p>
    </div>
    <div class="neumorphism rounded-xl border border-gray-100 p-3">
        <p class="text-[11px] uppercase text-gray-500 font-semibold">Sin serial</p>
        <p class="text-2xl font-bold text-red">{(data.students || []).filter((s) => !s.serial).length}</p>
    </div>
    <div class="neumorphism rounded-xl border border-gray-100 p-3">
        <p class="text-[11px] uppercase text-gray-500 font-semibold">Año / sección</p>
        <p class="text-lg font-bold text-color1">{data.meta?.course_name} · {data.meta?.section_name || "Todas"}</p>
    </div>
</div>

<div class="neumorphism rounded-xl border border-gray-100 overflow-hidden">
    <div class="px-4 py-3 border-b border-gray-100 flex flex-wrap items-center justify-between gap-2">
        <div>
            <h2 class="font-bold text-color1">Hoja de registro de títulos</h2>
            <p class="text-xs text-gray-500">
                {data.meta?.school_lapse_label ?? "—"} · Emitido {data.meta?.generated_at}
            </p>
        </div>
        <span class="text-xs text-gray-500">{data.students?.length ?? 0} registros</span>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-xs">
            <thead class="bg-color1 text-white">
                <tr>
                    <th class="px-2 py-2 text-left">N°</th>
                    <th class="px-2 py-2 text-left min-w-[120px]">Serial del título</th>
                    <th class="px-2 py-2 text-left">Cédula</th>
                    <th class="px-2 py-2 text-left min-w-[190px]">Nombres y apellidos</th>
                    <th class="px-2 py-2 text-center">Nacimiento</th>
                    <th class="px-2 py-2 text-left min-w-[150px]">Observaciones</th>
                    <th class="px-2 py-2 text-center">Acción</th>
                </tr>
            </thead>
            <tbody>
                {#each data.students as student, index}
                    <tr class="border-b border-gray-100 hover:bg-gray-50 align-middle">
                        <td class="px-2 py-1.5">{String(index + 1).padStart(2, "0")}</td>
                        <td class="px-2 py-1.5">
                            <input
                                bind:value={serials[student.id]}
                                placeholder="AB0000000"
                                class="w-32 rounded border border-gray-200 p-1 text-xs uppercase"
                            />
                        </td>
                        <td class="px-2 py-1.5">{student.document_type}-{student.ci}</td>
                        <td class="px-2 py-1.5 font-medium text-gray-700">{student.full_name}</td>
                        <td class="px-2 py-1.5 text-center text-gray-600">{fmtDate(student)}</td>
                        <td class="px-2 py-1.5">
                            <input
                                bind:value={observations[student.id]}
                                placeholder="Opcional"
                                class="w-full min-w-[140px] rounded border border-gray-200 p-1 text-xs"
                            />
                        </td>
                        <td class="px-2 py-1.5 text-center">
                            <button
                                type="button"
                                disabled={savingId === student.id}
                                on:click={() => saveSerial(student)}
                                class="px-2 py-1 rounded bg-color2 text-white text-[11px] font-semibold disabled:opacity-50"
                            >
                                {savingId === student.id ? "Guardando…" : "Guardar"}
                            </button>
                        </td>
                    </tr>
                {:else}
                    <tr>
                        <td colspan="7" class="text-center text-gray-400 py-8">
                            No hay estudiantes para los criterios seleccionados.
                        </td>
                    </tr>
                {/each}
            </tbody>
        </table>
    </div>
</div>
