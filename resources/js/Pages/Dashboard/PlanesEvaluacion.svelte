<script>
    import { router } from "@inertiajs/svelte";
    import Modal from "../../components/Modal.svelte";
    import Table from "../../components/Table.svelte";
    import Alert from "../../components/Alert.svelte";
    import PlanUnitsView from "../../components/PlanUnitsView.svelte";
    import { displayAlert } from "../../stores/alertStore";
    import SelectableRow from "../../components/SelectableRow.svelte";
    import Search from "../../components/Search.svelte";

    export let data = [];
    export let filters = {};

    let selectedRow = { status: false, data: null };
    let showModal = false;
    let rejectMode = false;
    let rejectNote = "";
    let rejectingPlanId = null;

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

    $: selectedSchoolLapse =
        data.school_lapses?.find(
            (l) => String(l.id) === String(filters.school_lapse_id),
        ) || activeSchoolLapse;
    $: momentOptions = selectedSchoolLapse?.lapses || [];

    $: extraSearchParams = {
        ...(filters.status ? { status: filters.status } : {}),
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
            status: filters.status || "",
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

    function openPlan() {
        rejectMode = false;
        rejectNote = "";
        rejectingPlanId = null;
        showModal = true;
    }

    function rejectPlanStart() {
        rejectMode = true;
        rejectNote = "";
    }

    function approvePlan(id) {
        router.post(
            `/dashboard/planes-evaluacion/${id}/aprobar`,
            {},
            {
                preserveScroll: true,
                onSuccess: () => {
                    displayAlert({
                        type: "success",
                        message: "Plan aprobado correctamente",
                    });
                    showModal = false;
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
        router.post(
            `/dashboard/planes-evaluacion/${id}/rechazar`,
            { admin_note: rejectNote },
            {
                preserveScroll: true,
                onSuccess: () => {
                    displayAlert({
                        type: "success",
                        message: "Plan rechazado correctamente",
                    });
                    showModal = false;
                    rejectMode = false;
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
            value={filters.status || ""}
            on:change={(e) => applyFilter("status", e.target.value)}
        >
            <option value="">Todos</option>
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
            <th>Total %</th>
            <th>Estado</th>
            <th>Fecha</th>
        </tr>
    </thead>
    <tbody slot="tbody">
        {#each data.plans as plan, i}
            <SelectableRow
                rowData={plan}
                idKey="id"
                {selectedRow}
                activeClass="bg-yellow bg-opacity-10 brightness-110"
                on:select={(e) => {
                    selectedRow = e.detail;
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
                <td>{plan.items_total}%</td>
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
                            class="px-10 py-2 text-sm bg-red text-white rounded-md"
                        >
                            Rechazar
                        </button>
                        <button
                            on:click={() => approvePlan(plan.id)}
                            class="px-10 py-2 text-sm bg-color1 text-white rounded-md"
                        >
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
        {/if}
    {/if}
</Modal>
