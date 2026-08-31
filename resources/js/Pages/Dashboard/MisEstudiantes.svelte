<script>
    import { useForm, router } from "@inertiajs/svelte";
    import Alert from "../../components/Alert.svelte";
    import { displayAlert } from "../../stores/alertStore";

    export let data = [];

    let editable = {};

    function rebuildEditable(matrix) {
        editable = {};
        matrix?.students?.forEach((s) => {
            matrix.items.forEach((item) => {
                const key = `${s.id}_${item.id}`;
                const val = s.scores[item.id];
                editable[key] = val != null ? String(val) : "";
            });
        });
    }

    let sortState = {
        key: "student",
        direction: "asc",
    };
    let definitiveByStudent = {};

    function toggleSort(key) {
        if (sortState.key === key) {
            sortState.direction =
                sortState.direction === "asc" ? "desc" : "asc";
            return;
        }

        sortState.key = key;
        sortState.direction = "asc";
    }

    function getSortIndicator(key) {
        if (sortState.key !== key) return "↕";
        return sortState.direction === "asc" ? "▲" : "▼";
    }

    $: if (data?.matrix?.plan?.id) rebuildEditable(data.matrix);
    $: if (data?.matrix) {
        definitiveByStudent = {};
        for (const student of data.matrix.students || []) {
            definitiveByStudent[student.id] = computeDefinitive(student);
        }
    }
    $: sortedStudents = [...(data.matrix?.students || [])].sort((a, b) => {
        const direction = sortState.direction === "asc" ? 1 : -1;

        if (sortState.key === "student") {
            const lastNameCompare = (a.last_name || "").localeCompare(
                b.last_name || "",
                "es",
                { sensitivity: "base" },
            );
            if (lastNameCompare !== 0) return lastNameCompare * direction;
            return (a.name || "").localeCompare(b.name || "", "es", {
                sensitivity: "base",
            }) * direction;
        }

        if (sortState.key === "definitive") {
            const aVal = definitiveByStudent[a.id];
            const bVal = definitiveByStudent[b.id];
            if (aVal === null && bVal === null) return 0;
            if (aVal === null) return 1 * direction;
            if (bVal === null) return -1 * direction;
            return (aVal - bVal) * direction;
        }

        const itemId = Number(sortState.key.replace("item_", ""));
        const aVal = a.scores?.[itemId] ?? null;
        const bVal = b.scores?.[itemId] ?? null;

        if (aVal === null && bVal === null) return 0;
        if (aVal === null) return 1 * direction;
        if (bVal === null) return -1 * direction;

        return (Number(aVal) - Number(bVal)) * direction;
    });

    let form = useForm({
        plan_id: data.selected_plan_id || "",
        grades: [],
    });

    function getActiveMomentId(schoolLapse) {
        if (!schoolLapse?.lapses?.length) return "";

        const today = new Date().toISOString().slice(0, 10);
        const active = schoolLapse.lapses.find(
            (lap) => today >= (lap.start || "") && today <= (lap.end || ""),
        );

        return String(
            active?.id ??
                schoolLapse.lapses[schoolLapse.lapses.length - 1]?.id ??
                "",
        );
    }

    $: selectedSchoolLapse =
        (data.school_lapses || []).find(
            (l) => String(l.id) === String(data.school_lapse_id),
        ) ||
        (data.school_lapses || [])[0] ||
        null;
    $: momentOptions = selectedSchoolLapse?.lapses || [];
    $: selectedLapseId = String(
        data.lapse_id || getActiveMomentId(selectedSchoolLapse) || "",
    );

    function selectSchoolLapse(schoolLapseId) {
        const nextSchool = (data.school_lapses || []).find(
            (l) => String(l.id) === String(schoolLapseId),
        );
        const nextMoment = getActiveMomentId(nextSchool);

        router.get(
            "/dashboard/mis-estudiantes",
            {
                school_lapse_id: schoolLapseId,
                lapse_id: nextMoment,
                plan_id: "",
            },
            {
                preserveState: true,
                replace: true,
            },
        );
    }

    function selectMoment(momentId) {
        router.get(
            "/dashboard/mis-estudiantes",
            {
                school_lapse_id: data.school_lapse_id || "",
                lapse_id: momentId,
                plan_id: "",
            },
            {
                preserveState: true,
                replace: true,
            },
        );
    }

    function selectPlan(planId) {
        router.get(
            "/dashboard/mis-estudiantes",
            {
                school_lapse_id: data.school_lapse_id || "",
                lapse_id: data.lapse_id || selectedLapseId || "",
                plan_id: planId,
            },
            {
                preserveState: true,
                replace: true,
            },
        );
    }

    function formatTooltipDate(date) {
        if (!date) return "—";

        const parsed = new Date(`${date}T00:00:00`);
        if (Number.isNaN(parsed.getTime())) return "—";

        return parsed.toLocaleDateString("es-VE", {
            weekday: "short",
            day: "numeric",
            month: "short",
        });
    }

    function getUnitMetaForItem(item) {
        const unit = (data.matrix?.units || []).find((entry) =>
            (entry.topics || []).some(
                (topic) =>
                    String(topic.id) === `topic_${item.id}` ||
                    (topic.name === item.name &&
                        Number(topic.percentage) === Number(item.percentage)),
            ),
        );

        return {
            unit_number: unit?.unit_number ?? item.unit_number ?? 1,
            unit_name: unit?.name ?? item.unit_name ?? "Sin unidad",
            assessment_type:
                unit?.topics?.find(
                    (topic) =>
                        String(topic.id) === `topic_${item.id}` ||
                        (topic.name === item.name &&
                            Number(topic.percentage) ===
                                Number(item.percentage)),
                )?.assessment_type ??
                item.assessment_type ??
                "—",
            scheduled_date:
                unit?.topics?.find(
                    (topic) =>
                        String(topic.id) === `topic_${item.id}` ||
                        (topic.name === item.name &&
                            Number(topic.percentage) ===
                                Number(item.percentage)),
                )?.scheduled_date ??
                item.scheduled_date ??
                null,
        };
    }

    function computeDefinitive(student) {
        if (!data.matrix) return null;

        let total = 0;
        for (const item of data.matrix.items) {
            const val = editable[`${student.id}_${item.id}`];
            if (val === "" || val === null || val === undefined) return null;
            const num = parseFloat(val);
            if (isNaN(num)) return null;
            total += num * (item.percentage / 100);
        }
        return Math.round(total * 100) / 100;
    }

    function getDefinitiveMedal(student) {
        if (!data.matrix) return null;

        const studentScore = definitiveByStudent[student.id];
        if (studentScore === null || studentScore < 10) return null;

        const scores = data.matrix.students
            .map((entry) => ({
                id: entry.id,
                score: definitiveByStudent[entry.id],
            }))
            .filter((entry) => entry.score !== null && entry.score >= 10)
            .sort((a, b) => b.score - a.score);

        if (!scores.length) return null;

        const uniqueScores = [...new Set(scores.map((entry) => entry.score))];
        const medalByScore = new Map();

        uniqueScores.forEach((score, index) => {
            if (index === 0) medalByScore.set(score, "gold");
            else if (index === 1) medalByScore.set(score, "silver");
            else if (index === 2) medalByScore.set(score, "bronze");
        });

        return medalByScore.get(studentScore) ?? null;
    }

    function getMedalIcon(medal) {
        if (medal === "gold") return { icon: "fluent-emoji-flat:1st-place-medal", class: "text-2xl" };
        if (medal === "silver") return { icon: "fluent-emoji-flat:2nd-place-medal", class: "text-xl" };
        if (medal === "bronze") return { icon: "fluent-emoji-flat:3rd-place-medal", class: "text-xl" };
        return "";
    }

    function handleSave() {
        if (!data.matrix) return;

        const grades = [];
        data.matrix.students.forEach((s) => {
            data.matrix.items.forEach((item) => {
                const val = editable[`${s.id}_${item.id}`];
                grades.push({
                    plan_item_id: item.id,
                    student_id: s.id,
                    score:
                        val === "" || val === null || val === undefined
                            ? null
                            : parseFloat(val),
                });
            });
        });

        $form.clearErrors();
        $form.plan_id = data.matrix.plan.id;
        $form.grades = grades;
        $form.post("/dashboard/mis-estudiantes/guardar-notas", {
            preserveScroll: true,
            onSuccess: () => {
                displayAlert({
                    type: "success",
                    message: "Notas guardadas correctamente",
                });
            },
            onError: (errors) => {
                displayAlert({
                    type: "error",
                    message: errors.message || "Error al guardar las notas",
                });
            },
        });
    }
</script>

<svelte:head>
    <title>Mis Estudiantes</title>
</svelte:head>

<Alert />

<div class="flex justify-between items-center mb-4 flex-wrap gap-2">
    <h2 class="text-2xl font-bold text-color1">Mis Estudiantes</h2>
</div>

<div
    class="bg-white border border-gray-200 rounded-lg p-4 mb-4 flex flex-wrap items-center gap-5 md:gap-6"
>
    <div class="flex flex-col md:flex-row md:items-center gap-2">
        <label class="text-sm font-semibold text-gray-600">
            Período escolar
        </label>
        <select
            class="rounded-md border border-gray-300 px-3 py-2 text-sm"
            value={String(data.school_lapse_id || "")}
            on:change={(e) => selectSchoolLapse(e.target.value)}
        >
            {#each data.school_lapses || [] as lapse}
                <option value={String(lapse.id)}>{lapse.label}</option>
            {/each}
        </select>
    </div>

    <div class="flex flex-col md:flex-row items-center gap-2">
        <label class="text-sm font-semibold text-gray-600">
            Momento escolar
        </label>
        <select
            class="rounded-md border border-gray-300 px-3 py-2 text-sm"
            value={selectedLapseId}
            on:change={(e) => selectMoment(e.target.value)}
        >
            {#each momentOptions as lap}
                <option value={String(lap.id)}>{lap.label}</option>
            {/each}
        </select>
    </div>

    <div class="flex-col md:flex-row items-center gap-2">
        <label class="text-sm font-semibold text-gray-600">
            Plan de evaluación
        </label>
        <select
            class="rounded-md border border-gray-300 px-3 py-2 text-sm min-w-[260px]"
            value={data.selected_plan_id || ""}
            on:change={(e) => selectPlan(e.target.value)}
        >
            {#each data.plans as plan}
                <option value={plan.id}>
                    {plan.matter_name} · {plan.course_name} · {plan.section_name}
                </option>
            {/each}
        </select>
    </div>
</div>
{#if !data.plans?.length}
    <div
        class="bg-white border border-gray-200 rounded-lg p-8 text-center text-gray-400"
    >
        Aún no tienes planes de evaluación. Crea un plan en "Planes de
        Evaluación" para poder calificar estudiantes.
    </div>
{/if}

{#if data.matrix}
    {#if data.matrix.students.length === 0}
        <div
            class="bg-white border border-gray-200 rounded-lg p-8 text-center text-gray-400"
        >
            No hay estudiantes en {data.matrix.plan.course_name} · Sección
            {data.matrix.plan.section_name}.
        </div>
    {:else}
        <div
            class="bg-white border border-gray-200 rounded-lg shadow overflow-x-auto"
        >
            <table class="w-full text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-3 py-3 text-left sticky left-0 bg-gray-50">
                            <button
                                type="button"
                                class="flex items-center gap-1 font-semibold text-left"
                                on:click={() => toggleSort("student")}
                            >
                                <span>Estudiante</span>
                                <span class="text-[10px] text-gray-500">
                                    {getSortIndicator("student")}
                                </span>
                            </button>
                        </th>
                        {#each data.matrix.items as item}
                            {@const meta = getUnitMetaForItem(item)}
                            <th
                                class="px-3 py-3 text-left min-w-[150px] relative group"
                                title=""
                            >
                                <button
                                    type="button"
                                    class="flex w-full items-center justify-between gap-2 text-left"
                                    on:click={() => toggleSort(`item_${item.id}`)}
                                >
                                    <span>
                                        <span class="font-semibold block">{item.name}</span>
                                        <span class="text-xs text-gray-400 font-normal">
                                            {item.percentage}%
                                        </span>
                                    </span>
                                    <span class="text-[10px] text-gray-500">
                                        {getSortIndicator(`item_${item.id}`)}
                                    </span>
                                </button>
                                <div
                                    class="pointer-events-none absolute left-1/2 top-12 z-20 mt-2 w-64 -translate-x-1/2 rounded-lg border border-gray-200 bg-white p-2 text-left text-[11px] text-gray-700 opacity-0 shadow-lg transition-opacity duration-150 group-hover:opacity-100"
                                >
                                    <div class="font-semibold text-gray-800">
                                        Unidad {meta.unit_number}: "{meta.unit_name}"
                                    </div>
                                    <div class="mt-1">
                                        Tipo: {meta.assessment_type}
                                    </div>
                                    <div class="mt-1">
                                        Fecha: {formatTooltipDate(
                                            meta.scheduled_date,
                                        )}
                                    </div>
                                </div>
                            </th>
                        {/each}
                        <th class="px-3 py-3 text-left bg-gray-50">
                            <button
                                type="button"
                                class="flex items-center gap-1 font-semibold text-left"
                                on:click={() => toggleSort("definitive")}
                            >
                                <span>Definitiva ({data.matrix.plan.lapse_label})</span>
                                <span class="text-[10px] text-gray-500">
                                    {getSortIndicator("definitive")}
                                </span>
                            </button>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    {#each sortedStudents as student}
                        {@const definitive = definitiveByStudent[student.id]}
                        <tr class="border-t border-gray-100">
                            <td class="px-3 py-2 sticky left-0 bg-white">
                                <p
                                    class="font-semibold text-gray-800 capitalize"
                                >
                                    {student.last_name}, {student.name}
                                </p>
                                <p class="text-xs text-gray-400">
                                    C.I {student.ci}
                                </p>
                            </td>
                            {#each data.matrix.items as item}
                                <td class="px-3 py-2">
                                    <input
                                        type="number"
                                        min="0"
                                        max="20"
                                        step="0.5"
                                        class="w-20 rounded-md border border-gray-300 px-2 py-1.5 text-sm"
                                        inputmode="decimal"
                                        on:wheel|preventDefault
                                        on:focus={(e) => e.currentTarget.select()}
                                        on:keydown={(e) => {
                                            if (
                                                [
                                                    "ArrowUp",
                                                    "ArrowDown",
                                                    "PageUp",
                                                    "PageDown",
                                                    "Home",
                                                    "End",
                                                ].includes(e.key)
                                            ) {
                                                e.stopPropagation();
                                            }
                                        }}
                                        bind:value={
                                            editable[`${student.id}_${item.id}`]
                                        }
                                    />
                                </td>
                            {/each}
                            <td class="px-3 py-2 bg-gray-50">
                                {#if definitive !== null}
                                    {@const medal = getDefinitiveMedal(student)}
                                    <div class="flex items-center gap-2">
                                        {#if medal}
                                            <span
                                                class="text-lg leading-none"
                                                title={
                                                    medal === "gold"
                                                        ? "Primero"
                                                        : medal === "silver"
                                                          ? "Segundo"
                                                          : "Tercero"
                                                }
                                            >
                                                <iconify-icon
                                                    icon={getMedalIcon(medal).icon}
                                                    class={getMedalIcon(medal).class}></iconify-icon>
                                            </span>
                                        {/if}
                                        <span
                                            class="font-bold {definitive >= 10
                                                ? 'text-green-600'
                                                : 'text-red'}"
                                        >
                                            {definitive}
                                        </span>
                                        <span
                                            class="ml-1 text-xs px-2 py-0.5 rounded font-bold {definitive >=
                                            10
                                                ? 'bg-green-100 text-green-700'
                                                : 'bg-red text-white'}"
                                        >
                                            {definitive >= 10
                                                ? ""
                                                : "R"}
                                        </span>
                                    </div>
                                {:else}
                                    <span
                                        class="text-xs px-2 py-0.5 rounded bg-yellow text-gray-800 font-bold"
                                    >
                                        En curso
                                    </span>
                                {/if}
                            </td>
                        </tr>
                    {/each}
                </tbody>
            </table>
        </div>

        <div class="mt-4 fixed bottom-8 right-10 flex justify-end max-w-[300px] ml-auto">
            <button
                on:click={handleSave}
                class="max-w-[300px] animated-button flex items-center gap-10"
                disabled={$form.processing}
            >
                {#if $form.processing}
                    Guardando...
                {:else}
                    <span class="text">Guardar notas</span>
                    <span class="circle"></span>
                {/if}
            </button>
        </div>
    {/if}
{:else}
    <div
        class="bg-white border border-gray-200 rounded-lg p-8 text-center text-gray-400"
    >
        Selecciona un plan para cargar la matriz de notas.
    </div>
{/if}
