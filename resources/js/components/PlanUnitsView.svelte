<script>
    export let plan = {};
    export let showTeacher = true;
    $: units = Array.isArray(plan.units) ? plan.units : [];
    $: evalPct = Number(plan.items_total || 0);
    $: rasgosPct = Number(plan.rasgos_points ? plan.rasgos_points * 5 : 0);
    $: totalPct =
        plan.total_percentage != null
            ? Number(plan.total_percentage)
            : evalPct + rasgosPct;
    $: isComplete = totalPct === 100;
</script>

<div class="w-full max-w-5xl mx-auto space-y-5 p-2 sm:p-5">
    <!-- 1. ENCABEZADO Y METADATOS PRINCIPALES -->
    <div
        class="bg-white rounded-2xl border border-grayBlue/40 p-5 md:p-6 shadow-sm"
    >
        <!-- Breadcrumb / Píldora superior -->
        <div class="flex flex-wrap items-center justify-between gap-3 mb-3">
            <div class="flex items-center gap-2">
                <span
                    class="text-xs font-semibold px-2.5 py-1 rounded-full bg-color2/10 text-color2"
                >
                    {plan.school_lapse_label || "Período Escolar"}
                </span>
                <span class="text-xs font-medium text-gray-400"
                    >· Planificación Curricular</span
                >
                <span class="text-xs font-medium text-gray-400"
                    >· Modo Solo Lectura</span
                >
            </div>
            <!-- Badge de estado -->
            {#if plan.status === "rejected"}
                <span
                    class="inline-flex items-center gap-1.5 text-xs font-bold px-3 py-1 rounded-full bg-red/10 text-red border border-red/30"
                >
                    <span class="w-2 h-2 rounded-full bg-red"></span> Rechazado
                </span>
            {:else if plan.status === "approved"}
                <span
                    class="inline-flex items-center gap-1.5 text-xs font-bold px-3 py-1 rounded-full bg-green/20 text-color1 border border-green/40"
                >
                    <span class="w-2 h-2 rounded-full bg-green"></span> Aprobado
                    & Activo
                </span>
            {:else}
                <span
                    class="inline-flex items-center gap-1.5 text-xs font-bold px-3 py-1 rounded-full bg-yellow/20 text-gray-800 border border-yellow/40"
                >
                    <span class="w-2 h-2 rounded-full bg-yellow"></span> En Elaboración
                </span>
            {/if}
        </div>
        <!-- Título de Asignatura, Año y Momento -->
        <h3 class="text-xl md:text-2xl font-black text-color1 tracking-tight">
            <span class="text-color2">{plan.matter_name || "Materia"}</span>
            <span class="text-gray-300 mx-1">·</span>
            <span>{plan.course_name || "—"}</span>
            {#if plan.section_name}
                <span class="text-gray-300 mx-1">·</span>
                <span class="text-color3">Sección {plan.section_name}</span>
            {/if} <span class="text-gray-300 mx-1">·</span>
            <span class="font-bold text-gray-700">{plan.lapse_label || ""}</span
            >
        </h3>
        <!-- Descripción / Objetivos -->
        {#if plan.description}
            <p
                class="text-xs md:text-sm text-gray-600 mt-2.5 max-w-4xl leading-relaxed"
            >
                <strong class="text-color1 font-semibold"
                    >Descripción / Objetivos:</strong
                >
                {plan.description}
            </p>
        {/if}
    </div>
    <!-- 2. TARJETA DE BALANCE Y PONDERACIÓN INSTITUCIONAL -->
    
    <!-- 3. UNIDADES Y DATA TABLE MODERNA -->
    <div class="space-y-4">
        {#each units as unit, unitIndex}
            <div
                class="bg-white rounded-2xl border border-grayBlue/40 shadow-sm overflow-hidden transition-shadow hover:shadow-md"
            >
                <!-- Encabezado de la Unidad -->
                <div
                    class="bg-gray-50/70 px-5 py-3.5 border-b border-grayBlue/30 flex items-center justify-between"
                >
                    <div class="flex items-center gap-2.5">
                        <span
                            class="bg-color2 text-white text-xs font-bold px-2.5 py-1 rounded-lg"
                        >
                            U{unit.unit_number || unitIndex + 1}
                        </span>
                        <h4 class="font-bold text-color1 text-sm md:text-base">
                            {unit.name
                                ? unit.name
                                : `Unidad ${unit.unit_number || unitIndex + 1}`}
                        </h4>
                    </div>
                    <!-- Subtotal de Unidad si existe en topics -->
                    {#if Array.isArray(unit.topics) && unit.topics.length}
                        {@const unitSubtotal = unit.topics.reduce(
                            (acc, t) => acc + (Number(t.percentage) || 0),
                            0,
                        )}
                        <span
                            class="text-xs font-semibold px-2.5 py-1 rounded-md bg-white border border-grayBlue/50 text-gray-700"
                        >
                            Subtotal unidad: <b class="text-color2"
                                >{unitSubtotal}%</b
                            >
                            · <b>{Math.round(unitSubtotal * 0.2)} Pts</b>
                        </span>
                    {/if}
                </div>
                <!-- Tabla de Temas y Evaluaciones -->
                {#if Array.isArray(unit.topics) && unit.topics.length}
                    <div class="overflow-x-auto">
                        <table
                            class="w-full text-xs md:text-sm text-left border-collapse"
                        >
                            <thead>
                                <tr
                                    class="border-b border-grayBlue/20 text-[11px] font-bold uppercase tracking-wider text-gray-400 bg-white"
                                >
                                    <th class="px-4 py-3 w-8 text-center">#</th>
                                    <th
                                        class="px-4 py-3 font-bold text-gray-500"
                                        >Tema / Contenido</th
                                    >
                                    <th
                                        class="px-4 py-3 font-bold text-gray-500"
                                        >Tipo de Prueba</th
                                    >
                                    <th
                                        class="px-4 py-3 font-bold text-gray-500"
                                        >Criterios / Descripción</th
                                    >
                                    <th
                                        class="px-4 py-3 font-bold text-gray-500 text-center"
                                        >% Ponderación</th
                                    >
                                    <th
                                        class="px-4 py-3 font-bold text-gray-500 text-center"
                                        >Puntos</th
                                    >
                                    <th
                                        class="px-4 py-3 font-bold text-gray-500 text-right"
                                        >Fecha Pautada</th
                                    >
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-grayBlue/15">
                                {#each unit.topics as topic, topicIndex}
                                    <tr
                                        class="hover:bg-slate-50/70 transition-colors"
                                    >
                                        <!-- Índice -->
                                        <td
                                            class="px-4 py-3 text-center text-xs font-semibold text-gray-400"
                                        >
                                            {topicIndex + 1}
                                        </td>
                                        <!-- Tema -->
                                        <td
                                            class="px-4 py-3 font-bold text-color1 align-top"
                                        >
                                            {topic.name || "—"}
                                        </td>
                                        <!-- Tipo de prueba (Badge) -->
                                        <td
                                            class="px-4 py-3 align-top whitespace-nowrap"
                                        >
                                            <span
                                                class="inline-block px-2.5 py-1 rounded-lg text-xs font-semibold bg-color4/15 text-color2 border border-color4/30"
                                            >
                                                {topic.assessment_type ||
                                                    "No especificado"}
                                            </span>
                                        </td>
                                        <!-- Descripción -->
                                        <td
                                            class="px-4 py-3 text-gray-600 align-top max-w-sm text-xs leading-relaxed"
                                        >
                                            {topic.description || "—"}
                                        </td>
                                        <!-- Porcentaje -->
                                        <td
                                            class="px-4 py-3 text-center font-bold text-color1 align-top whitespace-nowrap"
                                        >
                                            {topic.percentage || 0}%
                                        </td>
                                        <!-- Puntos -->
                                        <td
                                            class="px-4 py-3 text-center font-bold text-color2 align-top whitespace-nowrap"
                                        >
                                            {topic.points != null
                                                ? `${topic.points} Pts`
                                                : "—"}
                                        </td>
                                        <!-- Fecha -->
                                        <td
                                            class="px-4 py-3 text-right text-gray-600 align-top whitespace-nowrap font-medium text-xs"
                                        >
                                            {#if topic.scheduled_date}
                                                <span
                                                    class="inline-flex items-center gap-1"
                                                >
                                                    <iconify-icon
                                                        icon="mdi:calendar-outline"
                                                        class="text-color3"
                                                    ></iconify-icon>
                                                    {topic.scheduled_date}
                                                </span>
                                            {:else}
                                                <span class="text-gray-400"
                                                    >—</span
                                                >
                                            {/if}
                                        </td>
                                    </tr>
                                {/each}
                            </tbody>
                        </table>
                    </div>
                {:else}
                    <div class="p-6 text-center text-xs text-gray-400">
                        Esta unidad aún no contiene temas ni evaluaciones
                        asignadas.
                    </div>
                {/if}
            </div>
        {/each}
    </div>
    <!-- 4. FOOTER RESUMEN GLOBAL -->
    <div
        class="bg-white rounded-2xl border border-grayBlue/40 p-4 shadow-sm flex flex-wrap items-center justify-between gap-3 text-xs md:text-sm"
    >
        <div class="flex items-center gap-2 text-gray-600 font-medium">
            <span class="font-bold text-color1">Total General:</span>
            <span
                class="bg-gray-100 px-2.5 py-1 rounded-md text-color1 font-semibold"
                >{units.length} Unidades</span
            >
            <span>•</span>
            <!-- total de evaluaciones -->
            <span
                class="bg-gray-100 px-2.5 py-1 rounded-md text-color1 font-semibold"
            >
                {units.reduce(
                    (count, unit) => count + (Array.isArray(unit.topics) ? unit.topics.length : 0),
                    0,
                )} Evaluaciones
            </span>
           
        </div>
        <div
        class="bg-white rounded-2xl border border-grayBlue/40 p-5 shadow-sm grid grid-cols-1 md:grid-cols-12 gap-5 items-center"
    >
        <!-- Progreso y Ponderación (Columna izquierda) -->
        <div
            class="md:col-span-7 md:border-r md:border-grayBlue/30 md:pr-6 space-y-3"
        >
            <div class="flex items-center justify-between">
                <span
                    class="text-xs font-bold uppercase tracking-wider text-color1"
                >
                    Balance y Distribución de Ponderación
                </span>
            </div>
            <!-- Barra de Progreso Bicromática (color2 + green) -->
            <div
                class="w-full bg-gray-100 h-2.5 rounded-full overflow-hidden flex"
            >
                <div
                    class="bg-color2 transition-all duration-300"
                    style="width: {Math.min(evalPct, 100)}%"
                    title="Evaluaciones: {evalPct}%"
                ></div>
                <div
                    class="bg-emerald-500 transition-all duration-300"
                    style="width: {Math.min(rasgosPct, 100 - evalPct)}%"
                    title="Rasgos: {rasgosPct}%"
                ></div>
            </div>
            <!-- Leyenda de colores -->
            <div
                class="flex flex-wrap items-center gap-4 text-xs font-medium text-gray-600"
            ></div>
        </div>
        <!-- Metadatos de Responsable / Docente (Columna derecha) -->
        <div
            class="md:col-span-5 flex flex-col justify-center space-y-2 text-xs"
        >
            {#if showTeacher}
                <div class="flex items-center justify-between">
                    <span class="text-gray-400 font-medium"
                        >Docente Responsable:</span
                    >
                    <span
                        class="font-bold text-color1 flex items-center gap-1.5"
                    >
                        <span
                            class="w-5 h-5 rounded-full bg-color4/20 text-color2 text-[10px] font-black flex items-center justify-center"
                        >
                            {(plan.teacher_name || "D").charAt(0)}
                        </span>
                        {plan.teacher_name || "No asignado"}
                    </span>
                </div>
            {/if}
            <span class="flex items-center gap-1.5">
                <span class="w-2.5 h-2.5 rounded-sm bg-color2 inline-block"
                ></span>
                Evaluaciones Teórico-Prácticas:
                <b class="text-color1"
                    >{evalPct}% ({Math.round(evalPct * 0.2)} Pts)</b
                >
            </span>
            <span class="flex items-center gap-1.5">
                <span class="w-2.5 h-2.5 rounded-sm bg-green inline-block"
                ></span>
                Rasgos Personales / Conducta:
                <b class="text-color1"
                    >{rasgosPct}% ({plan.rasgos_points || 0} Pts)</b
                >
            </span>
        </div>
    </div>
    </div>
    <!-- 5. NOTA DE RECHAZO (SI APLICA) -->
    {#if plan.status === "rejected" && plan.admin_note}
        <div
            class="bg-red/10 border border-red/30 text-red px-5 py-3.5 rounded-2xl text-xs md:text-sm flex items-start gap-3 shadow-sm"
        >
            <iconify-icon
                icon="mdi:alert-circle-outline"
                class="text-lg text-red shrink-0 mt-0.5"
            ></iconify-icon>
            <div>
                <strong class="font-bold block"
                    >Motivo del rechazo de la coordinación:</strong
                >
                <p class="mt-0.5 text-redLight">{plan.admin_note}</p>
            </div>
        </div>
    {/if}
</div>
