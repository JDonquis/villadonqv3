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

    $: isRepeating = !!(data.student && data.student.is_repeating);

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

    function toggleRepeating() {
        const wasRepeating = isRepeating;
        const message = wasRepeating
            ? "¿Quitar la marca de repitiente a este estudiante?"
            : "¿Marcar que este estudiante repetirá el grado en el próximo período escolar?";
        if (!confirm(message)) return;

        router.patch(
            `/dashboard/matricula/${student.student_id}/repitencia`,
            {},
            {
                preserveScroll: true,
                onSuccess: () => {
                    displayAlert({
                        type: "success",
                        message: wasRepeating
                            ? "Se quitó la repetición."
                            : "Estudiante marcado como repitiente.",
                    });
                },
                onError: (errors) => {
                    displayAlert({
                        type: "error",
                        message:
                            errors.message ||
                            "No se pudo actualizar la repitencia.",
                    });
                },
            },
        );
    }

    function periodLabel(inscription) {
        return inscription?.period || "";
    }

    function initials() {
        return `${student.student_name?.[0] || ""}${student.student_last_name?.[0] || ""}`;
    }

    function fullName() {
        return `${student.student_name || ""} ${student.student_last_name || ""}`.trim();
    }

    function ciLabel() {
        if (!student.student_ci) return "—";
        const prefix = student.student_document_type
            ? `${student.student_document_type}-`
            : "";
        return `${prefix}${student.student_ci}`;
    }

    function birthDate() {
        if (!student.student_date_birth) return null;
        const d = new Date(student.student_date_birth);
        if (Number.isNaN(d.getTime())) return null;
        return d.toLocaleDateString("es-VE", {
            day: "2-digit",
            month: "2-digit",
            year: "numeric",
        });
    }

    function telHref(phone) {
        const digits = String(phone || "").replace(/\D/g, "");
        return digits ? `tel:${digits}` : null;
    }

    function locationLabel() {
        const parts = [student.city, student.state].filter(Boolean);
        return parts.length ? parts.join(", ") : null;
    }

    function ciLabelForRep() {
        if (!student.rep_ci) return null;
        return `${student.rep_document_type ? `${student.rep_document_type}-` : ""}${student.rep_ci}`;
    }

    function hasRep() {
        return Boolean(student.rep_name && student.rep_last_name);
    }

    function hasSecondRep() {
        return Boolean(
            student.second_rep_name || student.second_rep_last_name,
        );
    }

    // Documentación del período actual (para el checklist de admisión).
    $: currentInscription =
        inscriptions.find((i) => i.is_current) ||
        inscriptions[inscriptions.length - 1] ||
        null;

    $: uploadedTypeIds = new Set(
        (currentInscription?.documents || []).map((d) => d.type_document_id),
    );

    $: admissionChecklist = documentTypes
        .filter((t) => t.required)
        .map((t) => ({ ...t, uploaded: uploadedTypeIds.has(t.id) }));

    $: totalDocuments = inscriptions.reduce(
        (sum, ins) => sum + (ins.documents?.length || 0),
        0,
    );

    function missingRequiredFor(ins) {
        const ids = new Set((ins.documents || []).map((d) => d.type_document_id));
        return documentTypes.filter((t) => t.required && !ids.has(t.id)).length;
    }
</script>

<svelte:head>
    <title>Detalle del Estudiante</title>
</svelte:head>

<Alert />

<!-- ===== Page title + quick actions ===== -->
<div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
    <div class="flex flex-wrap items-center gap-3">
        <button
            on:click={() => router.visit("/dashboard/matricula")}
            class="group inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-600 shadow-sm transition-all hover:bg-slate-50 hover:text-slate-900"
        >
            <iconify-icon
                icon="mdi:arrow-left"
                class="text-slate-400 transition-transform group-hover:-translate-x-0.5"
                width="16"
                height="16"
            ></iconify-icon>
            Volver a Matrícula
        </button>
        <h1 class="text-xl font-bold tracking-tight text-color1">
            Expediente del Estudiante
        </h1>
    </div>

    <div class="flex flex-wrap items-center gap-2.5">
        {#if progress.graduate}
            <span
                class="inline-flex items-center gap-1.5 rounded-xl border border-purple/60 bg-purple/30 px-3.5 py-2 text-xs font-bold text-color1"
            >
                <iconify-icon icon="mdi:school-outline" width="16" height="16"></iconify-icon>
                Graduado
            </span>
        {:else if isRepeating}
            <span
                class="inline-flex items-center gap-1.5 rounded-xl border border-orange/50 bg-orange/20 px-3.5 py-2 text-xs font-bold text-dark"
            >
                <iconify-icon icon="mdi:repeat" width="16" height="16"></iconify-icon>
                Repite grado
            </span>
            <button
                on:click={toggleRepeating}
                class="inline-flex items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3.5 py-2 text-xs font-semibold text-slate-700 shadow-sm transition-colors hover:bg-slate-50"
            >
                Quitar repetición
            </button>
        {:else}
            <button
                on:click={toggleRepeating}
                class="inline-flex items-center gap-1.5 rounded-xl border border-orange/40 bg-orange/15 px-3.5 py-2 text-xs font-semibold text-dark shadow-sm transition-colors hover:bg-orange/25"
            >
                <iconify-icon icon="mdi:repeat" width="16" height="16"></iconify-icon>
                Marcar que repite grado
            </button>
        {/if}
    </div>
</div>

<!-- ===== 12-column grid ===== -->
<div class="grid grid-cols-1 items-start gap-6 lg:grid-cols-12">
    <!-- ============ LEFT COLUMN (8) ============ -->
    <div class="space-y-6 lg:col-span-8">
        <!-- Hero student card -->
        <section
            class="relative overflow-hidden rounded-2xl border border-slate-200 bg-white p-6 shadow-card md:p-7"
        >
            <div
                class="pointer-events-none absolute -right-16 -top-16 h-64 w-64 rounded-full bg-color4/20 blur-3xl"
            ></div>

            <div class="relative flex flex-col items-start gap-6 md:flex-row">
                <div class="relative shrink-0">
                    <div
                        class="flex h-20 w-20 items-center justify-center rounded-2xl bg-gradient-to-br from-color2 via-color2 to-color3 text-2xl font-extrabold tracking-wider text-white shadow-md ring-4 ring-white md:h-24 md:w-24 md:text-3xl"
                    >
                        {initials()}
                    </div>
                </div>

                <div class="min-w-0 flex-1 space-y-3">
                    <div
                        class="flex flex-col justify-between gap-2 sm:flex-row sm:items-start"
                    >
                        <div class="min-w-0">
                            <h2
                                class="text-2xl font-extrabold leading-tight tracking-tight text-color1"
                            >
                                {fullName()}
                            </h2>
                            <div class="mt-1 flex flex-wrap items-center gap-2">
                                <span
                                    class="inline-flex items-center rounded-md border border-slate-200 bg-slate-100 px-2.5 py-0.5 font-mono text-xs font-semibold text-slate-800"
                                >
                                    {ciLabel()}
                                </span>
                                {#if student.student_age}
                                    <span class="text-slate-300">•</span>
                                    <span class="text-xs font-medium text-slate-600">
                                        {student.student_age}
                                        {#if birthDate()}años ({birthDate()}){/if}
                                    </span>
                                {/if}
                                {#if student.student_sex}
                                    <span class="text-slate-300">•</span>
                                    <span
                                        class="inline-flex items-center gap-1 text-xs font-medium text-slate-600"
                                    >
                                        <iconify-icon
                                            icon={student.student_sex === "Femenino"
                                                ? "mdi:gender-female"
                                                : "mdi:gender-male"}
                                            class="text-redLight"
                                            width="14"
                                            height="14"
                                        ></iconify-icon>
                                        {student.student_sex}
                                    </span>
                                {/if}
                            </div>
                        </div>

                        <!-- Status tag -->
                        <div class="shrink-0">
                            {#if progress.graduate}
                                <span
                                    class="inline-flex items-center gap-1.5 rounded-full border border-purple/50 bg-purple/20 px-3 py-1 text-xs font-bold text-color1"
                                >
                                    <iconify-icon
                                        icon="mdi:school-outline"
                                        width="14"
                                        height="14"
                                    ></iconify-icon>
                                    Egresado
                                </span>
                            {:else if isRepeating}
                                <span
                                    class="inline-flex items-center gap-1.5 rounded-full border border-orange/50 bg-orange/20 px-3 py-1 text-xs font-bold text-dark"
                                >
                                    <iconify-icon icon="mdi:repeat" width="14" height="14"></iconify-icon>
                                    Repite grado
                                </span>
                            {:else}
                                <span
                                    class="inline-flex items-center gap-1.5 rounded-full border border-emerald-200 bg-emerald-50 px-3 py-1 text-xs font-bold text-emerald-700"
                                >
                                    <iconify-icon
                                        icon="mdi:check-circle-outline"
                                        class="text-emerald-600"
                                        width="14"
                                        height="14"
                                    ></iconify-icon>
                                    Matrícula Activa
                                </span>
                            {/if}
                        </div>
                    </div>

                    <!-- Academic placement + residence -->
                    <div
                        class="grid grid-cols-1 gap-3 border-t border-slate-100 pt-3 sm:grid-cols-2"
                    >
                        <div
                            class="flex items-center gap-3 rounded-xl border border-slate-100 bg-slate-50 p-2.5"
                        >
                            <div class="rounded-lg bg-color4/25 p-2 text-color2">
                                <iconify-icon
                                    icon="mdi:school-outline"
                                    width="16"
                                    height="16"
                                ></iconify-icon>
                            </div>
                            <div class="min-w-0">
                                <p
                                    class="text-[11px] font-bold uppercase tracking-wider text-slate-400"
                                >
                                    Nivel y Sección
                                </p>
                                <p class="truncate text-sm font-semibold text-slate-800">
                                    {student.course_name} · Sección {student.section_name}
                                </p>
                            </div>
                        </div>

                        <div
                            class="flex items-center gap-3 rounded-xl border border-slate-100 bg-slate-50 p-2.5"
                        >
                            <div class="rounded-lg bg-emerald-100 p-2 text-emerald-700">
                                <iconify-icon
                                    icon="mdi:map-marker-outline"
                                    width="16"
                                    height="16"
                                ></iconify-icon>
                            </div>
                            <div class="min-w-0 flex-1">
                                <p
                                    class="text-[11px] font-bold uppercase tracking-wider text-slate-400"
                                >
                                    Ubicación y Domicilio
                                </p>
                                <p class="truncate text-xs font-medium text-slate-700" title={student.address || ""}>
                                    {#if locationLabel()}
                                        {locationLabel()}
                                        {#if student.address}
                                            <span class="text-slate-500">· {student.address}</span>
                                        {/if}
                                    {:else if student.address}
                                        {student.address}
                                    {:else}
                                        <span class="italic text-slate-400">Sin domicilio registrado</span>
                                    {/if}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Academic reports -->
        <section
            class="rounded-2xl border border-slate-200 bg-white p-6 shadow-card"
        >
            <div
                class="flex flex-col gap-3 border-b border-slate-100 pb-4 sm:flex-row sm:items-center sm:justify-between"
            >
                <div class="flex items-center gap-3">
                    <div
                        class="rounded-xl border border-color4/40 bg-color4/20 p-2.5 text-color2"
                    >
                        <iconify-icon
                            icon="mdi:file-document-outline"
                            width="20"
                            height="20"
                        ></iconify-icon>
                    </div>
                    <div>
                        <h3 class="text-base font-bold leading-none text-color1">
                            Reportes Académicos
                        </h3>
                        <p class="mt-1 text-xs text-slate-500">
                            Documentación oficial y calificaciones del período
                            <strong>{report.period_label || "actual"}</strong>
                        </p>
                    </div>
                </div>
                <span
                    class="hidden w-fit rounded-lg border border-slate-100 bg-slate-50 px-2.5 py-1 text-[11px] font-medium text-slate-400 md:inline-flex"
                >
                    Formato Oficial MPPE
                </span>
            </div>

            <div
                class="flex flex-col items-stretch justify-between gap-4 pt-5 sm:flex-row sm:items-end"
            >
                <div class="w-full sm:w-64">
                    <label
                        for="report_scope"
                        class="mb-1.5 block text-xs font-bold uppercase tracking-wider text-slate-600"
                    >
                        Alcance del reporte
                    </label>
                    <select
                        id="report_scope"
                        bind:value={reportScope}
                        class="form__field w-full cursor-pointer bg-slate-50 text-xs font-medium text-slate-700 transition-colors hover:bg-slate-100 focus:border-color2 focus:outline-none focus:ring-2 focus:ring-color4/50"
                    >
                        {#each reportScopes as scope}
                            <option value={scope.value}>{scope.label}</option>
                        {/each}
                    </select>
                </div>

                <div class="flex w-full flex-col gap-2.5 sm:w-auto sm:flex-row">
                    <button
                        on:click={() => downloadReport("boleta")}
                        class="inline-flex flex-1 items-center justify-center gap-2 rounded-xl bg-color2 px-4 py-2.5 text-xs font-semibold text-white shadow-sm transition-all hover:bg-color1 sm:flex-initial"
                    >
                        <iconify-icon
                            icon="mdi:download"
                            width="16"
                            height="16"
                        ></iconify-icon>
                        Descargar Boleta
                    </button>
                    <button
                        on:click={() => downloadReport("certificado")}
                        class="inline-flex flex-1 items-center justify-center gap-2 rounded-xl bg-zelle px-4 py-2.5 text-xs font-semibold text-white shadow-sm transition-all hover:bg-purple700 sm:flex-initial"
                    >
                        <iconify-icon
                            icon="mdi:certificate-outline"
                            width="16"
                            height="16"
                        ></iconify-icon>
                        Descargar Certificado
                    </button>
                </div>
            </div>
        </section>

        <!-- Academic progress KPIs -->
        <section
            class="rounded-2xl border border-slate-200 bg-white p-6 shadow-card"
        >
            <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
                <div class="flex items-center gap-2.5">
                    <div class="rounded-lg bg-slate-100 p-2 text-slate-700">
                        <iconify-icon
                            icon="mdi:chart-timeline-variant"
                            width="16"
                            height="16"
                        ></iconify-icon>
                    </div>
                    <h3 class="text-base font-bold text-color1">
                        Progreso Académico y Rendimiento
                    </h3>
                </div>
                {#if progress.graduate}
                    <span
                        class="rounded-md border border-purple/40 bg-purple/20 px-2.5 py-1 text-xs font-semibold text-color1"
                    >
                        Egresado
                    </span>
                {:else}
                    <span
                        class="rounded-md border border-emerald-100 bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-600"
                    >
                        Período {currentInscription
                            ? periodLabel(currentInscription)
                            : "—"}
                    </span>
                {/if}
            </div>

            <div class="grid grid-cols-1 gap-3.5 md:grid-cols-3">
                <div
                    class="rounded-xl border border-slate-200 bg-slate-50 p-4 transition-colors hover:border-slate-300"
                >
                    <p
                        class="mb-1 text-[11px] font-bold uppercase tracking-wider text-slate-400"
                    >
                        Ingresó en
                    </p>
                    <p class="text-base font-bold text-color1">
                        {progress.started_period || "—"}
                    </p>
                    {#if progress.started_course}
                        <p class="mt-0.5 text-xs font-medium text-slate-500">
                            {progress.started_course}
                        </p>
                    {/if}
                </div>

                <div
                    class="rounded-xl border border-slate-200 bg-slate-50 p-4 transition-colors hover:border-slate-300"
                >
                    <p
                        class="mb-1 text-[11px] font-bold uppercase tracking-wider text-slate-400"
                    >
                        Repitió Período
                    </p>
                    {#if progress.repeated?.length}
                        <div class="mt-1 space-y-1">
                            {#each progress.repeated as rep}
                                <p class="text-sm font-bold text-color1">
                                    {rep.course_name}
                                    <span class="text-xs font-normal text-slate-500">
                                        ({rep.periods.join(", ")})
                                    </span>
                                </p>
                            {/each}
                        </div>
                    {:else}
                        <div class="mt-1 flex items-center gap-2">
                            <span class="text-base font-bold text-color1">No</span>
                            <span
                                class="rounded bg-emerald-100 px-2 py-0.5 text-[10px] font-semibold text-emerald-800"
                            >
                                Regular
                            </span>
                        </div>
                        <p class="mt-0.5 text-xs font-medium text-slate-500">
                            Sin historial de repitencia
                        </p>
                    {/if}
                </div>

                <div
                    class="rounded-xl border border-slate-200 bg-slate-50 p-4 transition-colors hover:border-slate-300"
                >
                    <p
                        class="mb-1 text-[11px] font-bold uppercase tracking-wider text-slate-400"
                    >
                        Abandonó y Regresó
                    </p>
                    {#if progress.abandoned_periods?.length}
                        <p class="mt-1 text-sm font-bold text-color1">
                            Abandonó:
                            <span class="text-xs font-normal text-slate-500">
                                {progress.abandoned_periods.join(", ")}
                            </span>
                        </p>
                        <p class="text-sm font-bold text-color1">
                            Regresó:
                            <span class="text-xs font-normal text-slate-500">
                                {progress.returned_period || "—"}
                            </span>
                        </p>
                    {:else}
                        <p class="mt-1 text-base font-bold text-color1">Sin abandonos</p>
                        <p class="mt-0.5 text-xs font-medium text-slate-500">
                            Prosecución continua
                        </p>
                    {/if}
                </div>
            </div>
        </section>

        <!-- Representatives -->
        <section
            class="rounded-2xl border border-slate-200 bg-white p-6 shadow-card"
        >
            <div class="mb-4 flex items-center gap-2.5">
                <div class="rounded-lg bg-indigo-50 p-2 text-indigo-600">
                    <iconify-icon
                        icon="mdi:account-group-outline"
                        width="16"
                        height="16"
                    ></iconify-icon>
                </div>
                <div>
                    <h3 class="text-base font-bold text-color1">
                        Representantes y Contactos de Emergencia
                    </h3>
                    <p class="text-xs text-slate-500">
                        Personas autorizadas legalmente ante la institución
                    </p>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <!-- Primary representative -->
                {#if hasRep()}
                    <div
                        class="flex flex-col justify-between space-y-4 rounded-xl border border-color4/50 bg-color4/10 p-4 transition-all duration-200"
                    >
                        <div>
                            <div class="flex items-center justify-between gap-2">
                                <span
                                    class="inline-flex items-center rounded bg-color4/30 px-2 py-0.5 text-[10px] font-extrabold uppercase tracking-wide text-color2"
                                >
                                    Principal{student.rep_relationship
                                        ? ` · ${prettyName(student.rep_relationship)}`
                                        : ""}
                                </span>
                                {#if student.rep_ci}
                                    <span class="font-mono text-xs font-medium text-slate-500">
                                        {student.rep_document_type
                                            ? `${student.rep_document_type}-`
                                            : ""}{student.rep_ci}
                                    </span>
                                {/if}
                            </div>

                            <div class="mt-2 flex items-start justify-between gap-2">
                                <div class="min-w-0">
                                    <h4 class="truncate text-base font-bold text-color1">
                                        {student.rep_name} {student.rep_last_name}
                                    </h4>
                                    <p class="text-xs text-slate-500">
                                        Representante Legal Asignada
                                    </p>
                                </div>
                            </div>

                            <div class="mt-3.5 space-y-2 text-xs">
                                {#if student.rep_phone_number}
                                    <div class="flex items-center gap-2 text-slate-700">
                                        <iconify-icon
                                            icon="mdi:phone-outline"
                                            class="text-slate-400"
                                            width="14"
                                            height="14"
                                        ></iconify-icon>
                                        <span
                                            class="font-mono-numbers font-medium text-slate-800"
                                        >
                                            {student.rep_phone_number}
                                        </span>
                                        <span class="text-[10px] text-slate-400">
                                            (Móvil principal)
                                        </span>
                                    </div>
                                {/if}
                                {#if student.rep_email}
                                    <div class="flex items-center gap-2 text-slate-700">
                                        <iconify-icon
                                            icon="mdi:email-outline"
                                            class="text-slate-400"
                                            width="14"
                                            height="14"
                                        ></iconify-icon>
                                        <span class="truncate">{student.rep_email}</span>
                                    </div>
                                {/if}
                            </div>

                            <details class="mt-3.5 border-t border-color4/30 pt-3 group">
                                <summary
                                    class="flex cursor-pointer list-none items-center justify-between text-xs font-semibold text-color2 transition-colors hover:text-color1"
                                >
                                    <span class="flex items-center gap-1.5">
                                        <iconify-icon
                                            icon="mdi:chevron-down"
                                            class="chevron transition-transform"
                                            width="14"
                                            height="14"
                                        ></iconify-icon>
                                        Ver información completa
                                    </span>
                                    <span class="text-[10px] font-medium text-slate-400">
                                        Ficha MPPE
                                    </span>
                                </summary>

                                <div
                                    class="mt-3 grid grid-cols-1 gap-2 rounded-lg border border-color4/30 bg-white/70 p-2.5 text-[11px] sm:grid-cols-2"
                                >
                                    <div class="rounded bg-slate-50 p-1.5">
                                        <span
                                            class="block text-[9px] font-bold uppercase tracking-wider text-slate-400"
                                        >
                                            Documento de Identidad
                                        </span>
                                        <span class="font-mono font-semibold text-slate-800">
                                            {ciLabelForRep() || "No registrado"}
                                        </span>
                                    </div>
                                    <div class="rounded bg-slate-50 p-1.5">
                                        <span
                                            class="block text-[9px] font-bold uppercase tracking-wider text-slate-400"
                                        >
                                            Parentesco / Vínculo
                                        </span>
                                        <span class="font-semibold text-slate-800">
                                            {student.rep_relationship
                                                ? prettyName(student.rep_relationship)
                                                : "No registrado"}
                                        </span>
                                    </div>
                                    <div class="rounded bg-slate-50 p-1.5">
                                        <span
                                            class="block text-[9px] font-bold uppercase tracking-wider text-slate-400"
                                        >
                                            Teléfono Alternativo
                                        </span>
                                        <span class="text-slate-600">
                                            {student.rep_phone_number2 || "No registrado"}
                                        </span>
                                    </div>
                                    <div class="rounded bg-slate-50 p-1.5">
                                        <span
                                            class="block text-[9px] font-bold uppercase tracking-wider text-slate-400"
                                        >
                                            Profesión / Ocupación
                                        </span>
                                        <span class="text-slate-600">
                                            {student.rep_profession
                                                ? prettyName(student.rep_profession)
                                                : "No registrada"}
                                        </span>
                                    </div>
                                    <div class="rounded bg-slate-50 p-1.5 sm:col-span-2">
                                        <span
                                            class="block text-[9px] font-bold uppercase tracking-wider text-slate-400"
                                        >
                                            Lugar de Trabajo
                                        </span>
                                        <span class="text-slate-600">
                                            {student.rep_workplace
                                                ? prettyName(student.rep_workplace)
                                                : "No registrado"}
                                        </span>
                                    </div>
                                    <div class="rounded bg-slate-50 p-1.5 sm:col-span-2">
                                        <span
                                            class="block text-[9px] font-bold uppercase tracking-wider text-slate-400"
                                        >
                                            Domicilio Habitual
                                        </span>
                                        <span class="text-slate-700">
                                            {#if locationLabel() || student.address}
                                                {[locationLabel(), student.address]
                                                    .filter(Boolean)
                                                    .join(" · ")}
                                            {:else}
                                                <span class="italic text-slate-400">
                                                    No registrado
                                                </span>
                                            {/if}
                                        </span>
                                    </div>
                                </div>
                            </details>
                        </div>

                        <div
                            class="flex items-center gap-2 border-t border-color4/30 pt-2"
                        >
                            {#if telHref(student.rep_phone_number)}
                                <a
                                    href={telHref(student.rep_phone_number)}
                                    class="inline-flex flex-1 items-center justify-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 shadow-sm transition-colors hover:border-color4 hover:text-color2"
                                >
                                    <iconify-icon
                                        icon="mdi:phone-outline"
                                        class="text-slate-400"
                                        width="12"
                                        height="12"
                                    ></iconify-icon>
                                    Llamar
                                </a>
                            {/if}
                            {#if student.rep_email}
                                <a
                                    href={`mailto:${student.rep_email}`}
                                    class="inline-flex flex-1 items-center justify-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 shadow-sm transition-colors hover:border-color4 hover:text-color2"
                                >
                                    <iconify-icon
                                        icon="mdi:email-outline"
                                        class="text-slate-400"
                                        width="12"
                                        height="12"
                                    ></iconify-icon>
                                    Email
                                </a>
                            {/if}
                        </div>
                    </div>
                {:else}
                    <div
                        class="flex flex-col items-center justify-center space-y-3 rounded-xl border border-dashed border-slate-300 bg-slate-50 p-6 text-center"
                    >
                        <div
                            class="flex h-10 w-10 items-center justify-center rounded-full bg-slate-100 text-slate-400"
                        >
                            <iconify-icon
                                icon="mdi:account-off-outline"
                                width="20"
                                height="20"
                            ></iconify-icon>
                        </div>
                        <div>
                            <span
                                class="inline-flex items-center rounded bg-slate-200/80 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider text-slate-600"
                            >
                                Representante Legal
                            </span>
                            <h4 class="mt-1 text-sm font-bold text-slate-800">
                                Sin representante asignado
                            </h4>
                            <p class="mt-0.5 text-xs text-slate-400">
                                No se ha cargado información del representante en el expediente.
                            </p>
                        </div>
                    </div>
                {/if}

                <!-- Secondary representative -->
                {#if hasSecondRep()}
                    <div
                        class="flex flex-col justify-between space-y-4 rounded-xl border border-slate-200 bg-slate-50/60 p-4 transition-all duration-200"
                    >
                        <div>
                            <div class="flex items-center justify-between gap-2">
                                <span
                                    class="inline-flex items-center rounded bg-slate-200 px-2 py-0.5 text-[10px] font-extrabold uppercase tracking-wide text-slate-700"
                                >
                                    Segundo Representante
                                </span>
                                {#if student.second_rep_ci}
                                    <span class="font-mono text-xs font-medium text-slate-500">
                                        {student.second_rep_document_type
                                            ? `${student.second_rep_document_type}-`
                                            : ""}{student.second_rep_ci}
                                    </span>
                                {/if}
                            </div>

                            <div class="mt-2">
                                <h4 class="truncate text-base font-bold text-color1">
                                    {student.second_rep_name} {student.second_rep_last_name}
                                </h4>
                                <p class="text-xs text-slate-500">
                                    {student.second_rep_relationship
                                        ? prettyName(student.second_rep_relationship)
                                        : "Segundo Representante"}
                                </p>
                            </div>

                            <div class="mt-3.5 space-y-2 text-xs">
                                {#if student.second_rep_phone_number}
                                    <div class="flex items-center gap-2 text-slate-700">
                                        <iconify-icon
                                            icon="mdi:phone-outline"
                                            class="text-slate-400"
                                            width="14"
                                            height="14"
                                        ></iconify-icon>
                                        <span
                                            class="font-mono-numbers font-medium text-slate-800"
                                        >
                                            {student.second_rep_phone_number}
                                        </span>
                                    </div>
                                {/if}
                                {#if student.second_rep_email}
                                    <div class="flex items-center gap-2 text-slate-700">
                                        <iconify-icon
                                            icon="mdi:email-outline"
                                            class="text-slate-400"
                                            width="14"
                                            height="14"
                                        ></iconify-icon>
                                        <span class="truncate">
                                            {student.second_rep_email}
                                        </span>
                                    </div>
                                {/if}
                            </div>

                            <details class="mt-3.5 border-t border-slate-200 pt-3 group">
                                <summary
                                    class="flex cursor-pointer list-none items-center justify-between text-xs font-semibold text-slate-600 transition-colors hover:text-color2"
                                >
                                    <span class="flex items-center gap-1.5">
                                        <iconify-icon
                                            icon="mdi:chevron-down"
                                            class="chevron transition-transform"
                                            width="14"
                                            height="14"
                                        ></iconify-icon>
                                        Ver información completa
                                    </span>
                                </summary>
                                <div
                                    class="mt-3 grid grid-cols-1 gap-2 rounded-lg border border-slate-200 bg-white/70 p-2.5 text-[11px] sm:grid-cols-2"
                                >
                                    <div class="rounded bg-slate-50 p-1.5">
                                        <span
                                            class="block text-[9px] font-bold uppercase tracking-wider text-slate-400"
                                        >
                                            Teléfono Alternativo
                                        </span>
                                        <span class="text-slate-600">
                                            {student.second_rep_phone_number2 || "No registrado"}
                                        </span>
                                    </div>
                                    <div class="rounded bg-slate-50 p-1.5">
                                        <span
                                            class="block text-[9px] font-bold uppercase tracking-wider text-slate-400"
                                        >
                                            Profesión / Ocupación
                                        </span>
                                        <span class="text-slate-600">
                                            {student.second_rep_profession
                                                ? prettyName(student.second_rep_profession)
                                                : "No registrada"}
                                        </span>
                                    </div>
                                    <div class="rounded bg-slate-50 p-1.5 sm:col-span-2">
                                        <span
                                            class="block text-[9px] font-bold uppercase tracking-wider text-slate-400"
                                        >
                                            Lugar de Trabajo
                                        </span>
                                        <span class="text-slate-600">
                                            {student.second_rep_workplace
                                                ? prettyName(student.second_rep_workplace)
                                                : "No registrado"}
                                        </span>
                                    </div>
                                </div>
                            </details>
                        </div>

                        <div class="flex items-center gap-2 border-t border-slate-200 pt-2">
                            {#if telHref(student.second_rep_phone_number)}
                                <a
                                    href={telHref(student.second_rep_phone_number)}
                                    class="inline-flex flex-1 items-center justify-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 shadow-sm transition-colors hover:border-color4 hover:text-color2"
                                >
                                    <iconify-icon
                                        icon="mdi:phone-outline"
                                        class="text-slate-400"
                                        width="12"
                                        height="12"
                                    ></iconify-icon>
                                    Llamar
                                </a>
                            {/if}
                            {#if student.second_rep_email}
                                <a
                                    href={`mailto:${student.second_rep_email}`}
                                    class="inline-flex flex-1 items-center justify-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 shadow-sm transition-colors hover:border-color4 hover:text-color2"
                                >
                                    <iconify-icon
                                        icon="mdi:email-outline"
                                        class="text-slate-400"
                                        width="12"
                                        height="12"
                                    ></iconify-icon>
                                    Email
                                </a>
                            {/if}
                        </div>
                    </div>
                {:else}
                    <div
                        class="flex flex-col items-center justify-center space-y-3 rounded-xl border border-dashed border-slate-300 bg-slate-50 p-6 text-center"
                    >
                        <div
                            class="flex h-10 w-10 items-center justify-center rounded-full bg-slate-100 text-slate-400"
                        >
                            <iconify-icon
                                icon="mdi:account-plus-outline"
                                width="20"
                                height="20"
                            ></iconify-icon>
                        </div>
                        <div>
                            <span
                                class="inline-flex items-center rounded bg-slate-200/80 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider text-slate-600"
                            >
                                Segundo Representante
                            </span>
                            <h4 class="mt-1 text-sm font-bold text-slate-800">
                                Sin registrar
                            </h4>
                            <p class="mt-0.5 text-xs text-slate-400">
                                No se ha cargado información del segundo contacto en el expediente.
                            </p>
                        </div>
                    </div>
                {/if}
            </div>
        </section>

        <!-- Timeline -->
        <section
            class="rounded-2xl border border-slate-200 bg-white p-6 shadow-card"
        >
            <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
                <div class="flex items-center gap-2.5">
                    <div class="rounded-lg bg-orange/20 p-2 text-orange">
                        <iconify-icon
                            icon="mdi:clock-outline"
                            width="16"
                            height="16"
                        ></iconify-icon>
                    </div>
                    <h3 class="text-base font-bold text-color1">
                        Línea de Tiempo por Período Escolar
                    </h3>
                </div>
                <span class="text-xs font-semibold text-slate-400">
                    {inscriptions.length}
                    {inscriptions.length === 1 ? "Período" : "Períodos"}
                    {inscriptions.length === 1 ? "Registrado" : "Registrados"}
                </span>
            </div>

            {#if inscriptions.length === 0}
                <p class="text-sm text-slate-500">
                    Este estudiante no tiene inscripciones registradas.
                </p>
            {:else}
                <div class="relative space-y-5 before:absolute before:bottom-2 before:left-2.5 before:top-2 before:w-0.5 before:bg-slate-200">
                    {#each inscriptions as ins, i}
                        {@const missing = missingRequiredFor(ins)}
                        <div class="relative">
                            <div
                                class="absolute -left-[1.85rem] top-3.5 flex h-6 w-6 items-center justify-center rounded-full text-[11px] font-bold text-white shadow-sm ring-4 ring-white {ins.is_current
                                    ? 'bg-color2'
                                    : 'bg-slate-400'}"
                            >
                                {i + 1}
                            </div>

                            <div
                                class="flex flex-col justify-between gap-3 rounded-xl border border-slate-200 bg-slate-50 p-4 transition-colors hover:bg-slate-100/60 sm:flex-row sm:items-center"
                            >
                                <div class="min-w-0">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <h4 class="text-sm font-bold text-color1">
                                            Período {periodLabel(ins)}
                                        </h4>
                                        {#if ins.is_current}
                                            <span
                                                class="inline-flex items-center rounded-full border border-orange/50 bg-orange/20 px-2.5 py-0.5 text-[10px] font-extrabold uppercase tracking-wide text-dark"
                                            >
                                                Período Actual
                                            </span>
                                        {/if}
                                        {#if ins.is_repeated}
                                            <span
                                                class="inline-flex items-center rounded-full border border-purple/50 bg-purple/20 px-2.5 py-0.5 text-[10px] font-extrabold uppercase tracking-wide text-color1"
                                            >
                                                Repitió
                                            </span>
                                        {/if}
                                    </div>
                                    <p class="mt-1 text-xs text-slate-600">
                                        {ins.course_name} · Sección {ins.section_name}
                                    </p>
                                    <div
                                        class="mt-2 flex flex-wrap items-center gap-2 text-[11px] font-medium text-slate-500"
                                    >
                                        <span>
                                            {ins.documents.length} documento(s) adjuntado(s)
                                        </span>
                                        <span class="text-slate-300">·</span>
                                        {#if missing === 0}
                                            <span class="font-semibold text-emerald-600">
                                                Inscripción Completada
                                            </span>
                                        {:else}
                                            <span class="font-semibold text-orange">
                                                Faltan {missing} documento(s) requerido(s)
                                            </span>
                                        {/if}
                                    </div>
                                </div>

                                <span
                                    class="self-start rounded-lg border border-slate-200 bg-white px-2.5 py-1 text-[11px] font-semibold text-slate-600 sm:self-auto"
                                >
                                    {ins.documents.length} doc(s)
                                </span>
                            </div>
                        </div>
                    {/each}
                </div>
            {/if}
        </section>
    </div>

    <!-- ============ RIGHT COLUMN (4) ============ -->
    <div class="space-y-6 lg:col-span-4">
        <!-- Upload document -->
        <section
            class="rounded-2xl border border-slate-200 bg-white p-6 shadow-card"
        >
            <div
                class="mb-4 flex items-center gap-2.5 border-b border-slate-100 pb-3"
            >
                <div class="rounded-lg bg-color4/20 p-2 text-color2">
                    <iconify-icon
                        icon="mdi:file-upload-outline"
                        width="16"
                        height="16"
                    ></iconify-icon>
                </div>
                <div>
                    <h3 class="text-base font-bold leading-tight text-color1">
                        Adjuntar Documento
                    </h3>
                    <p class="text-xs text-slate-500">
                        Expediente digital del alumno
                    </p>
                </div>
            </div>

            <form on:submit={handleUploadSubmit} class="space-y-3">
                <div>
                    <label
                        for="doc_inscription"
                        class="mb-1.5 block text-xs font-semibold text-slate-700"
                    >
                        Período escolar
                    </label>
                    <select
                        id="doc_inscription"
                        bind:value={$uploadForm.inscription_id}
                        class="form__field w-full bg-slate-50 text-xs font-medium text-slate-800 focus:border-color2 focus:outline-none focus:ring-2 focus:ring-color4/50"
                    >
                        {#each inscriptions as ins}
                            <option value={ins.id}>
                                {periodLabel(ins)} · {ins.course_name}
                            </option>
                        {/each}
                    </select>
                </div>

                <div>
                    <label
                        for="doc_type"
                        class="mb-1.5 flex items-center justify-between text-xs font-semibold text-slate-700"
                    >
                        <span>Tipo de documento</span>
                    </label>
                    <select
                        id="doc_type"
                        bind:value={$uploadForm.type_document_id}
                        class="form__field w-full bg-slate-50 text-xs font-medium text-slate-800 focus:border-color2 focus:outline-none focus:ring-2 focus:ring-color4/50"
                    >
                        {#each documentTypes as type}
                            <option value={type.id}>
                                {prettyName(type.name)}{type.required ? " *" : ""}
                            </option>
                        {/each}
                    </select>
                </div>

                <div>
                    <label
                        for="doc_file"
                        class="mb-1.5 block text-xs font-semibold text-slate-700"
                    >
                        Archivo (PDF o imagen)
                    </label>
                    <input
                        id="doc_file"
                        type="file"
                        accept=".pdf,.jpg,.jpeg,.png,.webp"
                        class="form__field w-full text-xs file:mr-3 file:rounded-lg file:border-0 file:bg-color2 file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-white hover:file:bg-color1"
                        bind:this={fileInput}
                        on:change={(e) =>
                            ($uploadForm.document = e.target.files[0] || null)}
                    />
                    {#if $uploadForm.errors?.document}
                        <p class="pt-1 text-xs font-semibold text-red">
                            {$uploadForm.errors.document}
                        </p>
                    {/if}
                </div>

                <button
                    type="submit"
                    class="inline-flex w-full items-center justify-center gap-2 rounded-xl bg-color1 px-4 py-2.5 text-xs font-semibold text-white shadow-sm transition-all hover:bg-color2 focus:ring-2 focus:ring-offset-2 focus:ring-color1 disabled:opacity-60"
                    disabled={$uploadForm.processing}
                >
                    {#if $uploadForm.processing}
                        Cargando...
                    {:else}
                        <iconify-icon
                            icon="mdi:tray-arrow-up"
                            width="16"
                            height="16"
                        ></iconify-icon>
                        Adjuntar al Expediente
                    {/if}
                </button>
            </form>
        </section>

        <!-- Attached documents -->
        <section
            class="rounded-2xl border border-slate-200 bg-white p-6 shadow-card"
        >
            <div
                class="mb-4 flex items-center justify-between gap-2 border-b border-slate-100 pb-3"
            >
                <div class="flex items-center gap-2.5">
                    <div class="rounded-lg bg-emerald-50 p-2 text-emerald-600">
                        <iconify-icon
                            icon="mdi:folder-multiple-image"
                            width="16"
                            height="16"
                        ></iconify-icon>
                    </div>
                    <div>
                        <h3 class="text-base font-bold leading-tight text-color1">
                            Documentos Adjuntados
                        </h3>
                        <p class="text-xs text-slate-500">
                            {#if currentInscription}
                                Expediente {periodLabel(currentInscription)}
                            {:else}
                                Sin períodos registrados
                            {/if}
                        </p>
                    </div>
                </div>
                <span
                    class="shrink-0 rounded-md border border-slate-200 bg-slate-50 px-2 py-0.5 text-[11px] font-bold text-slate-600"
                >
                    {totalDocuments}
                </span>
            </div>

            {#if totalDocuments === 0}
                <div
                    class="flex flex-col items-center justify-center space-y-2 rounded-xl border border-slate-200 bg-slate-50 p-6 text-center"
                >
                    <div
                        class="flex h-10 w-10 items-center justify-center rounded-full bg-slate-200/60 text-slate-400"
                    >
                        <iconify-icon
                            icon="mdi:inbox-outline"
                            width="20"
                            height="20"
                        ></iconify-icon>
                    </div>
                    <div>
                        <p class="text-xs font-bold text-slate-700">
                            Sin documentos en este expediente
                        </p>
                        <p class="mt-0.5 max-w-xs text-[11px] text-slate-400">
                            Utilice el formulario superior para cargar la partida de
                            nacimiento o notas certificadas requeridas.
                        </p>
                    </div>
                </div>
            {:else}
                <div class="space-y-5">
                    {#each inscriptions as ins}
                        {#if ins.documents.length > 0}
                            <div>
                                <p
                                    class="mb-2 flex items-center justify-between gap-2 text-xs font-semibold text-slate-700"
                                >
                                    <span class="truncate">
                                        {periodLabel(ins)} · {ins.course_name}
                                    </span>
                                    {#if ins.is_current}
                                        <span
                                            class="shrink-0 rounded bg-orange/25 px-1.5 py-0.5 text-[10px] font-bold text-dark"
                                        >
                                            Actual
                                        </span>
                                    {/if}
                                </p>
                                <ul class="space-y-1.5">
                                    {#each ins.documents as doc}
                                        <li
                                            class="flex items-center gap-2 rounded-lg border border-slate-100 bg-slate-50 px-2.5 py-2 transition-colors hover:bg-slate-100"
                                        >
                                            <iconify-icon
                                                icon="mdi:file-document-outline"
                                                class="shrink-0 text-color2"
                                                width="18"
                                                height="18"
                                            ></iconify-icon>
                                            <span class="min-w-0 flex-1 truncate text-sm">
                                                {prettyName(doc.type_document_name)}
                                            </span>
                                            {#if doc.url}
                                                <a
                                                    href={doc.url}
                                                    target="_blank"
                                                    rel="noopener"
                                                    class="shrink-0 text-color2 transition-colors hover:text-color1"
                                                    title="Descargar"
                                                >
                                                    <iconify-icon
                                                        icon="mdi:download"
                                                        width="18"
                                                        height="18"
                                                    ></iconify-icon>
                                                </a>
                                            {/if}
                                            <button
                                                on:click={() => handleDeleteDocument(doc.id)}
                                                class="shrink-0 text-red transition-colors hover:text-red/70"
                                                title="Eliminar"
                                            >
                                                <iconify-icon
                                                    icon="mdi:trash-can-outline"
                                                    width="18"
                                                    height="18"
                                                ></iconify-icon>
                                            </button>
                                        </li>
                                    {/each}
                                </ul>
                            </div>
                        {/if}
                    {/each}
                </div>
            {/if}

            <!-- Admission checklist -->
            {#if admissionChecklist.length > 0}
                <div class="mt-5 border-t border-slate-100 pt-4">
                    <p
                        class="mb-2 text-[11px] font-bold uppercase tracking-wider text-slate-400"
                    >
                        Checklist de Admisión
                    </p>
                    <ul class="space-y-2 text-xs">
                        {#each admissionChecklist as item}
                            <li class="flex items-center justify-between gap-2 text-slate-600">
                                <span class="flex min-w-0 items-center gap-2">
                                    <span
                                        class="h-2 w-2 shrink-0 rounded-full {item.uploaded
                                            ? 'bg-emerald-500'
                                            : 'bg-orange'}"
                                    ></span>
                                    <span class="truncate">
                                        {prettyName(item.name)}
                                    </span>
                                </span>
                                <span
                                    class="shrink-0 rounded px-2 py-0.5 text-[11px] font-semibold {item.uploaded
                                        ? 'bg-emerald-50 text-emerald-600'
                                        : 'bg-orange/20 text-dark'}"
                                >
                                    {item.uploaded ? "Verificado" : "Pendiente"}
                                </span>
                            </li>
                        {/each}
                    </ul>
                </div>
            {/if}
        </section>
    </div>
</div>

<style>
    .shadow-card {
        box-shadow:
            0 1px 3px 0 rgba(15, 23, 42, 0.04),
            0 1px 2px -1px rgba(15, 23, 42, 0.03);
    }

    .font-mono-numbers {
        font-feature-settings: "tnum";
        font-variant-numeric: tabular-nums;
    }

    details[open] .chevron {
        transform: rotate(180deg);
    }

    select,
    input[type="file"] {
        width: 100%;
        padding: 8px 12px;
        border-radius: 10px;
        font-family: inherit;
        border: 1px solid #e2e8f0;
        background-color: #fff;
    }

    @media (min-width: 768px) {
        select,
        input[type="file"] {
            padding: 10px 14px;
        }
    }

    select:focus,
    input[type="file"]:focus {
        outline: none;
        border-color: #1f4287;
    }
</style>
