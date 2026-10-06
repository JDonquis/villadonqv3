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
    export let canSeeMoney = false;

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

    $: thisMonthIncome = formatCurrency(kpi.this_month_income);
    $: monthTarget = formatCurrency(kpi.month_target);
    $: incomeHint = `Meta: ${monthTarget} (${kpi.income_percentage ?? 0}%)`;

    $: totalDebt = formatCurrency(kpi.total_outstanding_debt);
    $: collectedPct = Math.max(0, 100 - (kpi.debt_percentage ?? 0));
    $: debtHint = `${collectedPct.toFixed(1)}% cobrado de ${formatCurrency(kpi.total_billed)}`;

    $: collectionRate = (kpi.collection_rate ?? 0) + "%";
    $: collectionHint = `${kpi.representatives_up_to_date ?? 0} de ${kpi.representatives_total ?? 0} representantes al día`;

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
        if (canSeeMoney) fetchFlow(selectedLapse || undefined);
    }

    onMount(() => {
        if (!canSeeMoney) return;
        myChart = echarts.init(chartContainer);
        myChart.setOption(option);
        window.addEventListener("resize", handleResize);
        fetchFlow(selectedLapse || undefined);
    });

    onDestroy(() => {
        window.removeEventListener("resize", handleResize);
        myChart?.dispose();
    });
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

<div
    class={`grid grid-cols-1 sm:grid-cols-2 ${canSeeMoney ? "lg:grid-cols-4" : "lg:grid-cols-2"} gap-4 mb-6`}
    role="region"
    aria-label="Indicadores clave"
>
    <KpiCard
        label="Total Estudiantes Matriculados"
        value={totalStudents}
        icon="mdi:account-multiple"
        color="blue"
        hint={enrollmentHint}
        trend={enrollmentTrend}
    />
    {#if canSeeMoney}
        <KpiCard
            label="Ingresos del Mes"
            value={thisMonthIncome}
            icon="mdi:cash-multiple"
            color="teal"
            hint={incomeHint}
        />
        <KpiCard
            label="Deuda Total Pendiente"
            value={totalDebt}
            icon="mdi:alert-circle"
            color="red"
            hint={debtHint}
        />
        <KpiCard
            label="Tasa de Cobranza"
            value={collectionRate}
            icon="mdi:chart-line"
            color="purple"
            hint={collectionHint}
        />
    {:else}
        <KpiCard
            label="Representantes legales"
            value={kpi.total_representatives?.toLocaleString() ?? "0"}
            icon="mdi:account-group"
            color="green"
        />
    {/if}
</div>

{#if canSeeMoney}
    <div class="w-full p-6 rounded-md max-w-[1200px] flex flex-col gap-4">
        <h3 class="text-lg font-bold text-gray-800 tracking-tight">
            Proyección vs. Recaudación Real
        </h3>
    </div>

    <div bind:this={chartContainer} class="w-full h-[400px] neumorphism rounded-lg"></div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mt-6">
        <DebtByCourseChart schoolLapseId={selectedLapse} />
        <AgingChart schoolLapseId={selectedLapse} />
        <CollectionByChannelChart schoolLapseId={selectedLapse} />
        <CollectionRateTrendChart years={5} />
    </div>

    <div class="mt-6">
        <TopDebtorsChart schoolLapseId={selectedLapse} limit={10} />
    </div>
{/if}

<AttendanceSummaryCard schoolLapseId={selectedLapse} />
