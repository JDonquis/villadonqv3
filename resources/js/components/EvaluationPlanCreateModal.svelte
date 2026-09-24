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
    export let keyShortcut = null;
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
        return !name && !(percentage > 0) && !(points > 0);
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
            const response = await fetch(
                `${allowedDaysUrl}?${query.toString()}`,
            );
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
    <div class="hidden sm:flex sm:ml-auto">
        <button
            class="animated-button w-fitcontent flex items-center justify-center gap-3"
            on:click={open}
        >
            <svg
                xmlns="http://www.w3.org/2000/svg"
                class="arr-2"
                viewBox="0 0 24 24"
            >
                <path
                    d="M16.1716 10.9999L10.8076 5.63589L12.2218 4.22168L20 11.9999L12.2218 19.778L10.8076 18.3638L16.1716 12.9999H4V10.9999H16.1716Z"
                ></path>
            </svg>
            <iconify-icon
                icon="line-md:plus"
                class="text"
                width="20"
                height="20"
            ></iconify-icon>
            <span class="text">Nuevo plan</span>
            <span class="circle"></span>
            <svg
                xmlns="http://www.w3.org/2000/svg"
                class="arr-1"
                viewBox="0 0 24 24"
            >
                <path
                    d="M16.1716 10.9999L10.8076 5.63589L12.2218 4.22168L20 11.9999L12.2218 19.778L10.8076 18.3638L16.1716 12.9999H4V10.9999H16.1716Z"
                ></path>
            </svg>
        </button>
    </div>
    <button
        type="button"
        class="fixed-bottom-mobile fab sm:hidden bg-color1 text-white"
        on:click={open}
        aria-label="Nuevo plan"
    >
        <iconify-icon icon="mdi:plus" width="26" height="26"></iconify-icon>
    </button>
{/if}

<Modal
    bind:showModal={showFormModal}
    {keyShortcut}
    onKeyShortcut={open}
    classes="w-full "
>
    <!-- Contenedor Principal con estilo SaaS Moderno -->
    <form
        on:submit={submit}
        id="admin-plan-form"
        class="p-6 bg-slate-50/60 rounded-xl space-y-6"
    >
        <!-- Header del Modal -->
        <div
            class="flex items-center justify-between pb-4 border-b border-slate-200"
        >
            <div>
               
                <h3 class="text-xl font-bold text-slate-900 mt-1">
                    {submitStatus === "Crear"
                        ? "Nuevo plan de evaluación"
                        : "Editar plan de evaluación"}
                </h3>
            </div>
            <div class="text-xs text-slate-400 font-medium">
                Ponderación base: 20 pts (100%)
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">
            <!-- COLUMNA IZQUIERDA: Parámetros del Curso & Balance -->
            <div class="lg:col-span-4 space-y-5 lg:sticky lg:top-4">
                <!-- Tarjeta 1: Parámetros Generales -->
                <div
                    class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-sm space-y-4"
                >
                    <div
                        class="flex items-center gap-2 pb-2 border-b border-slate-100"
                    >
                        <svg
                            class="w-4 h-4 text-colorbg-color1"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke="currentColor"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"
                            />
                        </svg>
                        <h4
                            class="text-xs font-bold uppercase tracking-wider text-slate-700"
                        >
                            Parámetros del Curso
                        </h4>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-3 mt-0 mb-0">
                        {#if !isTeacher}
                            <Input
                                type="select"
                                label="Profesor"
                                bind:value={$form.teacher_id}
                                error={$form.errors?.teacher_id}
                                required={true}
                                classes="col-span-2"
                            >
                                <option value="">Seleccione...</option>
                                {#each data.teachers || [] as teacher}
                                    <option value={teacher.id}
                                        >{teacher.name}</option
                                    >
                                {/each}
                            </Input>
                        {/if}

                        <Input
                            type="select"
                            label="Materia"
                            bind:value={$form.matter_id}
                            error={$form.errors?.matter_id}
                            required={true}
                            classes="col-span-2"
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
                            classes="col-span-1"
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
                            classes="col-span-1"
                        >
                            {#each moments as moment}
                                <option value={moment.id}>{moment.label}</option
                                >
                            {/each}
                        </Input>

                        <Input
                            type="select"
                            label="Año / Grado"
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
                    </div>

                    <!-- Selector de Secciones Estilizado -->
                    <div class="pt-1">
                        <label
                            class="block text-xs font-semibold text-slate-700 mb-2"
                        >
                            Secciones asignadas <span class="text-rose-500"
                                >*</span
                            >
                        </label>
                        {#if courseSectionsCount === 0}
                            <div
                                class="text-xs text-slate-400 bg-slate-50 border border-dashed border-slate-200 rounded-lg p-3 text-center"
                            >
                                Seleccione un año para cargar las secciones
                                disponibles.
                            </div>
                        {:else}
                            <div class="flex flex-wrap gap-2">
                                <label
                                    class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-medium border cursor-pointer transition-colors {(
                                        $form.section_id || []
                                    ).includes('all')
                                        ? 'bg-color4/30 border-indigo-300 text-colorbg-color2 font-semibold'
                                        : 'bg-white border-slate-200 text-slate-600 hover:bg-slate-50'}"
                                >
                                    <input
                                        type="checkbox"
                                        class="rounded text-colorbg-color1 focus:ring-color1 w-3.5 h-3.5"
                                        checked={(
                                            $form.section_id || []
                                        ).includes("all")}
                                        on:change={(event) =>
                                            toggleAllSections(
                                                event.currentTarget.checked,
                                            )}
                                    />
                                    Todas
                                </label>

                                {#each courseSections as section}
                                    <label
                                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-medium border cursor-pointer transition-colors {(
                                            $form.section_id || []
                                        )
                                            .map(String)
                                            .includes(String(section.id))
                                            ? 'bg-color4/30 border-indigo-200 text-colorbg-color2'
                                            : 'bg-white border-slate-200 text-slate-600 hover:bg-slate-50'} {(
                                            $form.section_id || []
                                        ).includes('all')
                                            ? 'opacity-60 cursor-not-allowed'
                                            : ''}"
                                    >
                                        <input
                                            type="checkbox"
                                            class="rounded text-colorbg-color1 focus:ring-color1 w-3.5 h-3.5"
                                            disabled={(
                                                $form.section_id || []
                                            ).includes("all")}
                                            checked={($form.section_id || [])
                                                .map(String)
                                                .includes(String(section.id))}
                                            on:change={() =>
                                                toggleSection(section.id)}
                                        />
                                        {section.name}
                                    </label>
                                {/each}
                            </div>
                        {/if}
                        {#if $form.errors?.section_id}
                            <p class="text-xs text-rose-500 mt-1.5 font-medium">
                                {$form.errors.section_id}
                            </p>
                        {/if}
                    </div>

                    <Input
                        label="Descripción general / Objetivos"
                        bind:value={$form.description}
                        error={$form.errors?.description}
                        classes="col-span-2"
                        placeholder="Especifique el enfoque curricular, pautas o normativas de recuperación..."
                    />
                </div>

                <!-- Tarjeta 2: Balance, Ponderación y Rasgos -->
                <div
                    class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-sm space-y-4"
                >
                    <div
                        class="flex items-center justify-between pb-2 border-b border-slate-100"
                    >
                        <h4
                            class="text-xs font-bold uppercase tracking-wider text-slate-700"
                        >
                            Balance y Rasgos
                        </h4>
                        <span
                            class="text-xs font-bold px-2.5 py-0.5 rounded-full {totalIsValid
                                ? 'bg-emerald-100 text-emerald-700'
                                : 'bg-amber-100 text-amber-700'}"
                        >
                            {planTotalPct}% del total
                        </span>
                    </div>

                    <!-- Barra de progreso visual -->
                    <div>
                        <div
                            class="flex justify-between text-xs font-semibold text-slate-600 mb-1.5"
                        >
                            <span>Total acumulado</span>
                            <span
                                class={totalIsValid
                                    ? "text-emerald-600 font-bold"
                                    : "text-slate-800"}
                                >{planTotalPct}% / 100%</span
                            >
                        </div>
                        <div
                            class="w-full bg-slate-100 h-2.5 rounded-full overflow-hidden flex"
                        >
                            <div
                                class="bg-color2 transition-all duration-300"
                                style="width: {Math.min(evalTotalPct, 100)}%"
                                title="Evaluaciones: {evalTotalPct}%"
                            ></div>
                            <div
                                class="bg-emerald-500 transition-all duration-300"
                                style="width: {Math.min(
                                    rasgosPct,
                                    100 - evalTotalPct,
                                )}%"
                                title="Rasgos: {rasgosPct}%"
                            ></div>
                        </div>
                        <div
                            class="flex items-center gap-4 mt-2 text-[11px] text-slate-500"
                        >
                            <span class="flex items-center gap-1.5">
                                <span
                                    class="w-2 h-2 rounded-full bg-color2 inline-block"
                                ></span>
                                Eval: {evalTotalPct}%
                            </span>
                            <span class="flex items-center gap-1.5">
                                <span
                                    class="w-2 h-2 rounded-full bg-emerald-500 inline-block"
                                ></span>
                                Rasgos: {rasgosPct}%
                            </span>
                        </div>
                    </div>

                    <!-- Selector de Puntos de Rasgos -->
                    <div class="pt-2 border-t border-slate-100">
                        <div class="flex items-center justify-between gap-2">
                            <div>
                                <label
                                    class="block text-xs font-semibold text-slate-700"
                                >
                                    Puntos de rasgos
                                </label>
                                <p class="text-[11px] text-slate-400">
                                    Conducta / puntualidad (1 pt = 5%)
                                </p>
                            </div>
                            <select
                                class="rounded-lg border border-slate-200 px-3 py-1.5 text-sm bg-slate-50 font-semibold text-slate-700 focus:bg-white focus:ring-2 focus:ring-color1/20 focus:border-color1 outline-none"
                                bind:value={$form.rasgos_points}
                            >
                                {#each Array.from({ length: 11 }, (_, i) => i) as n}
                                    <option value={n}>{n} pts ({n * 5}%)</option
                                    >
                                {/each}
                            </select>
                        </div>
                        {#if $form.errors?.rasgos_points}
                            <p class="text-rose-500 text-xs font-semibold mt-1">
                                {$form.errors.rasgos_points}
                            </p>
                        {/if}
                    </div>

                    <!-- Estado / Alerta de Validación -->
                    {#if totalIsValid}
                        <div
                            class="flex items-center gap-2 text-xs font-medium text-emerald-700 bg-emerald-50/80 border border-emerald-200 p-2.5 rounded-xl"
                        >
                            <svg
                                class="w-4 h-4 text-emerald-600 shrink-0"
                                fill="currentColor"
                                viewBox="0 0 20 20"
                            >
                                <path
                                    fill-rule="evenodd"
                                    d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                                    clip-rule="evenodd"
                                />
                            </svg>
                            <span
                                >Distribución completa. Cumple con la normativa
                                académica.</span
                            >
                        </div>
                    {:else}
                        <div
                            class="flex items-start gap-2 text-xs font-medium text-amber-800 bg-amber-50 border border-amber-200 p-2.5 rounded-xl"
                        >
                            <svg
                                class="w-4 h-4 text-amber-600 shrink-0 mt-0.5"
                                fill="currentColor"
                                viewBox="0 0 20 20"
                            >
                                <path
                                    fill-rule="evenodd"
                                    d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z"
                                    clip-rule="evenodd"
                                />
                            </svg>
                            <div>
                                <span
                                    >La suma actual es de <b>{planTotalPct}%</b
                                    >. Debe ser exactamente <b>100%</b> (faltan {100 -
                                        planTotalPct}%).</span
                                >
                            </div>
                        </div>
                    {/if}
                </div>
            </div>

            <!-- COLUMNA DERECHA: Constructor de Unidades y Evaluaciones -->
            <div class="lg:col-span-8 space-y-6">
                {#each $form.units as unit, unitIndex}
                    <div
                        class="bg-white rounded-2xl border border-slate-200/90 shadow-sm overflow-hidden"
                    >
                        <!-- Header de Unidad -->
                        <div
                            class="bg-slate-50/70 px-5 py-3.5 border-b border-slate-200/80 flex flex-wrap items-center gap-3 justify-between"
                        >
                            <div
                                class="flex items-center gap-3 flex-1 min-w-[240px]"
                            >
                                <span
                                    class="bg-color2 text-white text-xs font-bold px-2.5 py-1 rounded-lg"
                                >
                                    U{unitIndex + 1}
                                </span>
                                <input
                                    class="bg-transparent font-semibold text-slate-800 text-sm md:text-base placeholder:text-slate-400 focus:bg-white focus:ring-2 focus:ring-color1/20 focus:border-indigo-400 border border-transparent rounded-lg px-2.5 py-1 transition-all w-full max-w-md outline-none"
                                    placeholder="Nombre de la unidad (ej. Geometría Analítica)"
                                    bind:value={$form.units[unitIndex].name}
                                />
                            </div>
                            <button
                                type="button"
                                class="text-xs font-medium text-slate-400 hover:text-rose-600 transition-colors flex items-center gap-1"
                                on:click={() => removeUnit(unitIndex)}
                            >
                                <svg
                                    class="w-3.5 h-3.5"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                    stroke="currentColor"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"
                                    />
                                </svg>
                                Quitar unidad
                            </button>
                        </div>

                        <!-- Data Grid / Cabecera de Temas -->
                        <div class="p-4 sm:p-5 space-y-3">
                            <div
                                class="hidden md:grid md:grid-cols-[1.2fr_1fr_1.3fr_75px_70px_135px_36px] gap-3 text-[11px] font-bold uppercase tracking-wider text-slate-400 px-2"
                            >
                                <span>Tema / Contenido</span>
                                <span>Tipo de prueba</span>
                                <span>Criterios / Descripción</span>
                                <span class="text-center">%</span>
                                <span class="text-center">Pts</span>
                                <span>Fecha</span>
                                <span></span>
                            </div>

                            <!-- Filas de Evaluación -->
                            <div class="space-y-3">
                                {#each unit.topics as topic, topicIndex}
                                    <div
                                        class="grid grid-cols-1 md:grid-cols-[1.2fr_1fr_1.3fr_75px_70px_135px_36px] gap-2.5 items-center rounded-xl hover:border-slate-300 hover:bg-slate-50/40 transition-all bg-white"
                                    >
                                        <!-- Nombre del Tema -->
                                        <div>
                                            <label
                                                class="block md:hidden text-[11px] font-bold text-slate-500 mb-1"
                                                >Tema #{topicIndex + 1}</label
                                            >
                                            <input
                                                type="text"
                                                class="w-full rounded-lg border border-slate-200 px-3 py-2 text-xs text-slate-800 placeholder:text-slate-400 focus:border-color1 focus:ring-2 focus:ring-color1/20 outline-none"
                                                placeholder="Nombre del tema..."
                                                bind:value={
                                                    $form.units[unitIndex]
                                                        .topics[topicIndex].name
                                                }
                                            />
                                        </div>

                                        <!-- Tipo de prueba -->
                                        <div>
                                            <label
                                                class="block md:hidden text-[11px] font-bold text-slate-500 mb-1"
                                                >Tipo de prueba</label
                                            >
                                            <input
                                                class="w-full rounded-lg border border-slate-200 px-3 py-2 text-xs text-slate-800 placeholder:text-slate-400 focus:border-color1 focus:ring-2 focus:ring-color1/20 outline-none"
                                                placeholder="Ej. Taller, Examen..."
                                                bind:value={
                                                    $form.units[unitIndex]
                                                        .topics[topicIndex]
                                                        .assessment_type
                                                }
                                            />
                                        </div>

                                        <!-- Descripción -->
                                        <div>
                                            <label
                                                class="block md:hidden text-[11px] font-bold text-slate-500 mb-1"
                                                >Descripción</label
                                            >
                                            <input
                                                class="w-full rounded-lg border border-slate-200 px-3 py-2 text-xs text-slate-800 placeholder:text-slate-400 focus:border-color1 focus:ring-2 focus:ring-color1/20 outline-none"
                                                placeholder="Criterios o rúbrica..."
                                                bind:value={
                                                    $form.units[unitIndex]
                                                        .topics[topicIndex]
                                                        .description
                                                }
                                            />
                                        </div>

                                        <!-- Porcentaje (%) -->
                                        <div class="relative">
                                            <label
                                                class="block md:hidden text-[11px] font-bold text-slate-500 mb-1"
                                                >Ponderación (%)</label
                                            >
                                            <input
                                                type="number"
                                                min="0.01"
                                                max="100"
                                                step="0.01"
                                                placeholder="%"
                                                class="w-full text-right pr-6 rounded-lg border border-slate-200 px-2 py-2 text-xs font-semibold text-slate-800 focus:border-color1 focus:ring-2 focus:ring-color1/20 outline-none"
                                                value={topic.percentage}
                                                on:input={(event) =>
                                                    updatePercentage(
                                                        unitIndex,
                                                        topicIndex,
                                                        event.currentTarget
                                                            .value,
                                                    )}
                                            />
                                            <span
                                                class="absolute right-2 top-2 text-[10px] font-bold text-slate-400 pointer-events-none"
                                                >%</span
                                            >
                                        </div>

                                        <!-- Puntos (Pts) -->
                                        <div class="relative">
                                            <label
                                                class="block md:hidden text-[11px] font-bold text-slate-500 mb-1"
                                                >Puntos (Pts)</label
                                            >
                                            <input
                                                type="number"
                                                min="0"
                                                step="0.01"
                                                placeholder="Pts"
                                                class="w-full text-right pr-7 rounded-lg border border-slate-200 px-2 py-2 text-xs font-semibold text-slate-800 focus:border-color1 focus:ring-2 focus:ring-color1/20 outline-none"
                                                value={topic.points}
                                                on:input={(event) =>
                                                    updatePoints(
                                                        unitIndex,
                                                        topicIndex,
                                                        event.currentTarget
                                                            .value,
                                                    )}
                                            />
                                            <span
                                                class="absolute right-2 top-2 text-[10px] font-bold text-slate-400 pointer-events-none"
                                                >Pts</span
                                            >
                                        </div>

                                        <!-- Fecha Programada con Tooltip -->
                                        <!-- svelte-ignore a11y-no-static-element-interactions -->
                                        <!-- svelte-ignore a11y-click-events-have-key-events -->
                                        <div
                                            class="relative"
                                            on:click|stopPropagation
                                        >
                                            <label
                                                class="block md:hidden text-[11px] font-bold text-slate-500 mb-1"
                                                >Fecha de entrega</label
                                            >
                                            <input
                                                type="date"
                                                class="w-full rounded-lg border border-slate-200 px-2 py-2 text-xs text-slate-700 focus:border-color1 focus:ring-2 focus:ring-color1/20 outline-none transition-colors"
                                                class:border-rose-400={showDateErrors &&
                                                    topicMissingDate(topic)}
                                                bind:value={
                                                    $form.units[unitIndex]
                                                        .topics[topicIndex]
                                                        .scheduled_date
                                                }
                                                on:click={(event) =>
                                                    openCalendar(
                                                        event.currentTarget,
                                                    )}
                                                on:focus={(event) => {
                                                    openCalendar(
                                                        event.currentTarget,
                                                    );
                                                    toggleTooltip(
                                                        `${unitIndex}-${topicIndex}`,
                                                    );
                                                }}
                                                on:blur={() =>
                                                    toggleTooltip(null)}
                                                title="Clic para abrir el calendario"
                                            />
                                            {#if openTooltip === `${unitIndex}-${topicIndex}` && allowedWeekdays?.length}
                                                <div
                                                    class="absolute right-0 bottom-full z-30 mb-2 w-64 rounded-xl border border-amber-200 bg-amber-50 p-3 text-xs font-medium text-amber-800 shadow-xl"
                                                >
                                                    Para esta materia {allowedSectionsPhrase()}
                                                    das clases los días
                                                    <b
                                                        >{describeAllowedDays(
                                                            allowedWeekdays,
                                                        )}.</b
                                                    >
                                                </div>
                                            {/if}
                                        </div>

                                        <!-- Acción Borrar Fila -->
                                        <div class="flex justify-end">
                                            <button
                                                type="button"
                                                class="p-1.5 rounded-lg text-slate-400 hover:text-rose-600 hover:bg-rose-50 transition-colors"
                                                title="Quitar tema"
                                                on:click={() =>
                                                    removeTopic(
                                                        unitIndex,
                                                        topicIndex,
                                                    )}
                                            >
                                                <svg
                                                    class="w-4 h-4"
                                                    fill="none"
                                                    viewBox="0 0 24 24"
                                                    stroke="currentColor"
                                                >
                                                    <path
                                                        stroke-linecap="round"
                                                        stroke-linejoin="round"
                                                        stroke-width="2"
                                                        d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"
                                                    />
                                                </svg>
                                            </button>
                                        </div>
                                    </div>
                                {/each}
                            </div>

                            <!-- Botón Agregar Tema dentro de la Unidad -->
                            <button
                                type="button"
                                class="w-full py-2.5 mt-2 border border-dashed border-slate-300 hover:border-color4 hover:bg-color4/10 text-slate-600 hover:text-colorbg-color2 rounded-xl text-xs font-semibold flex items-center justify-center gap-1.5 transition-all"
                                on:click={() => addTopic(unitIndex)}
                            >
                                <svg
                                    class="w-3.5 h-3.5"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                    stroke="currentColor"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M12 4v16m8-8H4"
                                    />
                                </svg>
                                Agregar tema / evaluación a Unidad {unitIndex +
                                    1}
                            </button>
                        </div>
                    </div>
                {/each}

                <!-- Botón Agregar Nueva Unidad -->
                <button
                    type="button"
                    class="w-full py-3.5 border-2 border-dashed border-indigo-200 hover:border-color1 bg-white hover:bg-color4/30/30 text-colorbg-color1 rounded-2xl text-xs md:text-sm font-bold flex items-center justify-center gap-2 transition-all shadow-sm"
                    on:click={addUnit}
                >
                    <svg
                        class="w-4 h-4"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke="currentColor"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M12 9v3m0 0v3m0-3h3m-3 0H9m12 0a9 9 0 11-18 0 9 9 0 0118 0z"
                        />
                    </svg>
                    + Agregar nueva unidad de aprendizaje
                </button>
            </div>
        </div>
    </form>

    <!-- FOOTER MODAL (Slot btn_footer) -->
    <svelte:fragment slot="btn_footer">
        <div
            class="w-full flex items-center justify-between pt-3 px-2 border-t border-slate-100"
        >
            <span class="text-xs text-slate-400 hidden sm:inline-block">
                Los cambios se validan antes de enviar.
            </span>
            <div class="flex gap-3 items-center justify-end ml-auto">
                {#if isTeacher}
                    <!-- Botón Guardar Borrador -->
                    <button
                        form="admin-plan-form"
                        type="submit"
                        class="px-4 py-2.5 min-w-[290px] mt-2 rounded-xl border border-slate-200 hover:bg-slate-100 text-slate-700 font-semibold text-xs md:text-sm flex items-center justify-center gap-2 transition-colors disabled:opacity-50"
                        disabled={$form.processing}
                        on:click={() => ($form.status = "draft")}
                    >
                        {#if $form.processing}
                            <span>Cargando...</span>
                        {:else}
                            <iconify-icon
                                icon="mdi:note-edit-outline"
                                width="18"
                                height="18"
                            />
                            <span>Guardar borrador</span>
                        {/if}
                    </button>

                    <!-- Botón Enviar a Aprobación (CTA Primario) -->
                    <button
                        form="admin-plan-form"
                        type="submit"
                        class="animated-button min-w-[200px] flex items-center justify-center gap-3"
                        disabled={$form.processing}
                        on:click={() => ($form.status = "pending")}
                    >
                        <svg
                            xmlns="http://www.w3.org/2000/svg"
                            class="arr-2"
                            viewBox="0 0 24 24"
                        >
                            <path
                                d="M16.1716 10.9999L10.8076 5.63589L12.2218 4.22168L20 11.9999L12.2218 19.778L10.8076 18.3638L16.1716 12.9999H4V10.9999H16.1716Z"
                            ></path>
                        </svg>
                        {#if $form.processing}
                            <span class="text">Cargando...</span>
                        {:else}
                            <iconify-icon
                                icon="material-symbols:send-rounded"
                                class="text"
                                width="22"
                                height="22"
                            />
                            <span class="text"
                                >{submitStatus === "Crear"
                                    ? "Enviar a aprobación"
                                    : "Guardar y enviar"}</span
                            >
                        {/if}
                        <span class="circle"></span>
                        <svg
                            xmlns="http://www.w3.org/2000/svg"
                            class="arr-1"
                            viewBox="0 0 24 24"
                        >
                            <path
                                d="M16.1716 10.9999L10.8076 5.63589L12.2218 4.22168L20 11.9999L12.2218 19.778L10.8076 18.3638L16.1716 12.9999H4V10.9999H16.1716Z"
                            ></path>
                        </svg>
                    </button>
                {:else}
                    <!-- Botón Admin Crear -->
                    <button
                        form="admin-plan-form"
                        type="submit"
                        class="animated-button min-w-[200px] flex items-center justify-center gap-3"
                        disabled={$form.processing}
                    >
                        <svg
                            xmlns="http://www.w3.org/2000/svg"
                            class="arr-2"
                            viewBox="0 0 24 24"
                        >
                            <path
                                d="M16.1716 10.9999L10.8076 5.63589L12.2218 4.22168L20 11.9999L12.2218 19.778L10.8076 18.3638L16.1716 12.9999H4V10.9999H16.1716Z"
                            ></path>
                        </svg>
                        {#if $form.processing}
                            <span class="text">Cargando...</span>
                        {:else}
                            <iconify-icon
                                icon="material-symbols:save-sharp"
                                class="text"
                                width="24"
                                height="24"
                            />
                            <span class="text">Crear</span>
                        {/if}
                        <span class="circle"></span>
                        <svg
                            xmlns="http://www.w3.org/2000/svg"
                            class="arr-1"
                            viewBox="0 0 24 24"
                        >
                            <path
                                d="M16.1716 10.9999L10.8076 5.63589L12.2218 4.22168L20 11.9999L12.2218 19.778L10.8076 18.3638L16.1716 12.9999H4V10.9999H16.1716Z"
                            ></path>
                        </svg>
                    </button>
                {/if}
            </div>
        </div>
    </svelte:fragment>
</Modal>
