<script>
    import { useForm } from "@inertiajs/svelte";
    import Modal from "./Modal.svelte";
    import Input from "./Input.svelte";
    import { displayAlert } from "../stores/alertStore";
    import { onDestroy, onMount } from "svelte";

    export let data = {};

    let showFormModal = false;
    let allowedWeekdays = null;
    let allowedTimer = null;
    let openTooltip = null;
    let lastShowPickerAt = 0;
    const totalPoints = 20;
    const allowedDaysUrl = "/dashboard/planes-evaluacion/allowed-days";

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

    const activeLapse = data.school_lapses?.find((item) => item.is_active)
        || data.school_lapses?.[0];
    let form = useForm({
        teacher_id: "",
        name: "",
        description: "",
        matter_id: "",
        school_lapse_id: activeLapse?.id || "",
        lapse_id: activeLapse?.lapses?.[0]?.id || "",
        course_id: "",
        section_id: [],
        units: [createUnit()],
    });

    $: selectedLapse = data.school_lapses?.find(
        (item) => String(item.id) === String($form.school_lapse_id),
    );
    $: moments = selectedLapse?.lapses || [];
    $: selectedTeacher = data.teachers?.find(
        (teacher) => String(teacher.id) === String($form.teacher_id),
    );
    $: teacherMatterIds = (selectedTeacher?.matter_ids || []).map(String);
    $: availableMatters = data.matters?.filter((matter) =>
        teacherMatterIds.includes(String(matter.id)),
    ) || [];

    function normalizeSectionIds() {
        const selected = ($form.section_id || []).map(String);
        if (selected.includes("all")) {
            return (data.sections || []).map((section) => section.id);
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
        if (!$form.teacher_id || !$form.school_lapse_id || !$form.course_id || !$form.matter_id || !sectionIds.length) {
            return;
        }

        const query = new URLSearchParams({
            teacher_id: $form.teacher_id,
            school_lapse_id: $form.school_lapse_id,
            course_id: $form.course_id,
            matter_id: $form.matter_id,
        });
        sectionIds.forEach((id) => query.append("section_ids[]", id));

        try {
            const response = await fetch(`${allowedDaysUrl}?${query.toString()}`);
            const result = await response.json();
            allowedWeekdays = result?.restrict ? result.allowedWeekdays : null;
        } catch (_) {
            allowedWeekdays = null;
        }
    }

    const dayNames = ["", "lunes", "martes", "miércoles", "jueves", "viernes", "sábado", "domingo"];

    function describeAllowedDays(days) {
        const names = (days || []).map((day) => dayNames[Number(day)] || "").filter(Boolean);
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

    function resetForm() {
        $form.reset();
        allowedWeekdays = null;
        openTooltip = null;
        $form.school_lapse_id = activeLapse?.id || "";
        $form.lapse_id = activeLapse?.lapses?.[0]?.id || "";
        $form.section_id = [];
        $form.units = [createUnit()];
    }

    function open() {
        resetForm();
        showFormModal = true;
    }

    function percentageToPoints(value) {
        const percentage = parseFloat(value);
        return Number.isNaN(percentage) ? "" : Math.round(percentage / 100 * totalPoints * 100) / 100;
    }

    function pointsToPercentage(value) {
        const points = parseFloat(value);
        return Number.isNaN(points) ? "" : Math.round(points / totalPoints * 100 * 100) / 100;
    }

    function updatePercentage(unitIndex, topicIndex, value) {
        $form.units[unitIndex].topics[topicIndex].percentage = value;
        $form.units[unitIndex].topics[topicIndex].points = percentageToPoints(value);
    }

    function updatePoints(unitIndex, topicIndex, value) {
        $form.units[unitIndex].topics[topicIndex].points = value;
        $form.units[unitIndex].topics[topicIndex].percentage = pointsToPercentage(value);
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
        const topics = units[unitIndex].topics.filter((_, index) => index !== topicIndex);
        units[unitIndex].topics = topics.length ? topics : [createTopic()];
        $form.units = units;
    }

    function toggleSection(id) {
        const value = String(id);
        const selected = ($form.section_id || []).map(String).filter((item) => item !== "all");
        $form.section_id = selected.includes(value)
            ? selected.filter((item) => item !== value)
            : [...selected, value];
    }

    function toggleAllSections(checked) {
        $form.section_id = checked ? ["all"] : [];
    }

    function submit(event) {
        event.preventDefault();
        $form.post("/dashboard/planes-evaluacion", {
            onSuccess: () => {
                showFormModal = false;
                resetForm();
                displayAlert({ type: "success", message: "Plan de evaluación creado y aprobado correctamente" });
            },
            onError: (errors) => {
                displayAlert({ type: "error", message: errors.message || errors.units || "Verifique los datos del plan" });
            },
        });
    }
</script>

<button class="animated-button w-fitcontent" on:click={open}>
    <span class="text">Nuevo plan</span>
    <span class="circle"></span>
</button>

<Modal bind:showModal={showFormModal} classes={"w-fit"}>
    <form on:submit={submit} id="admin-plan-form" class="max-w-[1200px] pt-2 px-5">
        <h3 class="text-lg font-bold text-color1 mb-3">Nuevo plan de evaluación</h3>

        <div class="grid grid-cols-12 gap-x-6">
            <Input type="select" label="Profesor" bind:value={$form.teacher_id} error={$form.errors?.teacher_id} required={true} classes="col-span-3">
                <option value="">Seleccione...</option>
                {#each data.teachers || [] as teacher}
                    <option value={teacher.id}>{teacher.name}</option>
                {/each}
            </Input>
            <Input type="select" label="Materia" bind:value={$form.matter_id} error={$form.errors?.matter_id} required={true} classes="col-span-3">
                <option value="">Seleccione...</option>
                {#each availableMatters as matter}
                    <option value={matter.id}>{matter.name}</option>
                {/each}
            </Input>
            <Input type="select" label="Período escolar" bind:value={$form.school_lapse_id} error={$form.errors?.school_lapse_id} required={true} classes="col-span-2">
                {#each data.school_lapses || [] as lapse}
                    <option value={lapse.id}>{lapse.label}</option>
                {/each}
            </Input>
            <Input type="select" label="Momento escolar" bind:value={$form.lapse_id} error={$form.errors?.lapse_id} required={true} classes="col-span-2">
                {#each moments as moment}
                    <option value={moment.id}>{moment.label}</option>
                {/each}
            </Input>
            <Input type="select" label="Año" bind:value={$form.course_id} error={$form.errors?.course_id} required={true} classes="col-span-2">
                <option value="">Seleccione...</option>
                {#each data.courses || [] as course}
                    <option value={course.id}>{course.name}</option>
                {/each}
            </Input>

            <div class="col-span-12 flex">
                <div class="mb-4 col-span-7">
                    <label class="block text-sm font-semibold text-gray-600 mb-1 mt-7">Secciones</label>
                    <div class="flex flex-wrap gap-3">
                        {#each data.sections || [] as section}
                        <label class="flex items-center gap-1 text-sm">
                            <input type="checkbox" disabled={($form.section_id || []).includes("all")} checked={($form.section_id || []).map(String).includes(String(section.id))} on:change={() => toggleSection(section.id)} />
                            {section.name}
                        </label>
                        {/each}
                        <label class="flex items-center w-full gap-1  -mt-1 text-sm font-semibold">
                            <input type="checkbox" checked={($form.section_id || []).includes("all")} on:change={(event) => toggleAllSections(event.currentTarget.checked)} />
                            Todas las secciones
                        </label>
                    </div>
                    {#if $form.errors?.section_id}<p class="text-xs text-red mt-1">{$form.errors.section_id}</p>{/if}
                </div>
    
                <Input label="Descripción" bind:value={$form.description} error={$form.errors?.description} classes="" />

            </div>

        </div>


        <div class="space-y-4 max-h-[48vh] overflow-y-auto pr-2">
            {#each $form.units as unit, unitIndex}
                <div class="border border-gray-200 rounded-md p-3">
                    <div class="flex gap-2 items-center mb-3">
                        <input class="rounded-md border border-gray-300 px-3 py-2 text-sm flex-1" placeholder="Nombre de la unidad" bind:value={$form.units[unitIndex].name} />
                        <button type="button" class="text-sm hover:text-red text-gray-500" on:click={() => removeUnit(unitIndex)}>Quitar unidad</button>
                    </div>
                    <div class="space-y-2">
                        {#each unit.topics as topic, topicIndex}
                            <div class="grid grid-cols-[1.3fr_1fr_1.4fr_70px_63px_140px_32px] gap-2 items-start">
                                <textarea class="rounded-md border border-gray-300 px-3 py-2 text-sm" placeholder="Tema" bind:value={$form.units[unitIndex].topics[topicIndex].name}></textarea>
                                <input class="rounded-md border border-gray-300 px-3 py-2 text-sm" placeholder="Tipo de prueba" bind:value={$form.units[unitIndex].topics[topicIndex].assessment_type} />
                                <textarea class="rounded-md border border-gray-300 px-3 py-2 text-sm" placeholder="Descripción" bind:value={$form.units[unitIndex].topics[topicIndex].description}></textarea>
                                <div class="flex w-[70px] mr-2 items-center relative">
                                    <input type="number" min="0.01" max="100" step="0.01" placeholder="%" class="rounded-md border w-[70px] border-gray-300 px-3 py-2 text-sm" value={topic.percentage} on:input={(event) => updatePercentage(unitIndex, topicIndex, event.currentTarget.value)} />
                                    {#if topic.percentage > 0}
                                        <b class="text-xs absolute top-2.5 right-1 p-1 px-2 text-gray-600 bg-white z-10">%</b>
                                    {/if}
                                </div>
                                <div class="flex w-[63px] items-center relative">
                                    {#if topic.points > 0}
                                        <b class="text-xs absolute top-2.5 right-1 p-1 px-1 text-gray-600 bg-white z-10">Pts</b>
                                    {/if}
                                    <input type="number" min="0" step="0.01" placeholder="Pts" class="rounded-md border w-[63px] border-gray-300 px-2 py-2 text-sm" value={topic.points} on:input={(event) => updatePoints(unitIndex, topicIndex, event.currentTarget.value)} />
                                </div>
                                <!-- svelte-ignore a11y-no-static-element-interactions -->
                                <!-- svelte-ignore a11y-click-events-have-key-events -->
                                <div class="relative" on:click|stopPropagation>
                                    <input
                                        type="date"
                                        class="rounded-md border border-gray-300 px-2 py-2 text-sm"
                                        bind:value={$form.units[unitIndex].topics[topicIndex].scheduled_date}
                                        on:click={(event) => openCalendar(event.currentTarget)}
                                        on:focus={(event) => {
                                            openCalendar(event.currentTarget);
                                            toggleTooltip(`${unitIndex}-${topicIndex}`);
                                        }}
                                        on:blur={() => toggleTooltip(null)}
                                        title="Clic para abrir el calendario"
                                    />
                                    {#if openTooltip === `${unitIndex}-${topicIndex}` && allowedWeekdays?.length}
                                        <div class="right-36 absolute bg-white bottom-full z-30 mb-2 w-60 rounded-lg border border-amber-300 bg-amber-50 p-2.5 text-xs font-medium text-amber-700 shadow-lg">
                                            Para esta materia
                                            {allowedSectionsPhrase()}
                                            das clases los días
                                            <b>{describeAllowedDays(allowedWeekdays)}.</b>
                                        </div>
                                    {/if}
                                </div>
                                <button type="button" class="hover:text-red text-gray-500 pt-2" title="Quitar tema" on:click={() => removeTopic(unitIndex, topicIndex)}><iconify-icon icon="mdi:close-circle-outline" width="22" height="22"></iconify-icon></button>
                            </div>
                        {/each}
                    </div>
                    <button type="button" class="mt-3 text-xs px-3 py-1.5 bg-gray-200 text-gray-700 rounded-md" on:click={() => addTopic(unitIndex)}>+ Agregar tema</button>
                </div>
            {/each}
        </div>
        <button type="button" class="mt-3 text-xs px-3 py-1.5 bg-gray-200 text-gray-700 rounded-md" on:click={addUnit}>+ Agregar unidad</button>
    </form>
    <button form="admin-plan-form" slot="btn_footer" type="submit" class="animated-button min-w-[200px]" disabled={$form.processing}>
        {#if $form.processing}Cargando...{:else}<iconify-icon icon="material-symbols:save-sharp" width="24" height="24" /> Crear{/if}
    </button>
</Modal>