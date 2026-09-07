<script>
    import { router } from "@inertiajs/svelte";
    import Modal from "../../components/Modal.svelte";
    import Table from "../../components/Table.svelte";
    import Alert from "../../components/Alert.svelte";
    import PlanUnitsView from "../../components/PlanUnitsView.svelte";
    import { displayAlert } from "../../stores/alertStore";
    import SelectableRow from "../../components/SelectableRow.svelte";
    import EvaluationPlanCreateModal from "../../components/EvaluationPlanCreateModal.svelte";

    export let data = [];
    export let filters = {};

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

    function applyFilter(key, value) {
        const params = {
            school_lapse_id:
                filters.school_lapse_id || activeSchoolLapse?.id || "",
            lapse_id: filters.lapse_id || "",
            matter_id: filters.matter_id || "",
        };
        if (key === "school_lapse_id") {
            params.school_lapse_id = value;
            params.lapse_id = "";
        } else if (key === "matter_id") {
            params.matter_id = value;
        } else {
            params.lapse_id = value;
        }
        Object.keys(params).forEach((k) => {
            if (
                params[k] === null ||
                params[k] === undefined ||
                params[k] === ""
            ) {
                delete params[k];
            }
        });
        router.get("/dashboard/mis-planes", params, {
            replace: true,
        });
    }

    let showModal = false;
    let selectedRow = { status: false, data: null };
    let planModal = null;

    const statusBadges = {
        draft: "bg-gray-200 text-gray-700",
        pending: "bg-yellow text-gray-800",
        approved: "bg-green-100 text-green-700",
        rejected: "bg-red text-white",
    };

    document.addEventListener("keydown", ({ key }) => {
        if (key === "Escape") {
            selectedRow = { status: false, data: null };
            showModal = false;
        }
    });

    function canEdit(plan) {
        return (
            plan.status === "draft" ||
            plan.status === "pending" ||
            plan.status === "rejected" ||
            plan.status === "approved"
        );
    }

    function fillFormToEdit() {
        const plan = selectedRow.data;
        if (!canEdit(plan)) {
            displayAlert({
                type: "error",
                message: "Un plan aprobado no puede editarse.",
            });
            return;
        }
        if (plan.status === "approved") {
            displayAlert({
                type: "info",
                message:
                    "Este plan está aprobado. Al guardar se creará una versión pendiente que no reemplaza la versión aprobada hasta su publicación.",
            });
        }
        planModal?.openForEdit(plan);
    }

    function handleDelete() {
        if (!selectedRow.data) return;
        if (selectedRow.data.status === "approved") {
            displayAlert({
                type: "error",
                message: "Un plan aprobado no puede eliminarse.",
            });
            return;
        }
        if (
            !confirm(
                `¿Está seguro de eliminar el plan "${selectedRow.data.name}"?`,
            )
        )
            return;

        router.delete(`/dashboard/mis-planes/${selectedRow.data.id}`, {
            onSuccess: () => {
                displayAlert({
                    type: "success",
                    message: "Plan eliminado correctamente",
                });
                selectedRow = { status: false, data: null };
            },
            onError: (errors) => {
                displayAlert({
                    type: "error",
                    message: errors.message || "Error al eliminar",
                });
            },
        });
    }

    function openReadOnly() {
        showModal = true;
    }
</script>

<svelte:head>
    <title>Planes de Evaluación</title>
</svelte:head>

<Alert />

<div class="flex justify-between items-center mb-3 flex-wrap gap-2">
    <h2 class="text-2xl font-bold text-color1">Mis Planes de Evaluación</h2>
    <button
        class="animated-button w-fitcontent"
        on:click={(e) => {
            e.preventDefault();
            planModal?.open();
        }}
    >
        <span class="text">Nuevo plan</span>
        <span class="circle"></span>
    </button>
</div>

<div
    class="flex flex-wrap gap-3 mb-4 bg-white border border-gray-200 rounded-lg p-3"
>
    <div class="flex items-center gap-2">
        <label class="text-sm font-semibold text-gray-600"
            >Período escolar</label
        >
        <select
            class="rounded-md border border-gray-300 px-3 py-2 text-sm"
            value={String(
                filters.school_lapse_id || activeSchoolLapse?.id || "",
            )}
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
        <label class="text-sm font-semibold text-gray-600">Materia</label>
        <select
            class="rounded-md border border-gray-300 px-3 py-2 text-sm"
            value={String(filters.matter_id || "")}
            on:change={(e) => applyFilter("matter_id", e.target.value)}
        >
            <option value="">Todas</option>
            {#each data.matters as matter}
                <option value={String(matter.id)}>{matter.name}</option>
            {/each}
        </select>
    </div>
</div>

<Table
    {selectedRow}
    allowFilters={false}
    filtersOptions={{}}
    serverSideData={{ filters }}
    pagination={false}
    on:fillFormToEdit={fillFormToEdit}
    on:clickDeleteIcon={handleDelete}
    otherSelectOptions={[
        {
            label: "Ver plan",
            icon: "mdi:eye",
            classes: "bg-blue text-white",
            onClick: openReadOnly,
        },
    ]}
>
    <thead slot="thead" class="sticky top-0 z-50">
        <tr>
            <th>N°</th>
            <th>Plan</th>
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
                    <div>
                        <b class="text-gray-700">
                            {plan.matter_name} ·
                        </b>
                        {plan.course_name || "—"}
                        {#if plan.section_name}· {plan.section_name}{/if}

                        {#if !filters.lapse_id && plan.lapse_label}
                            · {plan.lapse_label}
                        {/if}
                    </div>
                    {#if plan.description}
                        <p class="text-xs text-gray-500 max-w-[200px] truncate">
                            {plan.description}
                        </p>
                    {/if}
                </td>

                <td>
                    {plan.items_total}%{plan.rasgos_points
                        ? ` + ${plan.rasgos_points * 5}% r.`
                        : ""}
                </td>
                <td>
                    <span
                        class="px-2 py-0.5 rounded text-xs font-bold {statusBadges[
                            plan.status
                        ] || ''}"
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
        <PlanUnitsView {plan} showTeacher={false} />
    {/if}
</Modal>

<EvaluationPlanCreateModal
    {data}
    mode="teacher"
    renderTriggerButton={false}
    bind:this={planModal}
/>
