<script>
    import { useForm } from "@inertiajs/svelte";
    import Modal from "./Modal.svelte";
    import Input from "./Input.svelte";
    import { displayAlert } from "../stores/alertStore";
    import { createEventDispatcher, onDestroy, onMount } from "svelte";

    export let data = {};
    export let isUserATeacher = false;
    export let mode = "admin"; // "admin" | "teacher"
    export let renderTriggerButton = true;
    // When editing (teacher mode) the parent passes the plan to prefill.
    export let editingPlan = null;

    const dispatch = createEventDispatcher();

    let showFormModal = false;
    let allowedWeekdays = null;
    let allowedTimer = null;
    let openTooltip = null;
    let lastShowPickerAt = 0;
    let editingPlanId = null;
    let submitStatus = "Crear"; // "Crear" | "Editar"
    let showDateErrors = false;
    const totalPoints = 20;

    const isTeacher = mode === "teacher" || isUserATeacher;
    const allowedDaysUrl = isTeacher
        ? "/dashboard/mis-planes/allowed-days"
        : "/dashboard/planes-evaluacion/allowed-days";

    function createTopic() {
        return {
            name: "",
            assessment_type: "",
            percentage: "",
            points: "",
            scheduled_date: "",
            description: "",
        };
    }

    function createUnit(number = 1) {
        return { unit_number: number, name: "", topics: [createTopic()] };
    }

    function isBlankTopic(topic) {
        const name = (topic.name || "").trim();
        const percentage = parseFloat(topic.percentage);
        const points = parseFloat(topic.points);
        return (
            !name &&
            !(percentage > 0) &&
            !(points > 0)
        );
    }

    function topicMissingDate(topic) {
        return !isBlankTopic(topic) && !topic.scheduled_date;
    }

    function missingDatesCount() {
        let count = 0;
        for (const unit of $form.units || []) {
            for (const topic of unit.topics || []) {
                if (topicMissingDate(topic)) count++;
            }
        }
        return count;
    }

    const activeLapse =
        data.school_lapses?.find((item) => item.is_active) ||
        data.school_lapses?.[0];

    // In teacher mode the materials list is already scoped to the logged-in
    // teacher (data.matters); in admin mode it's filtered by selected teacher.
    let form = useForm({
        teacher_id: "",
        description: "",
        rasgos_points: 0,
        status: "pending",
        matter_id: "",
        school_lapse_id: activeLapse?.id || "",
        lapse_id: activeLapse?.lapses?.[0]?.id || "",
        course_id: "",
        section_id: [],
        units: [createUnit()],
    });

    $: evalTotalPct =
        Math.round(
            ($form.units || []).reduce(
                (acc, u) =>
                    acc +
                    (u.topics || []).reduce(
                        (a, t) => a + (parseFloat(t.percentage) || 0),
                        0,
                    ),
                0,
            ) * 100,
        ) / 100;
    $: rasgosPts = parseInt($form.rasgos_points, 10) || 0;
    $: rasgosPct = rasgosPts * 5;
    $: planTotalPct = Math.round((evalTotalPct + rasgosPct) * 100) / 100;
    $: totalIsValid = Math.abs(planTotalPct - 100) <= 0.01;

    $: selectedLapse = data.school_lapses?.find(
        (item) => String(item.id) === String($form.school_lapse_id),
    );
    $: moments = selectedLapse?.lapses || [];
    $: selectedTeacher = data.teachers?.find(
        (teacher) => String(teacher.id) === String($form.teacher_id),
    );
    $: teacherMatterIds = (selectedTeacher?.matter_ids || []).map(String);
    $: availableMatters = isTeacher
        ? data.matters || []
        : data.matters?.filter((matter) =>
              teacherMatterIds.includes(String(matter.id)),
          ) || [];
    $: courseSections =
        data.courses?.find(
            (course) => String(course.id) === String($form.course_id),
        )?.sections || [];
    $: courseSectionsCount = courseSections.length;

    function normalizeSectionIds() {
        const selected = ($form.section_id || []).map(String);
        if (selected.includes("all")) {
            return courseSections.map((section) => section.id);
        }
        return selected.map(Number).filter(Boolean);
    }

    $: allowedTrigger = [
        showFormModal,
        $form.teacher_id,
        $form.school_lapse_id,
        $form.course_id,
        $form.matter_id,
        JSON.stringify($form.section_id || []),
    ].join("|");

    $: if (showFormModal && allowedTrigger) scheduleAllowedFetch();

    function scheduleAllowedFetch() {
        if (allowedTimer) clearTimeout(allowedTimer);
        allowedTimer = setTimeout(fetchAllowedDays, 250);
    }

    async function fetchAllowedDays() {
        allowedWeekdays = null;
        const sectionIds = normalizeSectionIds();
        if (
            !$form.school_lapse_id ||
            !$form.course_id ||
            !$form.matter_id ||
            !sectionIds.length
        ) {
            return;
        }

        const query = new URLSearchParams({
            school_lapse_id: $form.school_lapse_id,
            course_id: $form.course_id,
            matter_id: $form.matter_id,
        });
        if (!isTeacher) {
            query.set("teacher_id", $form.teacher_id);
        }
        sectionIds.forEach((id) => query.append("section_ids[]", id));

        try {
            const response = await fetch(`${allowedDaysUrl}?${query.toString()}`);
            const result = await response.json();
            allowedWeekdays = result?.restrict ? result.allowedWeekdays : null;
        } catch (_) {
            allowedWeekdays = null;
        }
    }

    const dayNames = [
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
            .map((day) => dayNames[Number(day)] || "")
            .filter(Boolean);
        if (names.length < 2) return names[0] || "";
        return `${names.slice(0, -1).join(", ")} y ${names[names.length - 1]}`;
    }

    function allowedSectionsPhrase() {
        const selected = ($form.section_id || []).map(String);
        return !selected.length || selected.includes("all")
            ? "en todas las secciones"
            : "en esta sección";
    }

    function toggleTooltip(key) {
        openTooltip = openTooltip === key ? null : key;
    }

    function closeTooltipOnOutsideClick() {
        openTooltip = null;
    }

    function openCalendar(element) {
        const now = Date.now();
        if (now - lastShowPickerAt < 300) return;
        lastShowPickerAt = now;
        try {
            element.showPicker && element.showPicker();
        } catch (_) {}
    }

    onDestroy(() => {
        if (allowedTimer) clearTimeout(allowedTimer);
        window.removeEventListener("click", closeTooltipOnOutsideClick);
    });

    onMount(() => {
        window.addEventListener("click", closeTooltipOnOutsideClick);
    });

    function initialMomentIdFor(schoolLapse) {
        if (!schoolLapse?.lapses?.length) return "";
        const today = new Date().toISOString().slice(0, 10);
        const current =
            schoolLapse.lapses.find(
                (l) => today >= (l.start || "") && today <= (l.end || ""),
            ) || schoolLapse.lapses[schoolLapse.lapses.length - 1];
        return current?.id || "";
    }

    function resetForm() {
        $form.reset();
        allowedWeekdays = null;
        openTooltip = null;
        showDateErrors = false;
        $form.school_lapse_id = activeLapse?.id || "";
        $form.lapse_id = isTeacher
            ? initialMomentIdFor(activeLapse)
            : activeLapse?.lapses?.[0]?.id || "";
        $form.section_id = [];
        $form.units = [createUnit()];
        $form.status = "pending";
    }

    export function open() {
        resetForm();
        editingPlanId = null;
        submitStatus = "Crear";
        showFormModal = true;
    }

    export function openForEdit(plan) {
        editingPlanId = plan.id;
        submitStatus = "Editar";
        showDateErrors = false;
        if (!isTeacher) {
            $form.teacher_id = plan.teacher_id || "";
        }
        $form.description = plan.description || "";
        $form.rasgos_points = plan.rasgos_points ?? 0;
        $form.status = plan.status === "draft" ? "draft" : "pending";
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
                      unit_number: unit.unit_number ?? unitIndex + 1,
                      name: unit.name || "",
                      topics:
                          Array.isArray(unit.topics) && unit.topics.length
                              ? unit.topics.map((topic) => ({
                                    name: topic.name || "",
                                    assessment_type:
                                        topic.assessment_type || "",
                                    percentage: topic.percentage ?? "",
                                    points: topic.points ?? "",
                                    scheduled_date: topic.scheduled_date || "",
                                    description: topic.description || "",
                                }))
                              : [createTopic()],
                  }))
                : [createUnit()];
        showFormModal = true;
    }

    // Tracks the submit intent ("draft" | "pending"). Kept separate from
    // $form.status because useForm clears state after a successful submit,
    // so the success message must read this captured value.
    let submittedAs = "pending";

    function submit(event) {
        event.preventDefault();
        $form.clearErrors();
        submittedAs = $form.status || "pending";

        if (submittedAs !== "draft" && missingDatesCount() > 0) {
            showDateErrors = true;
            displayAlert({
                type: "error",
                message:
                    "Debes asignar una fecha a cada tema de evaluación antes de enviar el plan a aprobación.",
            });
            return;
        }
        showDateErrors = false;

        if (submitStatus === "Editar") {
            const url = isTeacher
                ? `/dashboard/mis-planes/${editingPlanId}`
                : `/dashboard/planes-evaluacion/${editingPlanId}`;

            $form.put(url, {
                onSuccess: () => {
                    showFormModal = false;
                    resetForm();
                    editingPlanId = null;
                    submitStatus = "Crear";
                    dispatch("saved");
                    displayAlert({
                        type: "success",
                        message: isTeacher
                            ? submittedAs === "draft"
                                ? "Borrador actualizado correctamente"
                                : "Plan actualizado y enviado a aprobación correctamente"
                            : "Plan de evaluación actualizado correctamente",
                    });
                },
                onError: (errors) => {
                    displayAlert({
                        type: "error",
                        message:
                            errors.message ||
                            errors.units ||
                            "Verifique los datos del plan",
                    });
                },
            });
            return;
        }

        const onSuccess = () => {
            showFormModal = false;
            resetForm();
            editingPlanId = null;
            submitStatus = "Crear";
            dispatch("saved");
            displayAlert({
                type: "success",
                message: isTeacher
                    ? submittedAs === "draft"
                        ? "Borrador guardado correctamente"
                        : "Plan enviado a aprobación correctamente"
                    : "Plan de evaluación creado y aprobado correctamente",
            });
        };
        const onError = (errors) => {
            displayAlert({
                type: "error",
                message:
                    errors.message ||
                    errors.units ||
                    "Verifique los datos del plan",
            });
        };

        if (isTeacher) {
            $form.post("/dashboard/mis-planes", { onSuccess, onError });
        } else {
            $form.post("/dashboard/planes-evaluacion", { onSuccess, onError });
        }
    }

    function percentageToPoints(value) {
        const percentage = parseFloat(value);
        return Number.isNaN(percentage)
            ? ""
            : Math.round((percentage / 100) * totalPoints * 100) / 100;
    }

    function pointsToPercentage(value) {
        const points = parseFloat(value);
        return Number.isNaN(points)
            ? ""
            : Math.round((points / totalPoints) * 100 * 100) / 100;
    }

    function updatePercentage(unitIndex, topicIndex, value) {
        $form.units[unitIndex].topics[topicIndex].percentage = value;
        $form.units[unitIndex].topics[topicIndex].points =
            percentageToPoints(value);
    }

    function updatePoints(unitIndex, topicIndex, value) {
        $form.units[unitIndex].topics[topicIndex].points = value;
        $form.units[unitIndex].topics[topicIndex].percentage =
            pointsToPercentage(value);
    }

    function addUnit() {
        $form.units = [...$form.units, createUnit($form.units.length + 1)];
    }

    function removeUnit(index) {
        const units = $form.units.filter((_, unitIndex) => unitIndex !== index);
        $form.units = units.length ? units : [createUnit()];
    }

    function addTopic(unitIndex) {
        const units = [...$form.units];
        units[unitIndex].topics = [...units[unitIndex].topics, createTopic()];
        $form.units = units;
    }

    function removeTopic(unitIndex, topicIndex) {
        const units = [...$form.units];
        const topics = units[unitIndex].topics.filter(
            (_, index) => index !== topicIndex,
        );
        units[unitIndex].topics = topics.length ? topics : [createTopic()];
        $form.units = units;
    }

    function toggleSection(id) {
        const value = String(id);
        const selected = ($form.section_id || [])
            .map(String)
            .filter((item) => item !== "all");
        $form.section_id = selected.includes(value)
            ? selected.filter((item) => item !== value)
            : [...selected, value];
    }

    function toggleAllSections(checked) {
        $form.section_id = checked ? ["all"] : [];
    }
</script>

{#if renderTriggerButton}
    <button class="animated-button w-fitcontent" on:click={open}>
        <span class="text">Nuevo plan</span>
        <span class="circle"></span>
    </button>
{/if}

<Modal bind:showModal={showFormModal} classes={"w-fit"}>
    <form
        on:submit={submit}
        id="admin-plan-form"
        class="max-w-[1200px] pt-2 px-5"
    >
        <h3 class="text-lg font-bold text-color1 mb-3">
            {submitStatus === "Crear"
                ? "Nuevo plan de evaluación"
                : "Editar plan de evaluación"}
        </h3>

        <div class="grid grid-cols-12 gap-x-6">
        {#if !isTeacher}
            <Input
                type="select"
                label="Profesor"
                bind:value={$form.teacher_id}
                error={$form.errors?.teacher_id}
                required={true}
                classes="col-span-3"
            >
                <option value="">Seleccione...</option>
                {#each data.teachers || [] as teacher}
                    <option value={teacher.id}>{teacher.name}</option>
                {/each}
            </Input>
        {/if}
            <Input
                type="select"
                label="Materia"
                bind:value={$form.matter_id}
                error={$form.errors?.matter_id}
                required={true}
                classes="col-span-3"
            >
                <option value="">Seleccione...</option>
                {#each availableMatters as matter}
                    <option value={matter.id}>{matter.name}</option>
                {/each}
            </Input>
            <Input
                type="select"
                label="Período escolar"
                bind:value={$form.school_lapse_id}
                error={$form.errors?.school_lapse_id}
                required={true}
                classes="col-span-2"
            >
                {#each data.school_lapses || [] as lapse}
                    <option value={lapse.id}>{lapse.label}</option>
                {/each}
            </Input>
            <Input
                type="select"
                label="Momento escolar"
                bind:value={$form.lapse_id}
                error={$form.errors?.lapse_id}
                required={true}
                classes="col-span-2"
            >
                {#each moments as moment}
                    <option value={moment.id}>{moment.label}</option>
                {/each}
            </Input>
            <Input
                type="select"
                label="Año"
                bind:value={$form.course_id}
                error={$form.errors?.course_id}
                required={true}
                classes="col-span-2"
                on:change={() => ($form.section_id = [])}
            >
                <option value="">Seleccione...</option>
                {#each data.courses || [] as course}
                    <option value={course.id}>{course.name}</option>
                {/each}
            </Input>

            <div class="col-span-12 flex gap-5">
                <div class="mb-4 col-span-7">
                    <label
                        class="block text-sm font-semibold text-gray-600 mb-1 mt-7"
                        >Secciones</label
                    >
                    <div class="flex flex-wrap gap-3">
                        {#if courseSectionsCount === 0}
                            <p class="text-xs text-gray-500">
                                Seleccione un año para ver sus secciones.
                            </p>
                        {/if}
                        {#each courseSections as section}
                            <label class="flex items-center gap-1 text-sm">
                                <input
                                    type="checkbox"
                                    disabled={($form.section_id || []).includes(
                                        "all",
                                    )}
                                    checked={($form.section_id || [])
                                        .map(String)
                                        .includes(String(section.id))}
                                    on:change={() => toggleSection(section.id)}
                                />
                                {section.name}
                            </label>
                        {/each}
                        {#if courseSectionsCount > 0}
                            <label
                                class="flex items-center w-full gap-1 -mt-1 text-sm font-semibold"
                            >
                                <input
                                    type="checkbox"
                                    checked={($form.section_id || []).includes(
                                        "all",
                                    )}
                                    on:change={(event) =>
                                        toggleAllSections(
                                            event.currentTarget.checked,
                                        )}
                                />
                                Todas las secciones
                            </label>
                        {/if}
                    </div>
                    {#if $form.errors?.section_id}<p
                            class="text-xs text-red mt-1"
                        >
                            {$form.errors.section_id}
                        </p>{/if}
                </div>

                <Input
                    label="Descripción"
                    bind:value={$form.description}
                    error={$form.errors?.description}
                    classes=""
                />
            </div>
        </div>

        <div class="flex flex-wrap items-end gap-x-6 gap-y-2 mt-3 mb-2">
            <div class="flex flex-col gap-1">
                <label class="text-xs md:text-sm font-semibold text-gray-700">
                    Puntos de rasgos 
                </label>
                <select
                    class="rounded-md border border-gray-300 px-3 py-2 text-sm bg-white"
                    bind:value={$form.rasgos_points}
                >
                    {#each Array.from({ length: 11 }, (_, i) => i) as n}
                        <option value={n}>{n}</option>
                    {/each}
                </select>
                <p class="text-[11px] text-gray-500">
                    Conducta/puntualidad. 1 punto = 5%.
                </p>
                {#if $form.errors?.rasgos_points}
                    <p class="text-red text-xs font-semibold">
                        {$form.errors.rasgos_points}
                    </p>
                {/if}
            </div>

            <div
                class="rounded-md px-4 py-2 text-sm font-semibold {totalIsValid
                    ? 'bg-green-50 text-green-700 border border-green-200'
                    : 'bg-red/10 text-red border border-red/30'}"
            >
                Total: {evalTotalPct}% (evaluaciones)
                {rasgosPct > 0 ? ` + ${rasgosPct}% (rasgos)` : ""} =
                {planTotalPct}%
                {#if !totalIsValid}
                    <span class="block text-xs font-normal mt-0.5"
                        >Debe sumar 100% (evaluaciones + rasgos).</span
                    >
                {/if}
            </div>
        </div>

        <div class="space-y-4 max-h-[48vh] overflow-y-auto pr-2">
            {#each $form.units as unit, unitIndex}
                <div class="rounded-lg shadow-lg bg-gray-50 p-3 md:p-5 ">
                    <div class="flex gap-2 items-center mb-3">
                        <span class="text-xs font-semibold text-gray-500">
                            Unidad {unitIndex + 1}
                        </span>
                        <input
                            class="rounded-md border border-gray-300 px-3 py-2 text-sm flex-1"
                            placeholder="Nombre de la unidad"
                            bind:value={$form.units[unitIndex].name}
                        />
                        <button
                            type="button"
                            class="text-sm hover:text-red text-gray-500"
                            on:click={() => removeUnit(unitIndex)}
                            >Quitar unidad</button
                        >
                    </div>
                    <div class="space-y-2">
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
                                    class="rounded-md border border-gray-300 px-3 py-2 text-sm"
                                    placeholder="Tema"
                                    bind:value={
                                        $form.units[unitIndex].topics[
                                            topicIndex
                                        ].name
                                    }
                                ></textarea>
                                <input
                                    class="rounded-md border border-gray-300 px-3 py-2 text-sm"
                                    placeholder="Tipo de prueba"
                                    bind:value={
                                        $form.units[unitIndex].topics[
                                            topicIndex
                                        ].assessment_type
                                    }
                                />
                                <textarea
                                    class="rounded-md border border-gray-300 px-3 py-2 text-sm"
                                    placeholder="Descripción"
                                    bind:value={
                                        $form.units[unitIndex].topics[
                                            topicIndex
                                        ].description
                                    }
                                ></textarea>
                                <div
                                    class="flex w-[70px] mr-2 items-center relative"
                                >
                                    <input
                                        type="number"
                                        min="0.01"
                                        max="100"
                                        step="0.01"
                                        placeholder="%"
                                        class="rounded-md border w-[70px] border-gray-300 px-3 py-2 text-sm"
                                        value={topic.percentage}
                                        on:input={(event) =>
                                            updatePercentage(
                                                unitIndex,
                                                topicIndex,
                                                event.currentTarget.value,
                                            )}
                                    />
                                    {#if topic.percentage > 0}
                                        <b
                                            class="text-xs absolute top-2.5 right-1 p-1 px-2 text-gray-600 bg-white z-10"
                                            >%</b
                                        >
                                    {/if}
                                </div>
                                <div
                                    class="flex w-[63px] items-center relative"
                                >
                                    {#if topic.points > 0}
                                        <b
                                            class="text-xs absolute top-2.5 right-1 p-1 px-1 text-gray-600 bg-white z-10"
                                            >Pts</b
                                        >
                                    {/if}
                                    <input
                                        type="number"
                                        min="0"
                                        step="0.01"
                                        placeholder="Pts"
                                        class="rounded-md border w-[63px] border-gray-300 px-2 py-2 text-sm"
                                        value={topic.points}
                                        on:input={(event) =>
                                            updatePoints(
                                                unitIndex,
                                                topicIndex,
                                                event.currentTarget.value,
                                            )}
                                    />
                                </div>
                                <!-- svelte-ignore a11y-no-static-element-interactions -->
                                <!-- svelte-ignore a11y-click-events-have-key-events -->
                                <div class="relative" on:click|stopPropagation>
                                    <input
                                        type="date"
                                        class="rounded-md border border-gray-300 px-2 py-2 text-sm"
                                        class:border-red={showDateErrors &&
                                            topicMissingDate(topic)}
                                        bind:value={
                                            $form.units[unitIndex].topics[
                                                topicIndex
                                            ].scheduled_date
                                        }
                                        on:click={(event) =>
                                            openCalendar(event.currentTarget)}
                                        on:focus={(event) => {
                                            openCalendar(event.currentTarget);
                                            toggleTooltip(
                                                `${unitIndex}-${topicIndex}`,
                                            );
                                        }}
                                        on:blur={() => toggleTooltip(null)}
                                        title="Clic para abrir el calendario"
                                    />
                                    {#if openTooltip === `${unitIndex}-${topicIndex}` && allowedWeekdays?.length}
                                        <div
                                            class="right-36 absolute bg-white bottom-full z-30 mb-2 w-60 rounded-lg border border-amber-300 bg-amber-50 p-2.5 text-xs font-medium text-amber-700 shadow-lg"
                                        >
                                            Para esta materia
                                            {allowedSectionsPhrase()}
                                            das clases los días
                                            <b
                                                >{describeAllowedDays(
                                                    allowedWeekdays,
                                                )}.</b
                                            >
                                        </div>
                                    {/if}
                                </div>
                                <button
                                    type="button"
                                    class="hover:text-red text-gray-500 pt-2"
                                    title="Quitar tema"
                                    on:click={() =>
                                        removeTopic(unitIndex, topicIndex)}
                                    ><iconify-icon
                                        icon="mdi:close-circle-outline"
                                        width="22"
                                        height="22"
                                    ></iconify-icon></button
                                >
                            </div>
                        {/each}
                    </div>
                    <button
                        type="button"
                        class="mt-3 text-xs px-3 py-1.5 bg-gray-200 hover:shadow-lg hover:font-semibold text-gray-700 rounded-md"
                        on:click={() => addTopic(unitIndex)}
                        >+ Agregar tema</button
                    >
                </div>
            {/each}
        </div>
        <button
            type="button"
            class="mt-3 text-xs px-3 py-1.5 bg-color1/20 hover:shadow-lg hover:font-semibold text-gray-700 rounded-md"
            on:click={addUnit}>+ Agregar unidad</button
        >
    </form>
    <svelte:fragment slot="btn_footer">
        {#if isTeacher}
            <div class="flex gap-3 items-center justify-end">
                <button
                    form="admin-plan-form"
                    type="submit"
                    class="toolbar-secondary min-w-[190px] justify-center"
                    disabled={$form.processing}
                    on:click={() => ($form.status = "draft")}
                >
                    {#if $form.processing}Cargando...{:else}<iconify-icon
                            icon="mdi:note-edit-outline"
                            width="20"
                            height="20"
                        />Guardar borrador{/if}
                </button>
                <button
                    form="admin-plan-form"
                    type="submit"
                    class="animated-button min-w-[200px] flex gap-2 hover:bg-[#c5e5e4]"
                    disabled={$form.processing}
                    on:click={() => ($form.status = "pending")}
                >
                    {#if $form.processing}Cargando...{:else}<iconify-icon
                            icon="material-symbols:send-rounded"
                            width="22"
                            height="22"
                        /><span>{submitStatus === "Crear"
                                ? "Enviar a aprobación"
                                : "Guardar y enviar"}</span>{/if}
                </button>
            </div>
        {:else}
            <button
                form="admin-plan-form"
                type="submit"
                class="animated-button min-w-[200px]"
                disabled={$form.processing}
            >
                {#if $form.processing}Cargando...{:else}<iconify-icon
                        icon="material-symbols:save-sharp"
                        width="24"
                        height="24"
                    /> Crear{/if}
            </button>
        {/if}
    </svelte:fragment>
</Modal>
