<script>
    export let plan = {};
    export let showTeacher = true;
    // defensive defaults
    const units = Array.isArray(plan.units) ? plan.units : [];
    console.log("PlanUnitsView plan:", showTeacher, plan);
</script>

<div class="px-5 py-2 md:min-w-[760px]">
    <p class="text-sm text-gray-500">
        {plan.school_lapse_label}
        <!-- prosefor -->
    </p>
    {#if showTeacher}
        <b class="text-sm text-gray-500"
            ><iconify-icon
                class="text-lg text-gray-500 mr-1"
                icon="mdi:account-tie"
            ></iconify-icon>{plan.teacher_name}</b
        >
    {/if}

    <h3 class="text-xl font-bold text-color1 mb-1">
        <div>
            <b class="text-gray-700">
                {plan.matter_name} ·
            </b>
            {plan.course_name || "—"}
            {#if plan.section_name}· {plan.section_name}{/if}

            · {plan.lapse_label}
        </div>
        {#if plan.description}
            <p class="text-xs text-gray-500 max-w-[200px] truncate">
                {plan.description}
            </p>
        {/if}
    </h3>

    <div class="space-y-4">
        {#each units as unit, unitIndex}
            <div class="rounded-lg bg-gray-50 p-3 md:p-4">
                <div class="text-sm font-bold text-color1 mb-2">
                    Unidad {unit.unit_number || unitIndex + 1}{#if unit.name}: {unit.name}{/if}
                </div>
                {#if Array.isArray(unit.topics) && unit.topics.length}
                    <table class="w-full text-sm">
                        <thead>
                            <tr
                                class="text-left text-xs font-semibold text-gray-500"
                            >
                                <th class="px-2 py-1">Tema</th>
                                <th class="px-2 py-1">Tipo de prueba</th>
                                <th class="px-2 py-1">Descripción</th>
                                <th class="px-2 py-1">%</th>
                                <th class="px-2 py-1">Pts</th>
                                <th class="px-2 py-1">Fecha</th>
                            </tr>
                        </thead>
                        <tbody>
                            {#each unit.topics as topic, topicIndex}
                                <tr class="border-t border-gray-200">
                                    <td class="px-2 py-1.5 align-top">
                                        <span
                                            class="font-semibold text-gray-500 mr-1"
                                            >{topicIndex + 1}.</span
                                        >
                                        {topic.name || "—"}
                                    </td>
                                    <td class="px-2 py-1.5"
                                        >{topic.assessment_type || "—"}</td
                                    >
                                    <td
                                        class="px-2 py-1.5 text-gray-500 max-w-[800px]"
                                        >{topic.description || "—"}</td
                                    >
                                    <td class="px-2 py-1.5"
                                        >{topic.percentage || 0}%</td
                                    >
                                    <td class="px-2 py-1.5"
                                        >{topic.points != null
                                            ? topic.points
                                            : "—"}</td
                                    >
                                    <td class="px-2 py-1.5"
                                        >{topic.scheduled_date || "—"}</td
                                    >
                                </tr>
                            {/each}
                        </tbody>
                    </table>
                {:else}
                    <p class="text-sm text-gray-500">Sin temas.</p>
                {/if}
            </div>
        {/each}
    </div>

    <div class="text-sm font-semibold text-gray-700 mt-3 text-right">
        Total: {plan.items_total}%
    </div>

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
