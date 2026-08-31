<script>
    import { router } from "@inertiajs/svelte";
    import Modal from "../../components/Modal.svelte";
    import Table from "../../components/Table.svelte";
    import Alert from "../../components/Alert.svelte";
    import PlanUnitsView from "../../components/PlanUnitsView.svelte";
    import { displayAlert } from "../../stores/alertStore";
    import SelectableRow from "../../components/SelectableRow.svelte";
    import Search from "../../components/Search.svelte";
    import { fade, fly } from "svelte/transition";

    export let data = [];
    export let filters = {};

    let selectedRow = { status: false, data: null };
    let showModal = false;
    let rejectMode = false;
    let rejectNote = "";
    let rejectingPlanId = null;

    let pendingQueue = [];
    let currentPlanId = null;
    let currentQueueIndex = 0;

    const statusBadges = {
        pending: "bg-yellow text-gray-800",
        approved: "bg-green-100 text-green-700",
        rejected: "bg-red text-white",
    };

    function schoolLapseForToday() {
        const today = new Date().toISOString().slice(0, 10);
        const byDate = data.school_lapses?.find((l) => {
            const ranges = (l.lapses || [])
                .map((m) =>
                    m.start && m.end ? { s: m.start, e: m.end } : null,
                )
                .filter(Boolean)
                .sort((a, b) => (a.s < b.s ? -1 : 1));
            if (!ranges.length) return false;
            return today >= ranges[0].s && today <= ranges[ranges.length - 1].e;
        });
        return (
            byDate ||
            data.school_lapses?.find((l) => l.is_active) ||
            data.school_lapses?.[0] ||
            null
        );
    }

    const activeSchoolLapse = schoolLapseForToday();

    $: defaultStatus = (data.plans ?? []).some((plan) => plan.status === "pending")
        ? "pending"
        : "approved";
    $: effectiveStatus = filters.status || defaultStatus;

    $: selectedSchoolLapse =
        data.school_lapses?.find(
            (l) => String(l.id) === String(filters.school_lapse_id),
        ) || activeSchoolLapse;
    $: momentOptions = selectedSchoolLapse?.lapses || [];

    $: extraSearchParams = {
        ...(effectiveStatus ? { status: effectiveStatus } : {}),
        ...(filters.school_lapse_id || activeSchoolLapse?.id
            ? { school_lapse_id: filters.school_lapse_id || activeSchoolLapse?.id }
            : {}),
        ...(filters.lapse_id ? { lapse_id: filters.lapse_id } : {}),
        ...(filters.course_id ? { course_id: filters.course_id } : {}),
        ...(filters.section_id ? { section_id: filters.section_id } : {}),
        ...(filters.matter_id ? { matter_id: filters.matter_id } : {}),
        ...(filters.teacher_id ? { teacher_id: filters.teacher_id } : {}),
    };

    function buildParams(overrides = {}) {
        const params = {
            status: effectiveStatus || "",
            search: filters.search || "",
            school_lapse_id:
                filters.school_lapse_id || activeSchoolLapse?.id || "",
            lapse_id: filters.lapse_id || "",
            course_id: filters.course_id || "",
            section_id: filters.section_id || "",
            matter_id: filters.matter_id || "",
            teacher_id: filters.teacher_id || "",
            ...overrides,
        };
        if (overrides.school_lapse_id !== undefined && overrides.school_lapse_id !== filters.school_lapse_id) {
            // Cambiar de período resetea el momento
            params.lapse_id = "";
        }
        Object.keys(params).forEach((k) => {
            if (params[k] === null || params[k] === undefined || params[k] === "") {
                delete params[k];
            }
        });
        return params;
    }

    function applyFilter(key, value) {
        const overrides = { [key]: value };
        router.get("/dashboard/planes-evaluacion", buildParams(overrides), {
            preserveState: true,
            replace: true,
        });
    }

    function openPlanFor(target) {
        pendingQueue = (data.plans || []).filter(
            (p) => p.status === "pending",
        );
        currentPlanId = target?.id ?? null;
        currentQueueIndex = pendingQueue.findIndex(
            (p) => p.id === currentPlanId,
        );
        rejectMode = false;
        rejectNote = "";
        rejectingPlanId = null;
        selectedRow = { status: true, data: target };
        showModal = true;
    }

    function openPlan() {
        openPlanFor(selectedRow.data);
    }

    // Avanza al siguiente plan pendiente de la cola actual (botón "Siguiente ›").
    function advanceToNextPlan() {
        const nextIndex = currentQueueIndex + 1;
        const next = pendingQueue[nextIndex];
        if (!next) {
            return;
        }
        currentPlanId = next.id;
        currentQueueIndex = nextIndex;
        selectedRow = { status: true, data: next };
        rejectMode = false;
        rejectNote = "";
        rejectingPlanId = null;
        showModal = true;
    }

    // Tras aprobar/rechazar en modo "swipe" (filtro en pendiente) se envía el
    // próximo plan pendiente para que el servidor lo auto-abra tras el reload.
    function nextPlanId(id) {
        if (effectiveStatus !== "pending") {
            return null;
        }
        const currentIdx = pendingQueue.findIndex((p) => p.id === id);
        const next = pendingQueue[currentIdx + 1];
        return next ? next.id : null;
    }

    function rejectPlanStart() {
        rejectMode = true;
        rejectNote = "";
    }

    function approvePlan(id) {
        const next = nextPlanId(id);
        router.post(
            `/dashboard/planes-evaluacion/${id}/aprobar`,
            { ...buildParams(), next_plan: next },
            {
                preserveScroll: true,
                onSuccess: () => {
                    if (!next) {
                        showModal = false;
                    }
                    displayAlert({
                        type: "success",
                        message: "Plan aprobado correctamente",
                    });
                },
                onError: (errors) => {
                    displayAlert({
                        type: "error",
                        message: errors.message || "Error al aprobar el plan",
                    });
                },
            },
        );
    }

    function confirmReject(id) {
        const next = nextPlanId(id);
        router.post(
            `/dashboard/planes-evaluacion/${id}/rechazar`,
            { ...buildParams(), admin_note: rejectNote, next_plan: next },
            {
                preserveScroll: true,
                onSuccess: () => {
                    if (!next) {
                        showModal = false;
                    }
                    displayAlert({
                        type: "success",
                        message: "Plan rechazado correctamente",
                    });
                },
                onError: (errors) => {
                    displayAlert({
                        type: "error",
                        message: errors.message || "Error al rechazar el plan",
                    });
                },
            },
        );
    }

    // Auto-abre el siguiente plan pendiente cuando el servidor indica uno tras
    // aprobar/rechazar en modo "swipe" (el componente se conserva entre la
    // redirección, por lo que esto debe reaccionar a las props, no a onMount).
    $: if (filters.open_plan && currentPlanId !== Number(filters.open_plan)) {
        const target = (data.plans || []).find(
            (p) => String(p.id) === String(filters.open_plan),
        );
        if (target) {
            openPlanFor(target);
        }
    }
</script>

<svelte:head>
    <title>Planes de Evaluación</title>
</svelte:head>

<Alert />


<Search {extraSearchParams} />

<div class="flex flex-wrap gap-3 mb-4 bg-white border border-gray-200 rounded-lg p-3">
    <div class="flex items-center gap-2">
        <label class="text-sm font-semibold text-gray-600">Estado</label>
        <select
            class="rounded-md border border-gray-300 px-3 py-2 text-sm"
            value={effectiveStatus}
            on:change={(e) => applyFilter("status", e.target.value)}
        >
            {#each data.statuses as st}
                <option value={st.value}>{st.label}</option>
            {/each}
        </select>
    </div>
    <div class="flex items-center gap-2">
        <label class="text-sm font-semibold text-gray-600">Período escolar</label>
        <select
            class="rounded-md border border-gray-300 px-3 py-2 text-sm"
            value={String(filters.school_lapse_id || activeSchoolLapse?.id || "")}
            on:change={(e) => applyFilter("school_lapse_id", e.target.value)}
        >
            {#each data.school_lapses as lapse}
                <option value={String(lapse.id)}>{lapse.label}</option>
            {/each}
        </select>
    </div>
    <div class="flex items-center gap-2">
        <label class="text-sm font-semibold text-gray-600">Momento</label>
        <select
            class="rounded-md border border-gray-300 px-3 py-2 text-sm"
            value={String(filters.lapse_id || "")}
            on:change={(e) => applyFilter("lapse_id", e.target.value)}
        >
            <option value="">Todos</option>
            {#each momentOptions as mom}
                <option value={String(mom.id)}>{mom.label}</option>
            {/each}
        </select>
    </div>
    <div class="flex items-center gap-2">
        <label class="text-sm font-semibold text-gray-600">Año</label>
        <select
            class="rounded-md border border-gray-300 px-3 py-2 text-sm"
            value={String(filters.course_id || "")}
            on:change={(e) => applyFilter("course_id", e.target.value)}
        >
            <option value="">Todos</option>
            {#each data.courses as course}
                <option value={String(course.id)}>{course.name}</option>
            {/each}
        </select>
    </div>
    <div class="flex items-center gap-2">
        <label class="text-sm font-semibold text-gray-600">Sección</label>
        <select
            class="rounded-md border border-gray-300 px-3 py-2 text-sm"
            value={String(filters.section_id || "")}
            on:change={(e) => applyFilter("section_id", e.target.value)}
        >
            <option value="">Todas</option>
            {#each data.sections as section}
                <option value={String(section.id)}>{section.name}</option>
            {/each}
        </select>
    </div>
</div>

<Table
    {selectedRow}
    allowFilters={false}
    filtersOptions={{}}
    serverSideData={{ filters: {} }}
    pagination={false}
    edit={false}
    otherSelectOptions={[
        {
            label: "Ver plan",
            icon: "mdi:eye",
            classes: "bg-blue text-white",
            onClick: openPlan,
        },
    ]}
>
    <thead slot="thead" class="sticky top-0 z-50">
        <tr>
            <th>N°</th>
            <th>Profesor</th>
            <th>Materia</th>
            <th>Momento</th>
            <th>Curso / Sección</th>
            <th>Estado</th>
            <th>Fecha</th>
        </tr>
    </thead>
    <tbody slot="tbody">
        {#each (data.plans || []) as plan, i}
            <SelectableRow
                rowData={plan}
                idKey="id"
                {selectedRow}
                activeClass="bg-yellow bg-opacity-10 brightness-110"
                on:select={(e) => {
                    if (e.detail.status && e.detail.data) {
                        openPlanFor(e.detail.data);
                    } else {
                        selectedRow = e.detail;
                    }
                }}
            >
                <td>{i + 1}</td>
               
                <td>{plan.teacher_name}</td>
                <td>{plan.matter_name}</td>
                <td>{plan.lapse_label || "—"}</td>
                <td>
                    {plan.course_name || "—"}
                    {#if plan.section_name}· {plan.section_name}{/if}
                </td>
                <td>
                    <span
                        class="px-2 py-0.5 rounded text-xs font-bold {statusBadges[plan.status] || ''}"
                    >
                        {plan.status_label}
                    </span>
                </td>
                <td class="text-xs">{plan.created_at}</td>
            </SelectableRow>
        {/each}
    </tbody>
</Table>

<Modal bind:showModal classes={"w-fit"}>
    {#if selectedRow.data}
        {@const plan = selectedRow.data}
        {#if effectiveStatus === "pending" && pendingQueue.length > 1}
            <div class="flex items-center justify-between mb-3 mt-4 text-xs text-gray-500">
                <span>
                    Plan {currentQueueIndex + 1} de {pendingQueue.length}
                    (pendiente)
                </span>
                {#if currentQueueIndex < pendingQueue.length - 1}
                    <button
                        on:click={() => {
                            if (currentQueueIndex + 1 < pendingQueue.length) {
                                currentQueueIndex += 1;
                                currentPlanId = pendingQueue[currentQueueIndex].id;
                                selectedRow = {
                                    status: true,
                                    data: pendingQueue[currentQueueIndex],
                                };
                                rejectMode = false;
                                rejectNote = "";
                                rejectingPlanId = null;
                            }
                        }}
                        class="text-color1 hover:underline"
                        title="Ir al siguiente plan pendiente"
                    >
                        Siguiente ›
                    </button>
                {/if}
            </div>
        {/if}
        {#key plan.id}
            <div
                in:fly={{ y: 10, duration: 180 }}
                out:fade={{ duration: 120 }}
            >
                <PlanUnitsView {plan} />

                {#if plan.status === "pending"}
                    <div class="mt-5 flex flex-col gap-3">
                        {#if rejectMode}
                            <textarea
                                bind:value={rejectNote}
                                placeholder="Motivo del rechazo (opcional)"
                                rows="3"
                                class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm"
                            ></textarea>
                            <div class="flex gap-3 justify-end">
                                <button
                                    on:click={() => (rejectMode = false)}
                                    class="px-4 py-2 text-sm border border-gray-300 rounded-md"
                                >
                                    Cancelar
                                </button>
                                <button
                                    on:click={() => confirmReject(plan.id)}
                                    class="px-4 py-2 text-sm bg-red text-white rounded-md"
                                >
                                    Confirmar rechazo
                                </button>
                            </div>
                        {:else}
                            <div class="flex gap-3 justify-center">
                                <button
                                    on:click={() => {
                                        rejectMode = true;
                                        rejectingPlanId = plan.id;
                                    }}
                                    class="hover:shadow-xl hover:bg-red hover:text-white px-10 py-2 text-sm flex items-center bg-gray-300 text-gray-600 rounded-md"
                                >
                                <iconify-icon icon="mdi:close-thick" class="mr-1" width="18" height="18" />
                                    Rechazar
                                </button>
                                <button
                                    on:click={() => approvePlan(plan.id)}
                                    class="hover:shadow-xl hover:bg-[#c5e5e4] hover:border hover:border-color1  hover:text-color1 px-10 py-2 text-sm flex items-center bg-color1 text-white rounded-md"
                                >
                                <iconify-icon icon="mdi:check" class="mr-1" width="18" height="18" />
                                    Aprobar
                                </button>
                            </div>
                        {/if}
                    </div>
                {:else if plan.status === "approved"}
                    <div class="mt-5 flex justify-end">
                        <button
                            on:click={() => rejectPlanStart()}
                            class="px-4 py-2 text-sm bg-red text-white rounded-md"
                        >
                            Rechazar
                        </button>
                    </div>
                {:else if plan.status === "rejected"}
                    <div class="mt-5 flex justify-end">
                        <button
                            on:click={() => approvePlan(plan.id)}
                            class="px-4 py-2 text-sm bg-color1 text-white rounded-md"
                        >
                            Aprobar
                        </button>
                    </div>
                {/if}
            </div>
        {/key}
    {/if}
</Modal>
