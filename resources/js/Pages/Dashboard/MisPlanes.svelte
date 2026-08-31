<script>
    import { useForm } from "@inertiajs/svelte";
    import { router } from "@inertiajs/svelte";
    import { onMount, onDestroy } from "svelte";
    import Input from "../../components/Input.svelte";
    import Modal from "../../components/Modal.svelte";
    import Table from "../../components/Table.svelte";
    import Alert from "../../components/Alert.svelte";
    import PlanUnitsView from "../../components/PlanUnitsView.svelte";
    import { displayAlert } from "../../stores/alertStore";
    import SelectableRow from "../../components/SelectableRow.svelte";

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

    const defaultLapseId = activeSchoolLapse?.id || "";

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

    function initialMomentId(schoolLapse) {
        if (!schoolLapse?.lapses?.length) return "";
        const today = new Date().toISOString().slice(0, 10);
        const current =
            schoolLapse.lapses.find(
                (l) => today >= (l.start || "") && today <= (l.end || ""),
            ) || schoolLapse.lapses[schoolLapse.lapses.length - 1];
        return current?.id || "";
    }

    function createEmptyTopic() {
        return {
            id: `topic_${Date.now()}_${Math.random().toString(36).slice(2, 8)}`,
            name: "",
            assessment_type: "",
            percentage: "",
            points: "",
            scheduled_date: "",
            description: "",
        };
    }

    function createEmptyUnit(unitNumber = 1) {
        return {
            id: `unit_${Date.now()}_${Math.random().toString(36).slice(2, 8)}`,
            unit_number: unitNumber,
            name: "",
            topics: [createEmptyTopic()],
        };
    }

    // Total points base (escala de la nota). Usado para convertir % <-> puntos.
    const TOTAL_POINTS = 20;

    function percentageToPoints(percentage) {
        const p = parseFloat(percentage);
        if (Number.isNaN(p)) return "";
        return Math.round((p / 100) * TOTAL_POINTS * 100) / 100;
    }

    function pointsToPercentage(points) {
        const pts = parseFloat(points);
        if (Number.isNaN(pts)) return "";
        return Math.round((pts / TOTAL_POINTS) * 100 * 100) / 100;
    }

    function updatePercentage(unitIndex, topicIndex, raw) {
        const value = raw === "" ? "" : raw;
        $form.units[unitIndex].topics[topicIndex].percentage = value;

        const pts = percentageToPoints(value);
        $form.units[unitIndex].topics[topicIndex].points =
            pts === "" ? "" : pts;
    }

    function updatePoints(unitIndex, topicIndex, raw) {
        const value = raw === "" ? "" : raw;
        $form.units[unitIndex].topics[topicIndex].points = value;

        const perc = pointsToPercentage(value);
        $form.units[unitIndex].topics[topicIndex].percentage =
            perc === "" ? "" : perc;
    }

    let form = useForm({
        name: "",
        description: "",
        matter_id: "",
        school_lapse_id: defaultLapseId,
        lapse_id: initialMomentId(activeSchoolLapse),
        course_id: "",
        section_id: [],
        units: [createEmptyUnit(1)],
    });

    $: selectedSchoolLapse = data.school_lapses?.find(
        (l) => String(l.id) === String($form.school_lapse_id),
    );
    $: momentOptions = selectedSchoolLapse?.lapses || [];
    $: if (submitStatus === "Crear") {
        const autoName = getAutoPlanName();
        if (autoName && $form.name !== autoName) {
            $form.name = autoName;
        }
    }

    let allowedWeekdays = null;
    let allowedFetching = false;
    let allowedTimer = null;
    const ALLOWED_DAYS_URL = "/dashboard/mis-planes/allowed-days";

    function normalizeSectionIdsForAllowed() {
        const sel = normalizeSectionSelection($form.section_id);
        if (sel.includes("all")) {
            return (data.all_section_ids?.length
                ? data.all_section_ids
                : data.sections?.map((s) => s.id) || []
            ).map(Number);
        }
        return sel.map(Number);
    }

    $: allowedTrigger = [
        showFormModal,
        submitStatus,
        $form.school_lapse_id,
        $form.course_id,
        $form.matter_id,
        JSON.stringify(normalizeSectionSelection($form.section_id)),
    ].join("§");

    $: if (showFormModal && allowedTrigger) scheduleAllowedFetch();

    function scheduleAllowedFetch() {
        if (allowedTimer) clearTimeout(allowedTimer);
        allowedTimer = setTimeout(fetchAllowedDays, 250);
    }

    async function fetchAllowedDays() {
        if (
            !$form.school_lapse_id ||
            !$form.course_id ||
            !$form.matter_id
        ) {
            allowedWeekdays = null;
            return;
        }
        const sectionIds = normalizeSectionIdsForAllowed();
        if (!sectionIds.length) {
            allowedWeekdays = null;
            return;
        }
        allowedFetching = true;
        const qs = new URLSearchParams();
        qs.set("school_lapse_id", $form.school_lapse_id);
        qs.set("course_id", $form.course_id);
        qs.set("matter_id", $form.matter_id);
        sectionIds.forEach((id) => qs.append("section_ids[]", id));
        try {
            const res = await fetch(`${ALLOWED_DAYS_URL}?${qs.toString()}`);
            const json = await res.json();
            allowedWeekdays = json && json.restrict ? json.allowedWeekdays : null;
        } catch (e) {
            allowedWeekdays = null;
        } finally {
            allowedFetching = false;
        }
    }

    onDestroy(() => {
        if (allowedTimer) clearTimeout(allowedTimer);
        window.removeEventListener("click", closeTooltipOnOutsideClick);
    });

    const DAY_NAMES = [
        "",
        "lunes",
        "martes",
        "miércoles",
        "jueves",
        "viernes",
        "sábado",
        "domingo",
    ];

    function describeAllowedDays(days) {
        const names = (days || [])
            .map((d) => DAY_NAMES[Number(d)] || "")
            .filter(Boolean);
        if (!names.length) return "";
        if (names.length === 1) return names[0];
        return `${names.slice(0, -1).join(", ")} y ${names[names.length - 1]}`;
    }

    function allowedSectionsPhrase() {
        const sel = normalizeSectionSelection($form.section_id);
        if (!sel.length || sel.includes("all")) {
            return "en todas las secciones";
        }
        return "en esta sección";
    }

    let openTooltip = null;

    onMount(() => {
        window.addEventListener("click", closeTooltipOnOutsideClick);
    });

    function closeTooltipOnOutsideClick() {
        openTooltip = null;
    }

    function toggleTooltip(key) {
        openTooltip = openTooltip === key ? null : key;
    }

    let lastShowPickerAt = 0;

    function openCalendar(el) {
        const now = Date.now();
        if (now - lastShowPickerAt < 300) return;
        lastShowPickerAt = now;
        try {
            el.showPicker && el.showPicker();
        } catch (_) {}
    }

    function getSchoolLapseYearRange(schoolLapse) {
        if (!schoolLapse?.start || !schoolLapse?.end) return "";

        const startYear = String(
            new Date(schoolLapse.start).getFullYear(),
        ).slice(-2);
        const endYear = String(new Date(schoolLapse.end).getFullYear()).slice(
            -2,
        );

        if (!startYear || !endYear) return "";
        return `${startYear}-${endYear}`;
    }

    function normalizeSectionSelection(value) {
        if (Array.isArray(value)) return value.map(String);
        if (value === "" || value === null || value === undefined) return [];
        return [String(value)];
    }

    function isAllSectionsSelected() {
        return normalizeSectionSelection($form.section_id).includes("all");
    }

    function toggleAllSections(checked) {
        if (checked) {
            $form.section_id = ["all"];
            return;
        }

        $form.section_id = [];
    }

    function toggleSection(sectionId) {
        const selected = normalizeSectionSelection($form.section_id);
        const value = String(sectionId);

        if (selected.includes("all")) {
            $form.section_id = [value];
            return;
        }

        const exists = selected.includes(value);
        const next = exists
            ? selected.filter((id) => id !== value)
            : [...selected, value];

        $form.section_id = next;
    }

    function getSelectedSectionsLabel() {
        const selected = normalizeSectionSelection($form.section_id);

        if (selected.includes("all") || selected.includes("ALL")) {
            return "Todas las secciones";
        }

        const names = data.sections
            ?.filter((section) => selected.includes(String(section.id)))
            .map((section) => section.name);

        return names?.length ? names.join(", ") : "";
    }

    function getAutoPlanName() {
        const matter = data.matters?.find(
            (item) => String(item.id) === String($form.matter_id),
        );
        const course = data.courses?.find(
            (item) => String(item.id) === String($form.course_id),
        );
        const moment = momentOptions?.find(
            (item) => String(item.id) === String($form.lapse_id),
        );
        const selectedSectionsLabel = getSelectedSectionsLabel();

        return [
            matter?.name,
            getSchoolLapseYearRange(selectedSchoolLapse),
            course?.name,
            moment?.label,
            selectedSectionsLabel,
        ]
            .filter((value) => value && String(value).trim())
            .join(" ");
    }

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

    function addUnit() {
        const nextIndex = $form.units.length;
        const newUnit = createEmptyUnit(nextIndex + 1);
        $form.units = [...$form.units, newUnit];

        setTimeout(() => {
            const input = document.getElementById(`unit-name-${nextIndex}`);
            if (input) input.focus();
        }, 0);
    }

    function removeUnit(unitIndex) {
        const units = [...$form.units];
        units.splice(unitIndex, 1);
        $form.units = units.length ? units : [createEmptyUnit(1)];
    }

    function addTopic(unitIndex) {
        const units = [...$form.units];
        const targetUnit = units[unitIndex];
        const nextTopicIndex = targetUnit.topics.length;
        targetUnit.topics = [...targetUnit.topics, createEmptyTopic()];
        $form.units = units;

        setTimeout(() => {
            const input = document.getElementById(
                `topic-name-${unitIndex}-${nextTopicIndex}`,
            );
            if (input) input.focus();
        }, 0);
    }

    function removeTopic(unitIndex, topicIndex) {
        const units = [...$form.units];
        const targetUnit = units[unitIndex];
        const topics = [...targetUnit.topics];
        topics.splice(topicIndex, 1);
        targetUnit.topics = topics.length ? topics : [createEmptyTopic()];
        $form.units = units;
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
                        displayAlert({
                            type: "error",
                            message: errors.message,
                        });
                    }
                },
                onSuccess: () => {
                    $form.reset();
                    $form.units = [createEmptyUnit(1)];
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
                        displayAlert({
                            type: "error",
                            message: errors.message,
                        });
                    }
                },
                onSuccess: () => {
                    $form.reset();
                    $form.units = [createEmptyUnit(1)];
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
        $form.section_id = Array.isArray(plan.section_id)
            ? plan.section_id
            : plan.section_id
              ? [plan.section_id]
              : [];
        $form.units =
            Array.isArray(plan.units) && plan.units.length
                ? plan.units.map((unit, unitIndex) => ({
                      id: unit.id || `unit_${Date.now()}_${unitIndex}`,
                      unit_number: unit.unit_number ?? unitIndex + 1,
                      name: unit.name || "",
                      topics:
                          Array.isArray(unit.topics) && unit.topics.length
                              ? unit.topics.map((topic, topicIndex) => ({
                                    id:
                                        topic.id ||
                                        `topic_${Date.now()}_${unitIndex}_${topicIndex}`,
                                    name: topic.name || "",
                                    assessment_type:
                                        topic.assessment_type || "",
                                    percentage: topic.percentage ?? "",
                                    points: topic.points ?? "",
                                    scheduled_date: topic.scheduled_date || "",
                                    description: topic.description || "",
                                }))
                              : [createEmptyTopic()],
                  }))
                : [createEmptyUnit(1)];
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
            $form.reset();
            $form.units = [createEmptyUnit(1)];
            submitStatus = "Crear";
            editingPlanId = null;
            showFormModal = true;
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

                <td>{plan.items_total}%</td>
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

<Modal bind:showModal={showFormModal} classes={"w-fit"}>
    <form
        id="pl-form"
        on:submit={handleSubmit}
        action=""
        class="max-w-[1200px] pt-2 px-5"
    >
        <h3 class="text-lg font-bold text-color1 mb-2">
            {submitStatus === "Crear"
                ? "Nuevo plan de evaluación"
                : "Editar plan de evaluación"}
        </h3>

        <div class="  gap-x-12">
            <div class=" grid grid-cols-12 gap-x-6 w-full">
                <Input
                    type="select"
                    label={"Materia"}
                    bind:value={$form.matter_id}
                    error={$form.errors?.matter_id}
                    required={true}
                    classes={"col-span-3"}
                >
                    <option value="">Seleccione...</option>
                    {#each data.matters as matter}
                        <option value={matter.id}>{matter.name}</option>
                    {/each}
                </Input>

                <Input
                    type="select"
                    label={"Momento escolar"}
                    bind:value={$form.lapse_id}
                    error={$form.errors?.lapse_id}
                    required={true}
                    classes={"col-span-2"}
                >
                    {#if momentOptions.length}
                        {#each momentOptions as moment}
                            <option value={moment.id}>{moment.label}</option>
                        {/each}
                    {:else}
                        <option value="">Sin momentos</option>
                    {/if}
                </Input>

                <Input
                    type="select"
                    label={"Curso"}
                    bind:value={$form.course_id}
                    error={$form.errors?.course_id}
                    required={true}
                    classes={"col-span-2"}
                >
                    {#each data.courses as course}
                        <option value={course.id}>{course.name}</option>
                    {/each}
                </Input>

                <div class="col-span-2">
                    <label
                        class="form__label w-full text-xs md:text-sm font-semibold text-gray-700"
                    >
                        Sección *
                    </label>
                    <div
                        class="form__field w-full rounded-md border border-gray-300 px-3 py-2 text-sm min-h-[42px] bg-white"
                    >
                        <label class="flex items-center gap-2 mb-2 text-sm">
                            <input
                                type="checkbox"
                                checked={isAllSectionsSelected()}
                                on:change={(event) =>
                                    toggleAllSections(
                                        event.currentTarget.checked,
                                    )}
                            />
                            <span>Todas las secciones</span>
                        </label>

                        <div class="space-y-1 max-h-[180px] overflow-y-auto">
                            {#each data.sections as section}
                                <label class="flex items-center gap-2 text-sm">
                                    <input
                                        type="checkbox"
                                        value={String(section.id)}
                                        checked={normalizeSectionSelection(
                                            $form.section_id,
                                        ).includes(String(section.id))}
                                        on:change={() =>
                                            toggleSection(section.id)}
                                    />
                                    <span>{section.name}</span>
                                </label>
                            {/each}
                        </div>
                    </div>
                    {#if $form.errors?.section_id}
                        <div class="text-black font-semibold bg-red pt-1 px-2">
                            <span>{$form.errors.section_id}</span>
                        </div>
                    {/if}
                </div>

                <Input
                    label="Descripción (opcional)"
                    type="textarea"
                    bind:value={$form.description}
                    classes={"col-span-3"}
                    error={$form.errors.description}
                />
            </div>

            <div class="mt-5 col-span-7">
                <div class="flex items-center justify-between mb-3 gap-2">
                    <p class="text-xs md:text-sm font-semibold text-gray-700">
                        Unidades y temas
                    </p>
                    <button
                        type="button"
                        on:click={addUnit}
                        class="text-xs px-3 py-1.5 bg-color1 text-white rounded-md"
                    >
                        + Agregar unidad
                    </button>
                </div>
                {#if $form.errors.units}
                    <p class="text-red text-xs font-semibold mb-2">
                        {$form.errors.units}
                    </p>
                {/if}
                <div class="space-y-4">
                    {#each $form.units as unit, unitIndex}
                        <div class="rounded-lg shadow-lg bg-gray-50 p-3 md:p-5">
                            <div class="flex items-center gap-2 mb-3">
                                <span
                                    class="text-xs font-semibold text-gray-500"
                                >
                                    Unidad {unitIndex + 1}
                                </span>
                                <input
                                    type="text"
                                    placeholder="Nombre de la unidad"
                                    class="rounded-md border border-gray-300 px-3 py-2 text-sm flex-1"
                                    bind:value={$form.units[unitIndex].name}
                                    id={`unit-name-${unitIndex}`}
                                />
                                {#if $form.units.length > 1}
                                    <button
                                        type="button"
                                        on:click={() => removeUnit(unitIndex)}
                                        class=" hover:text-red/70 text-xs font-semibold"
                                    >
                                        Quitar
                                    </button>
                                {/if}
                            </div>

                            <div class="space-y-3">
                                {#each unit.topics as topic, topicIndex}
                                    <div
                                        class="grid grid-cols-[5px_1.2fr_1.2fr_1fr_70px_63px_140px_32px] gap-2 items-start"
                                    >
                                        <span
                                            class="text-xs font-semibold text-gray-500 pt-2"
                                        >
                                            {topicIndex + 1}.
                                        </span>
                                        <textarea
                                            type="text"
                                            placeholder="Tema. Ej: Sistemas de Ecuaciones"
                                            class="rounded-md border border-gray-300 px-3 py-2 text-sm"
                                            bind:value={
                                                $form.units[unitIndex].topics[
                                                    topicIndex
                                                ].name
                                            }
                                            id={`topic-name-${unitIndex}-${topicIndex}`}
                                        />
                                        <input
                                            type="text"
                                            placeholder="Tipo de prueba"
                                            class="rounded-md border border-gray-300 px-3 py-2 text-sm"
                                            bind:value={
                                                $form.units[unitIndex].topics[
                                                    topicIndex
                                                ].assessment_type
                                            }
                                        />
                                        <textarea
                                            type="text"
                                            placeholder="Descripción (opcional)"
                                            class="rounded-md border border-gray-300 px-3 py-2 text-sm"
                                            bind:value={
                                                $form.units[unitIndex].topics[
                                                    topicIndex
                                                ].description
                                            }
                                        />
                                        <div
                                            class="flex w-[70px] mr-2 items-center relative"
                                        >
                                            <input
                                                type="number"
                                                placeholder="%"
                                                min="0.01"
                                                max="100"
                                                step="0.01"
                                                class="rounded-md border w-[70px] border-gray-300 px-3 py-2 text-sm"
                                                bind:value={
                                                    $form.units[unitIndex]
                                                        .topics[topicIndex]
                                                        .percentage
                                                }
                                                on:input={(e) =>
                                                    updatePercentage(
                                                        unitIndex,
                                                        topicIndex,
                                                        e.currentTarget.value,
                                                    )}
                                            />
                                            {#if $form.units[unitIndex].topics[topicIndex].percentage > 0}
                                                <b
                                                    class="text-xs absolute top-2.5 right-0.5 p-1 px-2 text-gray-600 bg-white z-10"
                                                    >%</b
                                                >
                                            {/if}
                                        </div>
                                        <div
                                            class="flex w-[63px] items-center relative"
                                        >
                                            {#if $form.units[unitIndex].topics[topicIndex].points > 0}
                                                <b
                                                    class="text-xs absolute top-2.5 right-0.5 p-1 px-1 text-gray-600 bg-white z-10"
                                                    >Pts</b
                                                >
                                            {/if}

                                            <input
                                                type="number"
                                                placeholder="Pts"
                                                min="0"
                                                step="0.01"
                                                class="rounded-md border w-[63px] border-gray-300 px-3 py-2 text-sm"
                                                bind:value={
                                                    $form.units[unitIndex]
                                                        .topics[topicIndex]
                                                        .points
                                                }
                                                on:input={(e) =>
                                                    updatePoints(
                                                        unitIndex,
                                                        topicIndex,
                                                        e.currentTarget.value,
                                                    )}
                                            />
                                        </div>
                                        <!-- svelte-ignore a11y-click-events-have-key-events -->
                                        <!-- svelte-ignore a11y-no-static-element-interactions -->
                                        <div class="relative" on:click|stopPropagation>
                                            <input
                                                type="date"
                                                class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm"
                                                bind:value={
                                                    $form.units[unitIndex].topics[
                                                        topicIndex
                                                    ].scheduled_date
                                                }
                                                on:click={(e) => {
                                                    // toggleTooltip(
                                                    //     `${unitIndex}-${topicIndex}`,
                                                    // );
                                                    openCalendar(
                                                        e.currentTarget,
                                                    );
                                                }}
                                                on:focus={(e) => {
                                                    openCalendar(
                                                        e.currentTarget,
                                                    );
                                                    toggleTooltip(
                                                        `${unitIndex}-${topicIndex}`,
                                                    );
                                                }}
                                                on:blur={() => {
                                                    toggleTooltip(null);
                                                }}
                                                title="Clic para abrir el calendario"
                                            />
                                            {#if openTooltip === `${unitIndex}-${topicIndex}` && allowedWeekdays?.length}
                                                <div
                                                    class="right-36 absolute bg-white bottom-full z-30 mb-2 w-60     rounded-lg border border-amber-300 bg-amber-50 p-2.5 text-xs font-medium text-amber-700 shadow-lg"
                                                >
                                                    Para esta materia
                                                    {allowedSectionsPhrase()}
                                                    das clases los días
                                                    <b>
                                                    {describeAllowedDays(
                                                        allowedWeekdays,
                                                    )}.</b>
                                                </div>
                                            {/if}
                                        </div>
                                        <button
                                            type="button"
                                            on:click={() =>
                                                removeTopic(
                                                    unitIndex,
                                                    topicIndex,
                                                )}
                                            class=" hover:text-red/70 pt-2"
                                            title="Quitar tema"
                                        >
                                            <iconify-icon
                                                icon="mdi:close-circle-outline"
                                                width="22"
                                                height="22"
                                            ></iconify-icon>
                                        </button>
                                    </div>
                                {/each}
                            </div>

                            <button
                                type="button"
                                on:click={() => addTopic(unitIndex)}
                                class="mt-3 text-xs px-3 py-1.5 bg-gray-200 text-gray-700 rounded-md"
                            >
                                + Agregar tema
                            </button>
                        </div>
                    {/each}
                </div>
            </div>
        </div>
    </form>
    <button
        form="pl-form"
        slot="btn_footer"
        type="submit"
        class="animated-button min-w-[200px] flex gap-2 hover:bg-[#c5e5e4]"
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
            <span class=""
                >{submitStatus === "Crear" ? "Crear" : "Guardar"}</span
            >
        {/if}
    </button>
</Modal>
