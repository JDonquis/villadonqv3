<script>
    import KpiCard from "../../components/KpiCard.svelte";
    import AcademicEvolutionChart from "../../components/charts/AcademicEvolutionChart.svelte";

    export let data = { children: [], announcements: [], events: [], period: null };

    function formatCurrency(value) {
        return "$" + Number(value || 0).toLocaleString(undefined, {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2,
        });
    }

    function formatDate(value) {
        if (!value) return "—";
        const [y, m, d] = value.split("-");
        return `${d}/${m}/${y}`;
    }

    const ATTENDANCE_COLORS = {
        high: "text-green-600",
        mid: "text-amber-600",
        low: "text-red-600",
    };

    function attendanceColor(rate) {
        if (rate === null || rate === undefined) return "text-gray-400";
        if (rate >= 85) return ATTENDANCE_COLORS.high;
        if (rate >= 70) return ATTENDANCE_COLORS.mid;
        return ATTENDANCE_COLORS.low;
    }

    const EVENT_LABELS = {
        general: "General",
        exam: "Evaluación",
        meeting: "Reunión",
        holiday: "Feriado",
    };
</script>

<svelte:head>
    <title>Inicio</title>
</svelte:head>

<div class="flex flex-wrap items-center justify-between gap-3 mb-5">
    <div>
        <h2 class="text-xl md:text-2xl font-bold text-color1">Resumen de mis representados</h2>
        {#if data.period}
            <p class="text-sm text-gray-500">Período escolar {data.period.label}</p>
        {/if}
    </div>
</div>

{#if !data.children?.length}
    <div class="neumorphism rounded-xl p-8 text-center text-gray-500">
        No tienes representados activos registrados.
    </div>
{/if}

{#each data.children as child (child.id)}
    <section class="mb-8">
        <div class="flex flex-wrap items-center gap-3 mb-3">
            <div
                class="w-10 h-10 rounded-xl bg-color2/80 text-white font-bold flex items-center justify-center"
            >
                {child.name?.charAt(0) ?? "?"}{child.last_name?.charAt(0) ?? ""}
            </div>
            <div>
                <h3 class="text-lg font-bold text-gray-800">
                    {child.name} {child.last_name}
                </h3>
                <p class="text-xs text-gray-500">
                    {child.ci ? `CI ${child.ci} · ` : ""}{child.course ?? "Sin curso"} {child.section ?? ""}
                </p>
            </div>
        </div>

        <div class={`grid grid-cols-1 ${data.can_see_money ? "sm:grid-cols-3" : "sm:grid-cols-2"} gap-4 mb-4`}>
            {#if data.can_see_money}
                <KpiCard
                    label="Estado de Cuenta"
                    value={child.account.is_up_to_date ? "Al día" : formatCurrency(child.account.debt)}
                    icon={child.account.is_up_to_date ? "mdi:check-circle" : "mdi:alert-circle"}
                    color={child.account.is_up_to_date ? "green" : "red"}
                    hint={child.account.next_due
                        ? `Próximo: ${child.account.next_due.label} · ${formatCurrency(child.account.next_due.amount)} · vence ${formatDate(child.account.next_due.date)}`
                        : "Sin cuotas pendientes"}
                />
            {/if}
            <KpiCard
                label="Promedio Académico"
                value={child.academic.average !== null ? `${child.academic.average} / 20` : "Sin notas"}
                icon="mdi:school-outline"
                color="blue"
                hint={`${child.academic.subjects.length} materias`}
            />
            <KpiCard
                label="Asistencia Acumulada"
                value={child.attendance.rate !== null ? `${child.attendance.rate}%` : "Sin datos"}
                icon="mdi:clipboard-check-outline"
                color="purple"
                hint={`${child.attendance.present} presentes · ${child.attendance.excused} justificadas · ${child.attendance.absent} ausentes`}
            />
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-4">
            <div class="neumorphism rounded-xl border p-5 shadow-sm">
                <h4 class="text-sm font-bold text-gray-800 mb-3">Evolución Académica por Momento</h4>
                <AcademicEvolutionChart evolution={child.academic.evolution} />
            </div>

            <div class="neumorphism rounded-xl border p-5 shadow-sm">
                <h4 class="text-sm font-bold text-gray-800 mb-3">Notas por Materia</h4>
                {#if child.academic.subjects?.length}
                    <ul class="divide-y divide-gray-100 max-h-[220px] overflow-y-auto">
                        {#each child.academic.subjects as subject}
                            <li class="flex items-center justify-between py-2 text-sm">
                                <span class="text-gray-700 truncate pr-3">{subject.name}</span>
                                <span
                                    class={`font-semibold ${subject.annual === null ? "text-gray-400" : attendanceColor(subject.annual >= 10 ? 100 : 50)}`}
                                >
                                    {subject.annual !== null ? subject.annual : "—"}
                                </span>
                            </li>
                        {/each}
                    </ul>
                {:else}
                    <p class="text-sm text-gray-400">Aún no hay materias con planes aprobados.</p>
                {/if}
            </div>
        </div>

        <div class={`grid grid-cols-1 ${data.can_see_money ? "lg:grid-cols-2" : ""} gap-4`}>
            {#if data.can_see_money}
                <div class="neumorphism rounded-xl border p-5 shadow-sm">
                    <h4 class="text-sm font-bold text-gray-800 mb-3">Próximos Pagos / Cuotas a Vencer</h4>
                    {#if child.upcoming.payments?.length}
                    <ul class="space-y-2">
                        {#each child.upcoming.payments as payment}
                            <li
                                class="flex items-center justify-between gap-3 bg-gray-50 rounded-lg px-3 py-2"
                            >
                                <div class="min-w-0">
                                    <p class="text-sm font-semibold text-gray-700 truncate">
                                        {payment.label}
                                    </p>
                                    <p class="text-xs {payment.overdue ? "text-red-500" : "text-gray-400"}">
                                        Vence {formatDate(payment.date)}{payment.overdue ? " · vencido" : ""}
                                    </p>
                                </div>
                                <div class="flex items-center gap-3 shrink-0">
                                    <span class="text-sm font-bold text-gray-800">
                                        {formatCurrency(payment.amount)}
                                    </span>
                                    <a
                                        href={`/dashboard/mis-pagos?student_id=${child.id}`}
                                        class="text-xs font-semibold px-3 py-1.5 rounded-lg bg-color1 text-white hover:bg-color2 transition-colors"
                                    >
                                        Reportar Pago
                                    </a>
                                </div>
                            </li>
                        {/each}
                    </ul>
                {:else}
                    <p class="text-sm text-gray-400">Sin cuotas pendientes. ¡Estás al día!</p>
                {/if}
                </div>
            {/if}

            <div class="neumorphism rounded-xl border p-5 shadow-sm">
                <h4 class="text-sm font-bold text-gray-800 mb-3">Próximas Evaluaciones</h4>
                {#if child.upcoming.evaluations?.length}
                    <ul class="space-y-2">
                        {#each child.upcoming.evaluations as evaluation}
                            <li class="flex items-center justify-between gap-3 bg-gray-50 rounded-lg px-3 py-2">
                                <div class="min-w-0">
                                    <p class="text-sm font-semibold text-gray-700 truncate">
                                        {evaluation.title}
                                    </p>
                                    <p class="text-xs text-gray-400 truncate">
                                        {evaluation.matter ?? "—"}{evaluation.unit ? ` · ${evaluation.unit}` : ""}
                                    </p>
                                </div>
                                <span class="text-xs font-semibold text-color1 shrink-0">
                                    {formatDate(evaluation.date)}
                                </span>
                            </li>
                        {/each}
                    </ul>
                {:else}
                    <p class="text-sm text-gray-400">No hay evaluaciones programadas próximas.</p>
                {/if}
            </div>
        </div>
    </section>
{/each}

<div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mt-2">
    <div class="neumorphism rounded-xl border p-5 shadow-sm">
        <h4 class="text-sm font-bold text-gray-800 mb-3">Avisos y Comunicados</h4>
        {#if data.announcements?.length}
            <ul class="space-y-3 max-h-[300px] overflow-y-auto">
                {#each data.announcements as announcement}
                    <li class="border-l-2 border-color2 pl-3">
                        <p class="text-sm font-semibold text-gray-800">{announcement.title}</p>
                        <p class="text-xs text-gray-500 whitespace-pre-line">{announcement.body}</p>
                        <p class="text-[11px] text-gray-400 mt-1">{formatDate(announcement.date)}</p>
                    </li>
                {/each}
            </ul>
        {:else}
            <p class="text-sm text-gray-400">No hay comunicados publicados.</p>
        {/if}
    </div>

    <div class="neumorphism rounded-xl border p-5 shadow-sm">
        <h4 class="text-sm font-bold text-gray-800 mb-3">Calendario de Actividades</h4>
        {#if data.events?.length}
            <ul class="space-y-2">
                {#each data.events as event}
                    <li class="flex items-start justify-between gap-3 bg-gray-50 rounded-lg px-3 py-2">
                        <div class="min-w-0">
                            <p class="text-sm font-semibold text-gray-700 truncate">{event.title}</p>
                            <p class="text-xs text-gray-400 truncate">
                                {EVENT_LABELS[event.type] ?? "Actividad"}{event.description ? ` · ${event.description}` : ""}
                            </p>
                        </div>
                        <span class="text-xs font-semibold text-color1 shrink-0">
                            {formatDate(event.date)}
                        </span>
                    </li>
                {/each}
            </ul>
        {:else}
            <p class="text-sm text-gray-400">No hay actividades programadas.</p>
        {/if}
    </div>
</div>
