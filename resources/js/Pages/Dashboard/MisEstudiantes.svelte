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

    $: if (data?.matrix?.plan?.id) rebuildEditable(data.matrix);

    let form = useForm({
        plan_id: data.selected_plan_id || "",
        grades: [],
    });

    function selectPlan(planId) {
        router.get("/dashboard/mis-estudiantes", { plan_id: planId }, {
            preserveState: true,
            replace: true,
        });
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

    function handleSave() {
        if (!data.matrix) return;

        const grades = [];
        data.matrix.students.forEach((s) => {
            data.matrix.items.forEach((item) => {
                const val = editable[`${s.id}_${item.id}`];
                grades.push({
                    plan_item_id: item.id,
                    student_id: s.id,
                    score: val === "" || val === null || val === undefined
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

{#if !data.plans?.length}
    <div class="bg-white border border-gray-200 rounded-lg p-8 text-center text-gray-400">
        Aún no tienes planes de evaluación. Crea un plan en "Planes de
        Evaluación" para poder calificar estudiantes.
    </div>
{:else}
    <div class="bg-white border border-gray-200 rounded-lg p-4 mb-4 flex flex-wrap items-center gap-4">
        <div class="flex items-center gap-2">
            <label class="text-sm font-semibold text-gray-600">
                Plan de evaluación
            </label>
            <select
                class="rounded-md border border-gray-300 px-3 py-2 text-sm min-w-[320px]"
                value={data.selected_plan_id || ""}
                on:change={(e) => selectPlan(e.target.value)}
            >
                {#each data.plans as plan}
                    <option value={plan.id}>
                        {plan.course_name} · {plan.section_name} · {plan.matter_name} · {plan.lapse_label} ({plan.status_label})
                    </option>
                {/each}
            </select>
        </div>

        {#if data.matrix}
            <div class="text-sm text-gray-600">
                <span class="font-semibold">{data.matrix.plan.name}</span>
                <span class="text-gray-400">
                    · {data.matrix.plan.school_lapse_label}
                </span>
            </div>
        {/if}
    </div>

    {#if data.matrix}
        {#if data.matrix.students.length === 0}
            <div class="bg-white border border-gray-200 rounded-lg p-8 text-center text-gray-400">
                No hay estudiantes en {data.matrix.plan.course_name} · Sección
                {data.matrix.plan.section_name}.
            </div>
        {:else}
            <div class="bg-white border border-gray-200 rounded-lg shadow overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-3 py-3 text-left sticky left-0 bg-gray-50">Estudiante</th>
                            {#each data.matrix.items as item}
                                <th class="px-3 py-3 text-left min-w-[150px]">
                                    <div class="font-semibold">{item.name}</div>
                                    <div class="text-xs text-gray-400 font-normal">
                                        {item.percentage}%
                                    </div>
                                </th>
                            {/each}
                            <th class="px-3 py-3 text-left bg-gray-50">
                                Definitiva ({data.matrix.plan.lapse_label})
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        {#each data.matrix.students as student}
                            {@const definitive = computeDefinitive(student)}
                            <tr class="border-t border-gray-100">
                                <td class="px-3 py-2 sticky left-0 bg-white">
                                    <p class="font-semibold text-gray-800 capitalize">
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
                                            bind:value={editable[`${student.id}_${item.id}`]}
                                        />
                                    </td>
                                {/each}
                                <td class="px-3 py-2 bg-gray-50">
                                    {#if definitive !== null}
                                        <span
                                            class="font-bold {definitive >= 10 ? 'text-green-600' : 'text-red'}"
                                        >
                                            {definitive}
                                        </span>
                                        <span
                                            class="ml-2 text-xs px-2 py-0.5 rounded font-bold {definitive >= 10 ? 'bg-green-100 text-green-700' : 'bg-red text-white'}"
                                        >
                                            {definitive >= 10 ? "Aprobada" : "Reprobada"}
                                        </span>
                                    {:else}
                                        <span class="text-xs px-2 py-0.5 rounded bg-yellow text-gray-800 font-bold">
                                            En curso
                                        </span>
                                    {/if}
                                </td>
                            </tr>
                        {/each}
                    </tbody>
                </table>
            </div>

            <div class="mt-4 flex justify-end">
                <button
                    on:click={handleSave}
                    class="animated-button flex items-center gap-2"
                    disabled={$form.processing}
                >
                    {#if $form.processing}
                        Guardando...
                    {:else}
                        <iconify-icon
                            icon="material-symbols:save-sharp"
                            width="24"
                            height="24"
                        />
                        <span>Guardar notas</span>
                    {/if}
                </button>
            </div>
        {/if}
    {:else}
        <div class="bg-white border border-gray-200 rounded-lg p-8 text-center text-gray-400">
            Selecciona un plan para cargar la matriz de notas.
        </div>
    {/if}
{/if}
