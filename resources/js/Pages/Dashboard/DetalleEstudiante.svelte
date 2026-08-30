<script>
    import { router, useForm } from "@inertiajs/svelte";
    import Alert from "../../components/Alert.svelte";
    import { displayAlert } from "../../stores/alertStore";

    export let data = [];

    const student = data.student || {};
    const progress = data.progress || {};
    const inscriptions = data.inscriptions || [];
    const documentTypes = data.document_types || [];
    const report = data.report || {};

    const reportScopes = [
        { value: "anual", label: "Anual (todos los momentos)" },
        ...(report.lapses || []).map((l) => ({
            value: String(l.id),
            label: l.label,
        })),
    ];

    let reportScope = "anual";

    function downloadReport(type) {
        const base = `/dashboard/reportes/${type}/${student.student_id}`;
        const url = reportScope === "anual" ? base : `${base}?lapse_id=${reportScope}`;
        window.open(url, "_blank");
    }

    const defaultInscriptionId =
        inscriptions.find((i) => i.is_current)?.id ||
        inscriptions[0]?.id ||
        null;

    let uploadForm = useForm({
        student_id: student.student_id,
        inscription_id: defaultInscriptionId,
        type_document_id: documentTypes[0]?.id || "",
        document: null,
    });

    let fileInput;

    function handleUploadSubmit(event) {
        event.preventDefault();
        $uploadForm.clearErrors();
        $uploadForm.post("/dashboard/matricula/documentos", {
            onSuccess: () => {
                $uploadForm.reset();
                $uploadForm.document = null;
                if (fileInput) fileInput.value = "";
                displayAlert({
                    type: "success",
                    message: "Documento adjuntado correctamente",
                });
            },
            onError: (errors) => {
                if (errors.message) {
                    displayAlert({ type: "error", message: errors.message });
                }
            },
        });
    }

    function handleDeleteDocument(id) {
        if (!confirm("¿Está seguro de eliminar este documento?")) return;

        router.delete(`/dashboard/matricula/documentos/${id}`, {
            preserveScroll: true,
            onSuccess: () => {
                displayAlert({
                    type: "success",
                    message: "Documento eliminado correctamente",
                });
            },
            onError: (errors) => {
                displayAlert({
                    type: "error",
                    message: errors.message || "Error al eliminar el documento",
                });
            },
        });
    }

    function prettyName(name) {
        return name ? name.replace(/_/g, " ") : "";
    }

    function periodLabel(inscription) {
        return inscription?.period || "";
    }
</script>

<svelte:head>
    <title>Detalle del Estudiante</title>
</svelte:head>

<Alert />

<div class="mb-6 flex flex-wrap items-center justify-between gap-3">
    <button
        on:click={() => router.visit("/dashboard/matricula")}
        class="flex items-center gap-2 text-sm font-semibold text-gray-600 hover:text-gray-900"
    >
        <iconify-icon icon="mdi:arrow-left" width="20" height="20"></iconify-icon>
        Volver a Matrícula
    </button>

    {#if progress.graduate}
        <span
            class="px-3 py-1 rounded-md text-sm font-bold text-white bg-purple"
        >
            Graduado
        </span>
    {/if}
</div>

<div class="mb-6 bg-white border border-gray-200 rounded-lg shadow p-5">
    <h3
        class="font-bold text-color1 mb-3 flex items-center gap-2"
    >
        <iconify-icon icon="mdi:file-download-outline"></iconify-icon>
        Reportes ({report.period_label || "Período actual"})
    </h3>
    <div class="flex flex-wrap items-end gap-3">
        <div class="flex flex-col gap-1">
            <label
                for="report_scope"
                class="text-xs md:text-sm font-semibold text-gray-700"
            >
                Alcance
            </label>
            <select
                id="report_scope"
                bind:value={reportScope}
                class="rounded-md border border-gray-300 px-3 py-2 text-sm"
            >
                {#each reportScopes as scope}
                    <option value={scope.value}>{scope.label}</option>
                {/each}
            </select>
        </div>
        <button
            on:click={() => downloadReport("boleta")}
            class="px-4 py-2 bg-blue text-white rounded-md text-sm font-semibold flex items-center gap-2"
        >
            <iconify-icon icon="mdi:file-document" width="18" height="18"></iconify-icon>
            Descargar Boleta
        </button>
        <button
            on:click={() => downloadReport("certificado")}
            class="px-4 py-2 bg-purple text-white rounded-md text-sm font-semibold flex items-center gap-2"
        >
            <iconify-icon icon="mdi:certificate-outline" width="18" height="18"></iconify-icon>
            Descargar Certificado
        </button>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <div class="lg:col-span-2 space-y-6">
        <div
            class="bg-white border border-gray-200 rounded-lg shadow p-5 flex flex-wrap items-center gap-4"
        >
            <div
                class="w-16 h-16 rounded-full bg-color1/10 flex items-center justify-center text-2xl font-bold text-color1"
            >
                {student.student_name?.[0]}{student.student_last_name?.[0]}
            </div>
            <div>
                <h2 class="text-xl font-bold text-gray-800 capitalize">
                    {student.student_name} {student.student_last_name}
                </h2>
                <p class="text-sm text-gray-500">
                    C.I {student.student_document_type ? student.student_document_type + "-" : ""}{student.student_ci}
                    · {student.student_age} años · {student.student_sex}
                </p>
                <p class="text-sm text-gray-500">
                    {student.course_name} · Sección {student.section_name}
                </p>
            </div>
            <div class="ml-auto text-sm text-gray-500">
                <p class="font-semibold text-gray-700">
                    {student.rep_name} {student.rep_last_name}
                </p>
                <p>Rep. Legal · {student.rep_phone_number || "—"}</p>
            </div>
        </div>

        <div class="bg-white border border-gray-200 rounded-lg shadow p-5">
            <h3
                class="font-bold text-color1 mb-4 flex items-center gap-2"
            >
                <iconify-icon icon="mdi:chart-timeline-variant"></iconify-icon>
                Progreso académico
            </h3>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-sm">
                <div class="bg-color1/5 rounded-lg p-4">
                    <p class="font-semibold text-gray-500 uppercase text-xs">
                        Ingresó en
                    </p>
                    <p class="font-bold text-gray-800 mt-1">
                        {progress.started_period || "—"}
                    </p>
                    {#if progress.started_course}
                        <p class="text-gray-500">{progress.started_course}</p>
                    {/if}
                </div>

                <div class="bg-purple/5 rounded-lg p-4">
                    <p class="font-semibold text-gray-500 uppercase text-xs">
                        Repitió período
                    </p>
                    {#if progress.repeated?.length}
                        <div class="mt-1 space-y-1">
                            {#each progress.repeated as rep}
                                <p class="font-bold text-gray-800">
                                    {rep.course_name}
                                    <span class="font-normal text-gray-500">
                                        ({rep.periods.join(", ")})
                                    </span>
                                </p>
                            {/each}
                        </div>
                    {:else}
                        <p class="text-gray-500 mt-1">No</p>
                    {/if}
                </div>

                <div class="bg-red/5 rounded-lg p-4">
                    <p class="font-semibold text-gray-500 uppercase text-xs">
                        Abandonó y regresó
                    </p>
                    {#if progress.abandoned_periods?.length}
                        <p class="font-bold text-gray-800 mt-1">
                            Abandonó:
                            <span class="font-normal text-gray-500">
                                {progress.abandoned_periods.join(", ")}
                            </span>
                        </p>
                        <p class="font-bold text-gray-800">
                            Regresó:
                            <span class="font-normal text-gray-500">
                                {progress.returned_period || "—"}
                            </span>
                        </p>
                    {:else}
                        <p class="text-gray-500 mt-1">Sin abandonos</p>
                    {/if}
                </div>
            </div>
        </div>

        <div class="bg-white border border-gray-200 rounded-lg shadow p-5">
            <h3
                class="font-bold text-color1 mb-4 flex items-center gap-2"
            >
                <iconify-icon icon="mdi:school"></iconify-icon>
                Línea de tiempo por período escolar
            </h3>

            {#if inscriptions.length === 0}
                <p class="text-sm text-gray-500">
                    Este estudiante no tiene inscripciones registradas.
                </p>
            {:else}
                <ol class="relative border-l-2 border-gray-200 ml-3 space-y-6">
                    {#each inscriptions as ins, i}
                        <li class="ml-6">
                            <span
                                class="absolute flex items-center justify-center w-6 h-6 rounded-full -left-[13px] border-2 border-white bg-color1 text-white text-xs"
                            >
                                {i + 1}
                            </span>
                            <div
                                class="flex flex-wrap items-center justify-between gap-2"
                            >
                                <div>
                                    <p
                                        class="font-bold text-gray-800"
                                    >
                                        {periodLabel(ins)}
                                        <span class="font-normal text-gray-500">
                                            · {ins.course_name}
                                        </span>
                                        <span
                                            class="font-normal text-gray-400"
                                        >
                                            · Sección {ins.section_name}
                                        </span>
                                    </p>
                                    <p class="text-xs text-gray-400">
                                        {ins.documents.length} documento(s)
                                        adjuntado(s)
                                    </p>
                                </div>
                                <div class="flex gap-2">
                                    {#if ins.is_repeated}
                                        <span
                                            class="px-2 py-0.5 rounded text-xs font-bold bg-purple/10 text-purple"
                                        >
                                            Repitió
                                        </span>
                                    {/if}
                                    {#if ins.is_current}
                                        <span
                                            class="px-2 py-0.5 rounded text-xs font-bold bg-yellow text-gray-800"
                                        >
                                            Período actual
                                        </span>
                                    {/if}
                                </div>
                            </div>
                        </li>
                    {/each}
                </ol>
            {/if}
        </div>
    </div>

    <div class="space-y-6">
        <div class="bg-white border border-gray-200 rounded-lg shadow p-5">
            <h3
                class="font-bold text-color1 mb-4 flex items-center gap-2"
            >
                <iconify-icon icon="mdi:file-upload-outline"></iconify-icon>
                Adjuntar documento
            </h3>

            <form on:submit={handleUploadSubmit} class="space-y-2">
                <label
                    for="doc_inscription"
                    class="form__label text-xs md:text-sm font-semibold text-gray-700"
                >
                    Período escolar
                </label>
                <select
                    id="doc_inscription"
                    bind:value={$uploadForm.inscription_id}
                    class="form__field w-full"
                >
                    {#each inscriptions as ins}
                        <option value={ins.id}>
                            {periodLabel(ins)} · {ins.course_name}
                        </option>
                    {/each}
                </select>

                <label
                    for="doc_type"
                    class="form__label text-xs md:text-sm font-semibold text-gray-700"
                >
                    Tipo de documento
                </label>
                <select
                    id="doc_type"
                    bind:value={$uploadForm.type_document_id}
                    class="form__field w-full"
                >
                    {#each documentTypes as type}
                        <option value={type.id}>
                            {prettyName(type.name)}
                            {type.required ? " *" : ""}
                        </option>
                    {/each}
                </select>

                <label
                    for="doc_file"
                    class="form__label text-xs md:text-sm font-semibold text-gray-700"
                >
                    Archivo (PDF o imagen)
                </label>
                <input
                    id="doc_file"
                    type="file"
                    accept=".pdf,.jpg,.jpeg,.png,.webp"
                    class="form__field w-full"
                    bind:this={fileInput}
                    on:change={(e) =>
                        ($uploadForm.document = e.target.files[0] || null)}
                />
                {#if $uploadForm.errors?.document}
                    <p class="text-red text-xs font-semibold">
                        {$uploadForm.errors.document}
                    </p>
                {/if}

                <button
                    type="submit"
                    class="animated-button w-full flex justify-center gap-2"
                    disabled={$uploadForm.processing}
                >
                    {#if $uploadForm.processing}
                        Cargando...
                    {:else}
                        <iconify-icon
                            icon="mdi:upload"
                            width="22"
                            height="22"
                        ></iconify-icon>
                        <span>Adjuntar</span>
                    {/if}
                </button>
            </form>
        </div>

        <div class="bg-white border border-gray-200 rounded-lg shadow p-5">
            <h3
                class="font-bold text-color1 mb-4 flex items-center gap-2"
            >
                <iconify-icon icon="mdi:folder-multiple-image"></iconify-icon>
                Documentos adjuntados
            </h3>

            {#if inscriptions.length === 0}
                <p class="text-sm text-gray-500">Sin documentos.</p>
            {:else}
                {#each inscriptions as ins}
                    <div class="mb-5">
                        <p class="font-semibold text-gray-700 text-sm mb-2">
                            {periodLabel(ins)} · {ins.course_name}
                        </p>
                        {#if ins.documents.length === 0}
                            <p class="text-xs text-gray-400 pl-1">
                                Sin documentos en este período.
                            </p>
                        {:else}
                            <ul class="space-y-1">
                                {#each ins.documents as doc}
                                    <li
                                        class="flex items-center gap-3 rounded-md px-2 py-1.5 bg-gray-50 hover:bg-gray-100"
                                    >
                                        <iconify-icon
                                            icon="mdi:file-document-outline"
                                            class="text-color1"
                                        ></iconify-icon>
                                        <span class="text-sm flex-1">
                                            {prettyName(doc.type_document_name)}
                                        </span>
                                        <a
                                            href={doc.url}
                                            target="_blank"
                                            rel="noopener"
                                            class="text-blue hover:underline text-sm"
                                            title="Descargar"
                                        >
                                            <iconify-icon
                                                icon="mdi:download"
                                                width="20"
                                                height="20"
                                            ></iconify-icon>
                                        </a>
                                        <button
                                            on:click={() =>
                                                handleDeleteDocument(doc.id)}
                                            class="text-red hover:text-red/70"
                                            title="Eliminar"
                                        >
                                            <iconify-icon
                                                icon="mdi:trash-can-outline"
                                                width="20"
                                                height="20"
                                            ></iconify-icon>
                                        </button>
                                    </li>
                                {/each}
                            </ul>
                        {/if}
                    </div>
                {/each}
            {/if}
        </div>
    </div>
</div>

<style>
    select,
    input[type="file"] {
        width: 100%;
        padding: 8px 12px;
        border-radius: 6px;
        font-family: inherit;
        border: 1px solid #ccc;
    }

    @media (min-width: 768px) {
        select {
            padding: 10px 14px;
        }
    }

    select:focus,
    input[type="file"]:focus {
        outline: none;
    }
</style>

