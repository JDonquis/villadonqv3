<script>
    import { router } from "@inertiajs/svelte";
    import Modal from "./Modal.svelte";
    import Input from "./Input.svelte";
    import { displayAlert } from "../stores/alertStore";

    export let plan = null;
    export let data = {};
    export let isAdmin = false;
    export let url = "";
    export let showModal = false;

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

    function currentMomentId(schoolLapse) {
        if (!schoolLapse?.lapses?.length) return "";
        const today = new Date().toISOString().slice(0, 10);
        const current =
            schoolLapse.lapses.find(
                (m) => today >= (m.start || "") && today <= (m.end || ""),
            ) || schoolLapse.lapses[0];
        return current?.id || "";
    }

    let schoolLapseId = "";
    let lapseId = "";
    let teacherId = "";
    let selectedSections = [];
    let saving = false;

    $: selectedSchoolLapse = data.school_lapses?.find(
        (l) => String(l.id) === String(schoolLapseId),
    );
    $: momentOptions = selectedSchoolLapse?.lapses || [];
    $: course = data.courses?.find(
        (c) => String(c.id) === String(plan?.course_id || ""),
    );
    $: courseSections = course?.sections || [];
    $: teacherOptions = isAdmin ? data.teachers || [] : [];

    function normalizeSections() {
        return Array.isArray(selectedSections)
            ? selectedSections.map(String).filter(Boolean)
            : [];
    }

    function init() {
        if (!plan) return;
        const active = schoolLapseForToday();
        schoolLapseId = active?.id || plan.school_lapse_id || "";
        lapseId = active ? currentMomentId(active) : plan.lapse_id || "";
        teacherId = plan?.teacher_id || "";
        selectedSections = plan?.section_id
            ? [String(plan.section_id)]
            : [];
    }

    $: if (showModal && plan) init();

    function onPeriodChange() {
        const period = data.school_lapses?.find(
            (l) => String(l.id) === String(schoolLapseId),
        );
        lapseId = currentMomentId(period);
    }

    function isAllSelected() {
        return normalizeSections().includes("all");
    }

    function toggleAll(checked) {
        selectedSections = checked ? ["all"] : [];
    }

    function toggleSection(id) {
        const chosen = normalizeSections().filter((v) => v !== "all");
        const value = String(id);
        selectedSections = chosen.includes(value)
            ? chosen.filter((v) => v !== value)
            : [...chosen, value];
    }

    function submit() {
        if (!plan) return;
        const sections = normalizeSections();
        if (!sections.length) {
            displayAlert({
                type: "error",
                message: "Debe seleccionar al menos una sección.",
            });
            return;
        }
        const payload = {
            source_id: plan.id,
            school_lapse_id: schoolLapseId,
            lapse_id: lapseId,
            section_id: sections,
        };
        if (isAdmin) {
            payload.teacher_id = teacherId || plan.teacher_id;
        }
        saving = true;
        router.post(url, payload, {
            preserveScroll: true,
            onSuccess: () => {
                saving = false;
                showModal = false;
            },
            onError: (errors) => {
                saving = false;
                displayAlert({
                    type: "error",
                    message:
                        errors.message ||
                        errors.section_id ||
                        errors.lapse_id ||
                        "Error al copiar el plan",
                });
            },
        });
    }
</script>

{#if plan}
    <Modal bind:showModal classes={"w-fit"}>
        <div class="pt-1 px-2 max-w-[760px]">
            <h3 class="text-lg font-bold text-color1 mb-1">
                Copiar plan de evaluación
            </h3>

            <div class="mb-4 rounded-md bg-gray-50 border border-gray-200 p-3 text-sm text-gray-700">
                <p class="font-semibold text-gray-800">
                    {plan.matter_name}
                    {#if plan.course_name}· {plan.course_name}{/if}
                    {#if plan.section_name}· {plan.section_name}{/if}
                </p>
                <p class="text-xs text-gray-500 mt-0.5">
                    Origen: {plan.school_lapse_label || "—"}
                    {#if plan.lapse_label} · {plan.lapse_label}{/if}
                </p>
                <p class="text-xs text-amber-700 mt-2 bg-amber-50 border border-amber-200 rounded px-2 py-1">
                    Se creará como borrador y las fechas de los temas quedarán vacías.
                </p>
            </div>

            <div class="grid grid-cols-12 gap-x-4 gap-y-3">
                {#if isAdmin}
                    <Input
                        type="select"
                        label={"Profesor"}
                        bind:value={teacherId}
                        classes={"col-span-6"}
                    >
                        <option value="">Seleccione...</option>
                        {#each teacherOptions as teacher}
                            <option value={teacher.id}>{teacher.name}</option>
                        {/each}
                    </Input>
                {/if}

                <Input
                    type="select"
                    label={"Periodo escolar destino"}
                    bind:value={schoolLapseId}
                    classes={"col-span-6"}
                    on:change={onPeriodChange}
                >
                    {#each data.school_lapses || [] as lapse}
                        <option value={String(lapse.id)}>{lapse.label}</option>
                    {/each}
                </Input>

                <Input
                    type="select"
                    label={"Momento destino"}
                    bind:value={lapseId}
                    classes={"col-span-6"}
                >
                    {#if momentOptions.length}
                        {#each momentOptions as moment}
                            <option value={String(moment.id)}>
                                {moment.label}
                            </option>
                        {/each}
                    {:else}
                        <option value="">Sin momentos</option>
                    {/if}
                </Input>

                <div class="col-span-12">
                    <!-- svelte-ignore a11y-label-has-associated-control -->
                    <label
                        class="form__label w-full text-xs md:text-sm font-semibold text-gray-700"
                    >
                        Secciones (se creará un plan por cada una) *
                    </label>
                    <div
                        class="form__field w-full px-3 py-2 text-sm min-h-[42px] bg-white"
                    >
                        {#if courseSections.length === 0}
                            <p class="text-xs text-gray-500">
                                El plan no tiene un año escolar asociado con secciones.
                            </p>
                        {/if}
                        <div
                            class="flex flex-wrap gap-3 max-h-[180px] overflow-y-auto"
                        >
                            {#each courseSections as section}
                                <label
                                    class="flex items-center gap-1 text-sm"
                                >
                                    <input
                                        type="checkbox"
                                        value={String(section.id)}
                                        checked={normalizeSections().includes(
                                            String(section.id),
                                        )}
                                        on:change={() =>
                                            toggleSection(section.id)}
                                    />
                                    <span>{section.name}</span>
                                </label>
                            {/each}
                        </div>
                        {#if courseSections.length > 0}
                            <label
                                class="flex items-center gap-1 text-sm font-semibold mt-2"
                            >
                                <input
                                    type="checkbox"
                                    checked={isAllSelected()}
                                    on:change={(e) =>
                                        toggleAll(
                                            e.currentTarget.checked,
                                        )}
                                />
                                <span>Todas las secciones</span>
                            </label>
                        {/if}
                    </div>
                </div>
            </div>
        </div>

        <button
            slot="btn_footer"
            class="animated-button min-w-[190px] flex gap-2"
            disabled={saving}
            on:click={submit}
        >
            {#if saving}
                Cargando...
            {:else}
                <iconify-icon
                    icon="mdi:content-copy"
                    width="20"
                    height="20"
                />
                Copiar plan
            {/if}
        </button>
    </Modal>
{/if}
