<script>
    import { router } from "@inertiajs/svelte";
    import Modal from "../../components/Modal.svelte";
    import Table from "../../components/Table.svelte";
    import Alert from "../../components/Alert.svelte";
    import { displayAlert } from "../../stores/alertStore";
    import SelectableRow from "../../components/SelectableRow.svelte";

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

    function applyFilter(key, value) {
        const params = {
            status: filters.status || "",
            matter_id: filters.matter_id || "",
            teacher_id: filters.teacher_id || "",
            [key]: value,
        };
        Object.keys(params).forEach((k) => {
            if (params[k] === null || params[k] === undefined || params[k] === "") {
                delete params[k];
            }
        });
        router.get("/dashboard/planes-evaluacion", params, {
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

<div class="flex justify-between items-center mb-3 flex-wrap gap-2">
    <h2 class="text-2xl font-bold text-color1">Planes de Evaluación</h2>
</div>

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
        <label class="text-sm font-semibold text-gray-600">Materia</label>
        <select
            class="rounded-md border border-gray-300 px-3 py-2 text-sm"
            value={filters.matter_id || ""}
            on:change={(e) => applyFilter("matter_id", e.target.value)}
        >
            <option value="">Todas</option>
            {#each data.matters as matter}
                <option value={matter.id}>{matter.name}</option>
            {/each}
        </select>
    </div>
    <div class="flex items-center gap-2">
        <label class="text-sm font-semibold text-gray-600">Profesor</label>
        <select
            class="rounded-md border border-gray-300 px-3 py-2 text-sm"
            value={filters.teacher_id || ""}
            on:change={(e) => applyFilter("teacher_id", e.target.value)}
        >
            <option value="">Todos</option>
            {#each data.teachers as teacher}
                <option value={teacher.id}>{teacher.name}</option>
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
            <th>Plan</th>
            <th>Profesor</th>
            <th>Materia</th>
            <th>Período</th>
            <th>Momento</th>
            <th>Curso / Sección</th>
            <th>Items</th>
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
                <td>
                    <p class="font-semibold">{plan.name}</p>
                    {#if plan.description}
                        <p class="text-xs text-gray-500 max-w-[200px] truncate">
                            {plan.description}
                        </p>
                    {/if}
                </td>
                <td>{plan.teacher_name}</td>
                <td>{plan.matter_name}</td>
                <td>{plan.school_lapse_label}</td>
                <td>{plan.lapse_label || "—"}</td>
                <td>
                    {plan.course_name || "—"}
                    {#if plan.section_name}· {plan.section_name}{/if}
                </td>
                <td>{plan.items.length}</td>
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
        <div class="px-5 py-2 min-w-[560px]">
            <h3 class="text-xl font-bold text-color1 mb-1">{plan.name}</h3>
            <p class="text-sm text-gray-500 mb-3">
                {plan.teacher_name} · {plan.matter_name} ·
                {plan.school_lapse_label}
                {#if plan.lapse_label} · {plan.lapse_label}{/if}
                {#if plan.course_name}
                    · {plan.course_name}{plan.section_name ? " · Sección " + plan.section_name : ""}
                {/if}
            </p>
            {#if plan.description}
                <p class="text-sm text-gray-600 mb-3">{plan.description}</p>
            {/if}

            <table class="w-full text-sm border border-gray-200 rounded-lg overflow-hidden">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="text-left px-3 py-2">Evaluación</th>
                        <th class="text-left px-3 py-2">Porcentaje</th>
                        <th class="text-left px-3 py-2">Fecha</th>
                    </tr>
                </thead>
                <tbody>
                    {#each plan.items as item}
                        <tr class="border-t border-gray-100">
                            <td class="px-3 py-1.5">{item.name}</td>
                            <td class="px-3 py-1.5">{item.percentage}%</td>
                            <td class="px-3 py-1.5">{item.date || "—"}</td>
                        </tr>
                    {/each}
                    <tr class="border-t border-gray-200 font-semibold">
                        <td class="px-3 py-1.5">Total</td>
                        <td class="px-3 py-1.5">{plan.items_total}%</td>
                        <td></td>
                    </tr>
                </tbody>
            </table>

            {#if plan.status === "rejected" && plan.admin_note}
                <div
                    class="mt-4 bg-red/5 border border-red/20 text-red px-4 py-3 rounded-md text-sm"
                >
                    <span class="font-semibold">Motivo del rechazo: </span>
                    {plan.admin_note}
                </div>
            {/if}

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
                        <div class="flex gap-3 justify-end">
                            <button
                                on:click={() => {
                                    rejectMode = true;
                                    rejectingPlanId = plan.id;
                                }}
                                class="px-4 py-2 text-sm bg-red text-white rounded-md"
                            >
                                Rechazar
                            </button>
                            <button
                                on:click={() => approvePlan(plan.id)}
                                class="px-4 py-2 text-sm bg-green text-white rounded-md"
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
        </div>
    {/if}
</Modal>
