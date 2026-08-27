<script>
    import { useForm } from "@inertiajs/svelte";
    import { router } from "@inertiajs/svelte";
    import Input from "../../components/Input.svelte";
    import Modal from "../../components/Modal.svelte";
    import Table from "../../components/Table.svelte";
    import Alert from "../../components/Alert.svelte";
    import { displayAlert } from "../../stores/alertStore";
    import SelectableRow from "../../components/SelectableRow.svelte";

    export let data = [];

    const activeSchoolLapse =
        data.school_lapses?.find((l) => l.is_active) ||
        data.school_lapses?.[0];

    const defaultLapseId = activeSchoolLapse?.id || "";

    function initialMomentId(schoolLapse) {
        if (!schoolLapse?.lapses?.length) return "";
        const today = new Date().toISOString().slice(0, 10);
        const current =
            schoolLapse.lapses.find(
                (l) => today >= (l.start || "") && today <= (l.end || ""),
            ) || schoolLapse.lapses[schoolLapse.lapses.length - 1];
        return current?.id || "";
    }

    const emptyItem = { name: "", percentage: "", date: "" };

    let form = useForm({
        name: "",
        description: "",
        matter_id: "",
        school_lapse_id: defaultLapseId,
        lapse_id: initialMomentId(activeSchoolLapse),
        course_id: "",
        section_id: "",
        items: [{ ...emptyItem }],
    });

    $: selectedSchoolLapse = data.school_lapses?.find(
        (l) => String(l.id) === String($form.school_lapse_id),
    );
    $: momentOptions = selectedSchoolLapse?.lapses || [];

    let showModal = false;
    let showFormModal = false;
    let selectedRow = { status: false, data: null };
    let editingPlanId = null;
    let submitStatus = "Crear";

    const statusBadges = {
        pending: "bg-yellow text-gray-800",
        approved: "bg-green-100 text-green-700",
        rejected: "bg-red text-white",
    };

    document.addEventListener("keydown", ({ key }) => {
        if (key === "Escape") {
            selectedRow = { status: false, data: null };
            editingPlanId = null;
            showModal = false;
            showFormModal = false;
        }
    });

    function addItem() {
        $form.items = [...$form.items, { ...emptyItem }];
    }

    function removeItem(index) {
        const items = [...$form.items];
        items.splice(index, 1);
        $form.items = items.length ? items : [{ ...emptyItem }];
    }

    function canEdit(plan) {
        return plan.status === "pending" || plan.status === "rejected";
    }

    function handleSubmit(event) {
        event.preventDefault();
        $form.clearErrors();

        if (submitStatus === "Crear") {
            $form.post("/dashboard/mis-planes", {
                onError: (errors) => {
                    if (errors.items) {
                        displayAlert({ type: "error", message: errors.items });
                    } else if (errors.message) {
                        displayAlert({ type: "error", message: errors.message });
                    }
                },
                onSuccess: () => {
                    $form.reset();
                    $form.items = [{ ...emptyItem }];
                    displayAlert({
                        type: "success",
                        message: "Plan de evaluación creado correctamente",
                    });
                    showFormModal = false;
                    editingPlanId = null;
                },
            });
        } else {
            $form.put(`/dashboard/mis-planes/${editingPlanId}`, {
                onError: (errors) => {
                    if (errors.items) {
                        displayAlert({ type: "error", message: errors.items });
                    } else if (errors.message) {
                        displayAlert({ type: "error", message: errors.message });
                    }
                },
                onSuccess: () => {
                    $form.reset();
                    $form.items = [{ ...emptyItem }];
                    displayAlert({
                        type: "success",
                        message: "Plan actualizado correctamente",
                    });
                    showFormModal = false;
                    editingPlanId = null;
                    submitStatus = "Crear";
                    selectedRow = { status: false, data: null };
                },
            });
        }
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
        editingPlanId = plan.id;
        submitStatus = "Editar";
        $form.name = plan.name;
        $form.description = plan.description || "";
        $form.matter_id = plan.matter_id;
        $form.school_lapse_id = plan.school_lapse_id;
        $form.lapse_id = plan.lapse_id || "";
        $form.course_id = plan.course_id || "";
        $form.section_id = plan.section_id || "";
        $form.items = plan.items.length
            ? plan.items.map((item) => ({
                  name: item.name,
                  percentage: item.percentage,
                  date: item.date || "",
              }))
            : [{ ...emptyItem }];
        showFormModal = true;
    }

    function handleDelete() {
        if (!selectedRow.data) return;
        if (!canEdit(selectedRow.data)) {
            displayAlert({
                type: "error",
                message: "Un plan aprobado no puede eliminarse.",
            });
            return;
        }
        if (!confirm(`¿Está seguro de eliminar el plan "${selectedRow.data.name}"?`))
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
    }</script>

<svelte:head>
    <title>Mis Planes de Evaluación</title>
</svelte:head>

<Alert />

<div class="flex justify-between items-center mb-3 flex-wrap gap-2">
    <h2 class="text-2xl font-bold text-color1">Mis Planes de Evaluación</h2>
    <button
        class="animated-button w-fitcontent"
        on:click={(e) => {
            e.preventDefault();
            $form.reset();
            $form.items = [{ ...emptyItem }];
            submitStatus = "Crear";
            editingPlanId = null;
            showFormModal = true;
        }}
    >
        <span class="text">Nuevo plan</span>
        <span class="circle"></span>
    </button>
</div>

<Table
    {selectedRow}
    allowFilters={false}
    filtersOptions={{}}
    serverSideData={{ filters: {} }}
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
            <th>Materia</th>
            <th>Período</th>
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
                <td>
                    <p class="font-semibold">{plan.name}</p>
                    {#if plan.description}
                        <p class="text-xs text-gray-500 max-w-[200px] truncate">
                            {plan.description}
                        </p>
                    {/if}
                </td>
                <td>{plan.matter_name}</td>
                <td>{plan.school_lapse_label}</td>
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
        <div class="px-5 py-2 min-w-[560px]">
            <h3 class="text-xl font-bold text-color1 mb-1">{plan.name}</h3>
            <p class="text-sm text-gray-500 mb-3">
                {plan.matter_name} · {plan.school_lapse_label}
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
            {#if plan.status === "approved"}
                <div
                    class="mt-4 bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-md text-sm"
                >
                    Plan aprobado. Ya no puede editarlo.
                </div>
            {/if}
        </div>
    {/if}
</Modal>

<Modal bind:showModal={showFormModal} classes={"w-fit"}>
    <form
        id="pl-form"
        on:submit={handleSubmit}
        action=""
        class="max-w-[760px] pt-2 px-5"
    >
            <h3 class="text-lg font-bold text-color1 mb-2">
                {submitStatus === "Crear" ? "Nuevo plan de evaluación" : "Editar plan de evaluación"}
            </h3>

            <div class="grid grid-cols-2 gap-x-6">
                <Input
                    label="Nombre del plan"
                    type="text"
                    required={true}
                    bind:value={$form.name}
                    error={$form.errors.name}
                />
                <div class="mt-3 md:mt-5">
                    <label
                        class="form__label text-xs md:text-sm font-semibold text-gray-700"
                    >
                        Materia *
                    </label>
                    <select
                        class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm"
                        bind:value={$form.matter_id}
                    >
                        <option value="">Seleccione...</option>
                        {#each data.matters as matter}
                            <option value={matter.id}>{matter.name}</option>
                        {/each}
                    </select>
                    {#if $form.errors.matter_id}
                        <p class="text-red text-xs font-semibold mt-1">
                            {$form.errors.matter_id}
                        </p>
                    {/if}
                </div>
                <div class="mt-3 md:mt-5">
                    <label
                        class="form__label text-xs md:text-sm font-semibold text-gray-700"
                    >
                        Período escolar *
                    </label>
                    <select
                        class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm"
                        bind:value={$form.school_lapse_id}
                        on:change={() => {
                            const sl = data.school_lapses?.find(
                                (l) => String(l.id) === String($form.school_lapse_id),
                            );
                            $form.lapse_id = sl?.lapses?.[0]?.id || "";
                        }}
                    >
                        {#each data.school_lapses as lapse}
                            <option value={lapse.id}>{lapse.label}</option>
                        {/each}
                    </select>
                    {#if $form.errors.school_lapse_id}
                        <p class="text-red text-xs font-semibold mt-1">
                            {$form.errors.school_lapse_id}
                        </p>
                    {/if}
                </div>
                <div class="mt-3 md:mt-5">
                    <label
                        class="form__label text-xs md:text-sm font-semibold text-gray-700"
                    >
                        Momento escolar *
                    </label>
                    <select
                        class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm"
                        bind:value={$form.lapse_id}
                    >
                        {#if momentOptions.length}
                            {#each momentOptions as moment}
                                <option value={moment.id}>{moment.label}</option>
                            {/each}
                        {:else}
                            <option value="">Sin momentos</option>
                        {/if}
                    </select>
                    {#if $form.errors.lapse_id}
                        <p class="text-red text-xs font-semibold mt-1">
                            {$form.errors.lapse_id}
                        </p>
                    {/if}
                </div>
                <div class="mt-3 md:mt-5">
                    <label
                        class="form__label text-xs md:text-sm font-semibold text-gray-700"
                    >
                        Curso *
                    </label>
                    <select
                        class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm"
                        bind:value={$form.course_id}
                    >
                        <option value="">Seleccione...</option>
                        {#each data.courses as course}
                            <option value={course.id}>{course.name}</option>
                        {/each}
                    </select>
                    {#if $form.errors.course_id}
                        <p class="text-red text-xs font-semibold mt-1">
                            {$form.errors.course_id}
                        </p>
                    {/if}
                </div>
                <div class="mt-3 md:mt-5">
                    <label
                        class="form__label text-xs md:text-sm font-semibold text-gray-700"
                    >
                        Sección *
                    </label>
                    <select
                        class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm"
                        bind:value={$form.section_id}
                    >
                        <option value="">Seleccione...</option>
                        {#each data.sections as section}
                            <option value={section.id}>{section.name}</option>
                        {/each}
                    </select>
                    {#if $form.errors.section_id}
                        <p class="text-red text-xs font-semibold mt-1">
                            {$form.errors.section_id}
                        </p>
                    {/if}
                </div>
            </div>

            <Input
                label="Descripción (opcional)"
                type="textarea"
                bind:value={$form.description}
                error={$form.errors.description}
            />

            <div class="mt-5">
                <div class="flex items-center justify-between mb-2">
                    <p class="text-xs md:text-sm font-semibold text-gray-700">
                        Evaluaciones / porcentajes
                    </p>
                    <button
                        type="button"
                        on:click={addItem}
                        class="text-xs px-3 py-1.5 bg-color1 text-white rounded-md"
                    >
                        + Agregar evaluación
                    </button>
                </div>
                {#if $form.errors.items}
                    <p class="text-red text-xs font-semibold mb-2">
                        {$form.errors.items}
                    </p>
                {/if}
                <div class="space-y-2">
                    {#each $form.items as item, i}
                        <div
                            class="grid grid-cols-[1fr_120px_150px_auto] gap-2 items-center"
                        >
                            <input
                                type="text"
                                placeholder="Nombre (ej: Parcial 1)"
                                class="rounded-md border border-gray-300 px-3 py-2 text-sm"
                                bind:value={$form.items[i].name}
                            />
                            <input
                                type="number"
                                placeholder="%"
                                min="0.01"
                                max="100"
                                step="0.01"
                                class="rounded-md border border-gray-300 px-3 py-2 text-sm"
                                bind:value={$form.items[i].percentage}
                            />
                            <input
                                type="date"
                                class="rounded-md border border-gray-300 px-3 py-2 text-sm"
                                bind:value={$form.items[i].date}
                            />
                            <button
                                type="button"
                                on:click={() => removeItem(i)}
                                class="text-red hover:text-red/70"
                                title="Quitar"
                            >
                                <iconify-icon
                                    icon="mdi:close-circle-outline"
                                    width="24"
                                    height="24"
                                ></iconify-icon>
                            </button>
                        </div>
                    {/each}
                </div>
            </div>
        </form>
        <button
            form="pl-form"
            slot="btn_footer"
            type="submit"
            class="animated-button min-w-[200px] flex gap-2"
            disabled={$form.processing}
        >
            {#if $form.processing}
                Cargando...
            {:else}
                <iconify-icon
                    icon="material-symbols:save-sharp"
                    width="24"
                    height="24"
                />
                <span>{submitStatus === "Crear" ? "Crear" : "Guardar"}</span>
            {/if}
        </button>
</Modal>
