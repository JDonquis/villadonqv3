<script>
    import { onMount, onDestroy } from "svelte";
    import * as echarts from "echarts";
    import Input from "../../components/Input.svelte";
    import axios from "axios";
    import KpiCard from "../../components/KpiCard.svelte";
    import DebtByCourseChart from "../../components/charts/DebtByCourseChart.svelte";
    import CollectionRateTrendChart from "../../components/charts/CollectionRateTrendChart.svelte";
    import TopDebtorsChart from "../../components/charts/TopDebtorsChart.svelte";
    import AgingChart from "../../components/charts/AgingChart.svelte";
    import CollectionByChannelChart from "../../components/charts/CollectionByChannelChart.svelte";
    import AttendanceSummaryCard from "../../components/charts/AttendanceSummaryCard.svelte";

    export let schoolLapses = [];
    export let kpiData = {};
    export let widgets = [];

    const has = (key) => widgets.includes(key);

    let kpi = kpiData;
    let selectedLapse = kpiData.school_lapse_id
        ? String(kpiData.school_lapse_id)
        : schoolLapses[0]?.id?.toString() ?? "";

    const MONTH_LABELS = [
        "Sep", "Oct", "Nov", "Dic", "Ene", "Feb",
        "Mar", "Abr", "May", "Jun", "Jul", "Ago",
    ];

    function formatCurrency(value) {
        return "$" + Number(value || 0).toLocaleString(undefined, {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2,
        });
    }

    $: totalStudents = (kpi.enrollment?.current ?? kpi.total_students)?.toLocaleString() ?? "0";
    $: enrollment = kpi.enrollment ?? null;
    $: enrollmentTrend = enrollment
        ? { up: enrollment.variation >= 0, value: Math.abs(enrollment.percentage) }
        : null;
    $: enrollmentHint = enrollment
        ? `${enrollment.variation >= 0 ? "+" : ""}${enrollment.variation} vs. período anterior (${enrollment.previous})`
        : "";

    $: representatives = kpi.representatives ?? null;
    $: representativesValue = (representatives?.current ?? kpi.total_representatives)?.toLocaleString() ?? "0";
    $: representativesTrend = representatives
        ? { up: representatives.variation >= 0, value: Math.abs(representatives.percentage) }
        : null;
    $: representativesHint = representatives
        ? `${representatives.variation >= 0 ? "+" : ""}${representatives.variation} vs. período anterior (${representatives.previous})`
        : "";

    $: thisMonthIncome = formatCurrency(kpi.this_month_income);
    $: monthTarget = formatCurrency(kpi.month_target);
    $: incomeHint = `Meta: ${monthTarget} (${kpi.income_percentage ?? 0}%)`;

    $: paymentsCount = kpi.payments_count?.toLocaleString() ?? "0";

    $: totalDebt = formatCurrency(kpi.total_outstanding_debt);
    $: collectedPct = Math.max(0, 100 - (kpi.debt_percentage ?? 0));
    $: debtHint = `${collectedPct.toFixed(1)}% cobrado de ${formatCurrency(kpi.total_billed)}`;

    $: repsOnTrack = (kpi.collection_rate ?? 0) + "%";
    $: repsOnTrackHint = `${kpi.representatives_up_to_date ?? 0} de ${kpi.representatives_total ?? 0} representantes al día`;

    // ---- Gráfico principal: proyección vs. recaudación real ----
    let flowData = {
        pagado_mensual: [],
        esperado_mensual: [],
        real_acumulado: [],
        meta_acumulada: [],
    };

    function calcularTopeEje(arraysCombinados) {
        const maxValor = Math.max(
            ...arraysCombinados
                .flat()
                .map((v) => Number(v))
                .filter((v) => !isNaN(v)),
        );

        if (!isFinite(maxValor) || maxValor <= 0) return 5000;

        return Math.ceil((maxValor * 1.1) / 5) * 5;
    }

    $: maxMensual = calcularTopeEje([flowData.pagado_mensual, flowData.esperado_mensual]);
    $: maxAcumulado = calcularTopeEje([flowData.real_acumulado, flowData.meta_acumulada]);

    let chartContainer;
    let myChart;
    let option = {};

    $: option = {
        color: ["#88d498", "#dddddd", "#1f4287", "#ff6b6b"],
        tooltip: {
            trigger: "axis",
            axisPointer: { type: "cross", crossStyle: { color: "#999" } },
            valueFormatter: (value) =>
                value === "" || value === null ? "-" : formatCurrency(value),
        },
        toolbox: {
            feature: { dataView: { show: true, readOnly: true, title: "Ver Datos" } },
        },
        legend: {
            data: ["Pagado", "Esperado", "Ingreso Real Acumulado", "Meta Esperada Acumulada"],
            bottom: 0,
        },
        xAxis: [{ type: "category", data: MONTH_LABELS, axisPointer: { type: "shadow" } }],
        yAxis: [
            {
                type: "value",
                name: "Flujo Mensual",
                min: 0,
                max: maxMensual,
                interval: maxMensual / 5,
                axisLabel: { formatter: "${value}" },
            },
            {
                type: "value",
                name: "Histórico Anual",
                min: 0,
                max: maxAcumulado,
                interval: maxAcumulado / 5,
                axisLabel: { formatter: "${value}" },
                splitLine: { show: false },
            },
        ],
        series: [
            { name: "Pagado", type: "bar", data: flowData.pagado_mensual },
            { name: "Esperado", type: "bar", data: flowData.esperado_mensual },
            {
                name: "Ingreso Real Acumulado",
                type: "line",
                yAxisIndex: 1,
                smooth: true,
                data: flowData.real_acumulado,
            },
            {
                name: "Meta Esperada Acumulada",
                type: "line",
                yAxisIndex: 1,
                smooth: true,
                lineStyle: { type: "dashed", width: 2 },
                data: flowData.meta_acumulada,
            },
        ],
    };

    $: if (myChart && option) {
        myChart.setOption(option);
    }

    function handleResize() {
        myChart?.resize();
    }

    async function fetchKpis(lapseId) {
        try {
            const url = lapseId ? `/dashboard/metricas/${lapseId}` : `/dashboard/metricas`;
            const response = await axios.get(url);
            kpi = response.data.data;
        } catch (error) {
            console.error("Error fetching dashboard metrics:", error);
        }
    }

    async function fetchFlow(lapseId) {
        try {
            if (myChart) myChart.showLoading();
            const url = lapseId
                ? `/dashboard/graficos/annual-vs-monthly-flow/${lapseId}`
                : `/dashboard/graficos/annual-vs-monthly-flow`;
            const response = await axios.get(url);
            flowData = response.data.data;
        } catch (error) {
            console.error("Error fetching annual vs monthly flow:", error);
        } finally {
            if (myChart) myChart.hideLoading();
        }
    }

    let lastLapse = selectedLapse;
    $: if (selectedLapse !== lastLapse) {
        lastLapse = selectedLapse;
        fetchKpis(selectedLapse || undefined);
        if (has("chart_projection_vs_real")) fetchFlow(selectedLapse || undefined);
    }

    onMount(() => {
        if (!has("chart_projection_vs_real")) return;
        myChart = echarts.init(chartContainer);
        myChart.setOption(option);
        window.addEventListener("resize", handleResize);
        fetchFlow(selectedLapse || undefined);
    });

    onDestroy(() => {
        window.removeEventListener("resize", handleResize);
        myChart?.dispose();
    });

    $: hasMainKpis = [
        "kpi_enrollment",
        "kpi_representatives",
        "kpi_payments_count",
        "kpi_month_income",
        "kpi_total_debt",
        "kpi_reps_on_track",
    ].some(has);

    $: hasInstitutionalKpis = [
        "kpi_staff_total",
        "kpi_teachers_total",
        "kpi_matters_total",
        "kpi_schedules",
        "kpi_active_period",
    ].some(has);

    $: plans = kpi.plans ?? {};
    $: hasPlansKpis = [
        "kpi_plans_approved",
        "kpi_plans_pending",
        "kpi_plans_rejected",
    ].some(has);

    $: hasAnyChart =
        has("chart_projection_vs_real") ||
        has("chart_debt_by_course") ||
        has("chart_aging") ||
        has("chart_collection_by_channel") ||
        has("chart_revenue_trend") ||
        has("chart_top_debtors") ||
        has("stat_withdrawn") ||
        has("stat_graduated") ||
        has("stat_attendance_today");
</script>

<svelte:head>
    <title>Dashboard</title>
</svelte:head>

<h2 class="text-xl md:text-2xl font-bold text-color1 sm:hidden mb-3">Panel de control</h2>

<div class="flex justify-end mb-4">
    {#if schoolLapses?.length}
        <Input
            id="filterYear"
            type="select"
            bind:value={selectedLapse}
            classes={"max-w-[190px] mt-0 "}
        >
            {#each schoolLapses as lapse}
                <option value={lapse.id.toString()}>
                    {lapse.start.slice(0, 4)} - {lapse.end.slice(0, 4)}
                </option>
            {/each}
        </Input>
    {/if}
</div>

{#if !hasMainKpis && !hasInstitutionalKpis && !hasPlansKpis && !hasAnyChart}
    <div class="neumorphism rounded-xl p-8 text-center text-gray-500">
        No tienes módulos con indicadores disponibles. Contacta al administrador.
    </div>
{:else}
    {#if hasMainKpis}
        <div
            class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5 gap-4 mb-6"
            role="region"
            aria-label="Indicadores clave"
        >
            {#if has("kpi_enrollment")}
                <KpiCard
                    label="Total Estudiantes Matriculados"
                    value={totalStudents}
                    icon="mdi:account-multiple"
                    color="blue"
                    hint={enrollmentHint}
                    trend={enrollmentTrend}
                />
            {/if}
            {#if has("kpi_representatives")}
                <KpiCard
                    label="Representantes legales"
                    value={representativesValue}
                    icon="mdi:account-group"
                    color="green"
                    hint={representativesHint}
                    trend={representativesTrend}
                />
            {/if}
            {#if has("kpi_month_income")}
                <KpiCard
                    label="Ingresos del Mes"
                    value={thisMonthIncome}
                    icon="mdi:cash-multiple"
                    color="teal"
                    hint={incomeHint}
                />
            {/if}
            {#if has("kpi_payments_count")}
                <KpiCard
                    label="Pagos Realizados"
                    value={paymentsCount}
                    icon="mdi:receipt-text-check"
                    color="teal"
                />
            {/if}
            {#if has("kpi_total_debt")}
                <KpiCard
                    label="Deuda Total Pendiente"
                    value={totalDebt}
                    icon="mdi:alert-circle"
                    color="red"
                    hint={debtHint}
                />
            {/if}
            {#if has("kpi_reps_on_track")}
                <KpiCard
                    label="% Representantes al día"
                    value={repsOnTrack}
                    icon="mdi:chart-line"
                    color="purple"
                    hint={repsOnTrackHint}
                />
            {/if}
        </div>
    {/if}

    {#if hasInstitutionalKpis}
        <div
            class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5 gap-4 mb-6"
            role="region"
            aria-label="Resumen institucional"
        >
            {#if has("kpi_staff_total")}
                <KpiCard
                    label="Personal administrativo"
                    value={kpi.total_staff?.toLocaleString() ?? "0"}
                    icon="mdi:briefcase-account"
                    color="blue"
                />
            {/if}
            {#if has("kpi_teachers_total")}
                <KpiCard
                    label="Profesores"
                    value={kpi.total_teachers?.toLocaleString() ?? "0"}
                    icon="mdi:account-tie"
                    color="purple"
                />
            {/if}
            {#if has("kpi_matters_total")}
                <KpiCard
                    label="Materias"
                    value={kpi.total_matters?.toLocaleString() ?? "0"}
                    icon="mdi:book-open-variant"
                    color="green"
                />
            {/if}
            {#if has("kpi_schedules")}
                <KpiCard
                    label="Secciones con horario"
                    value={`${kpi.schedules?.with_schedule ?? 0} / ${kpi.schedules?.total_sections ?? 0}`}
                    icon="mdi:calendar-clock"
                    color="teal"
                />
            {/if}
            {#if has("kpi_active_period")}
                <KpiCard
                    label="Período escolar activo"
                    value={kpi.active_period?.label ?? "Sin período"}
                    icon="mdi:calendar-star"
                    color="orange"
                    hint={kpi.active_period ? `${kpi.active_period.moments} momentos` : ""}
                />
            {/if}
        </div>
    {/if}

    {#if hasPlansKpis}
        <h3 class="text-lg font-bold text-gray-800 tracking-tight mb-3">
            Planes de Evaluación
        </h3>
        <div
            class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 mb-6"
            role="region"
            aria-label="Planes de evaluación"
        >
            {#if has("kpi_plans_approved")}
                <KpiCard
                    label="Planes Aprobados"
                    value={plans.approved?.toLocaleString() ?? "0"}
                    icon="mdi:check-decagram"
                    color="green"
                    hint={plans.total !== undefined ? `${plans.total} en total` : ""}
                />
            {/if}
            {#if has("kpi_plans_pending")}
                <KpiCard
                    label="Planes Pendientes"
                    value={plans.pending?.toLocaleString() ?? "0"}
                    icon="mdi:clock-alert-outline"
                    color="amber"
                />
            {/if}
            {#if has("kpi_plans_rejected")}
                <KpiCard
                    label="Planes Rechazados"
                    value={plans.rejected?.toLocaleString() ?? "0"}
                    icon="mdi:close-octagon-outline"
                    color="red"
                />
            {/if}
        </div>
    {/if}

    {#if has("chart_projection_vs_real")}
        <div class="w-full p-6 rounded-md max-w-[1200px] flex flex-col gap-4">
            <h3 class="text-lg font-bold text-gray-800 tracking-tight">
                Proyección vs. Recaudación Real
            </h3>
        </div>

        <div bind:this={chartContainer} class="w-full h-[400px] neumorphism rounded-lg"></div>
    {/if}

    {#if has("chart_debt_by_course") || has("chart_aging") || has("chart_collection_by_channel") || has("chart_revenue_trend")}
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mt-6">
            {#if has("chart_debt_by_course")}
                <DebtByCourseChart schoolLapseId={selectedLapse} />
            {/if}
            {#if has("chart_aging")}
                <AgingChart schoolLapseId={selectedLapse} />
            {/if}
            {#if has("chart_collection_by_channel")}
                <CollectionByChannelChart schoolLapseId={selectedLapse} />
            {/if}
            {#if has("chart_revenue_trend")}
                <CollectionRateTrendChart years={5} />
            {/if}
        </div>
    {/if}

    {#if has("chart_top_debtors")}
        <div class="mt-6">
            <TopDebtorsChart schoolLapseId={selectedLapse} limit={10} />
        </div>
    {/if}

    {#if has("stat_withdrawn") || has("stat_graduated") || has("stat_attendance_today")}
        <AttendanceSummaryCard schoolLapseId={selectedLapse} {widgets} />
    {/if}
{/if}
