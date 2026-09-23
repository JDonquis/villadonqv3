<script>
    import { onMount, onDestroy } from "svelte";
    import * as echarts from "echarts";
    import Input from "../../components/Input.svelte";
    import axios from "axios";
    import KpiCard from "../../components/KpiCard.svelte";
    import DebtByCourseChart from "../../components/charts/DebtByCourseChart.svelte";
    import CollectionRateTrendChart from "../../components/charts/CollectionRateTrendChart.svelte";
    import TopDebtorsChart from "../../components/charts/TopDebtorsChart.svelte";
    export let schoolLapses;
    export let schoolCharges = [];
    export let totalSchoolCharges = 0;
    export let schoolChargesByLapse = [];
    export let kpiData = {};

    function formatCurrency(value) {
        return "$" + Number(value || 0).toLocaleString("en-US", {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2,
        });
    }

    $: totalStudents = kpiData.total_students?.toLocaleString() || "0";
    $: totalRepresentatives = kpiData.total_representatives?.toLocaleString() || "0";
    $: totalOutstandingDebt = kpiData.total_outstanding_debt?.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2}) || "0.00";
    $: studentsAtRisk = kpiData.students_at_risk?.toLocaleString() || "0";
    $: collectionRate = (kpiData.collection_rate?.toFixed(1) || "0") + "%";
    $: thisMonthIncome = kpiData.this_month_income?.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2}) || "0.00";
    $: pendingPayments = kpiData.pending_payments?.toLocaleString() || "0";

    let annual_vs_monthly_flow_year_id;
    let chartContainer;
    let myChart;

    // 1. SUPONGAMOS QUE ESTOS SON LOS DATOS CRUDOS QUE LLEGAN DE TU ENDPOINT
    // (Convertimos strings a números y "" a null para que la matemática no falle)

    let annual_vs_monthly_flow_data = {
        pagado_mensual: [],
        esperado_mensual: [],
        real_acumulado: [],
        meta_acumulada: [],
    };

    // 2. FUNCIÓN MATEMÁTICA PARA CALCULAR EL TOPE PERFECTO (Múltiplo de 5 para los saltos del eje)
    function calcularTopeEje(arraysCombinados) {
        // Filtramos nulls, vacíos o cosas que no sean números y buscamos el valor más alto
        const maxValor = Math.max(
            ...arraysCombinados
                .flat()
                .map((v) => Number(v))
                .filter((v) => !isNaN(v)),
        );

        if (maxValor <= 0) return 5000; // Valor por defecto si no hay datos

        // Añadimos un 10% de margen superior para que las barras/líneas no toquen el techo del gráfico
        const valorConMargen = maxValor * 1.1;

        // Buscamos el próximo número más alto que sea divisible exactamente entre 5
        // Esto garantiza que al dividir el eje en 5 tramos (interval), den números enteros limpios
        return Math.ceil(valorConMargen / 5) * 5;
    }

    // 3. CÁLCULO REACTIVO DE LOS TOPES
    // Evaluamos tanto lo real como lo esperado para asegurar que nada se desborde
    $: maxMensual = calcularTopeEje([
        annual_vs_monthly_flow_data.pagado_mensual,
        annual_vs_monthly_flow_data.esperado_mensual,
    ]);
    $: maxAcumulado = calcularTopeEje([
        annual_vs_monthly_flow_data.real_acumulado,
        annual_vs_monthly_flow_data.meta_acumulada,
    ]);

    // 4. EL OBJETO OPTION SE CONFIGURA DINÁMICAMENTE
    // Usamos una declaración reactiva ($:) para que si los datos cambian, el gráfico se entere
    $: option = {
        color: ["#88d498", "#dddddd", "#1f4287", "#ff6b6b"],
        tooltip: {
            trigger: "axis",
            axisPointer: { type: "cross", crossStyle: { color: "#999" } },
        },
        toolbox: {
            feature: {
                dataView: { show: true, readOnly: true, title: "Ver Datos" },
            },
        },
        legend: {
            data: [
                "Pagado",
                "Esperado",
                "Ingreso Real Acumulado",
                "Meta Esperada Acumulada",
            ],
            bottom: 0,
        },
        xAxis: [
            {
                type: "category",
                data: [
                    "Sep",
                    "Oct",
                    "Nov",
                    "Dic",
                    "Ene",
                    "Feb",
                    "Mar",
                    "Abr",
                    "May",
                    "Jun",
                    "Jul",
                    "Ago",
                ],
                axisPointer: { type: "shadow" },
            },
        ],
        yAxis: [
            {
                type: "value",
                name: "Flujo Mensual",
                min: 0,
                max: maxMensual,
                interval: maxMensual / 5, // División perfecta en 5 partes
                axisLabel: { formatter: "${value}" },
            },
            {
                type: "value",
                name: "Histórico Anual",
                min: 0,
                max: maxAcumulado,
                interval: maxAcumulado / 5, // División perfecta en 5 partes
                axisLabel: { formatter: "${value}" },
                splitLine: { show: false },
            },
        ],
        series: [
            {
                name: "Pagado",
                type: "bar",
                tooltip: {
                    valueFormatter: (value) =>
                        "$" + (value ? value.toLocaleString() : 0),
                },
                data: annual_vs_monthly_flow_data.pagado_mensual,
            },
            {
                name: "Esperado",
                type: "bar",
                tooltip: {
                    valueFormatter: (value) => "$" + value.toLocaleString(),
                },
                data: annual_vs_monthly_flow_data.esperado_mensual,
            },
            {
                name: "Ingreso Real Acumulado",
                type: "line",
                yAxisIndex: 1,
                smooth: true,
                tooltip: {
                    valueFormatter: (value) =>
                        "$" + (value ? value.toLocaleString() : 0),
                },
                data: annual_vs_monthly_flow_data.real_acumulado,
            },
            {
                name: "Meta Esperada Acumulada",
                type: "line",
                yAxisIndex: 1,
                smooth: true,
                lineStyle: { type: "dashed", width: 2 },
                tooltip: {
                    valueFormatter: (value) => "$" + value.toLocaleString(),
                },
                data: annual_vs_monthly_flow_data.meta_acumulada,
            },
        ],
    };

    // 5. OBSERVAR CAMBIOS EN OPTION PARA ACTUALIZAR EL GRÁFICO
    // Si los datos llegan después de que el componente montó (frecuente con fetch), esto redibuja automáticamente
    $: if (myChart && option) {
        myChart.setOption(option);
    }

    function handleResize() {
        if (myChart) myChart.resize();
    }

    onMount(() => {
        myChart = echarts.init(chartContainer);
        myChart.setOption(option);
        window.addEventListener("resize", handleResize);
    });

    onMount(async () => {
        // Inicializamos ECharts con la estructura base vacía
        myChart = echarts.init(chartContainer);
        myChart.setOption(option);
        window.addEventListener("resize", handleResize);

        // Llamamos a la función SIN parámetros la primera vez.
        // Tu backend entenderá que es la carga inicial y buscará el último año.
        await getAnnualVsMonthlyFlowData();
    });

    onDestroy(() => {
        if (myChart) myChart.dispose();
        window.removeEventListener("resize", handleResize);
    });

    // 7. FUNCIÓN ASÍNCRONA MODIFICADA
    // Hacemos que el 'year_id' sea opcional (por defecto undefined)
    async function getAnnualVsMonthlyFlowData(year_id = undefined) {
        try {
            if (myChart) myChart.showLoading();

            // Si hay year_id construimos la ruta con el ID, si no, llamamos a la ruta base de carga inicial
            const url = year_id
                ? `/dashboard/graficos/annual-vs-monthly-flow/${year_id}`
                : `/dashboard/graficos/annual-vs-monthly-flow`; // <-- Ajusta esta URL a tu ruta base si es distinta

            const response = await axios.get(url);
            const data = response.data.data;
            console.log("Datos recibidos del backend:", data);
            console.log(response);

            annual_vs_monthly_flow_year_id =
                response.data.schoolLapseID.toLocaleString();
            annual_vs_monthly_flow_data = data;
        } catch (error) {
            console.error("Error al obtener datos:", error);
        } finally {
            if (myChart) myChart.hideLoading();
        }
    }

    onDestroy(() => {
        if (myChart) myChart.dispose();
        window.removeEventListener("resize", handleResize);
    });
</script>

<svelte:head>
    <title>Dashboard</title>
</svelte:head>

<h2 class="text-xl md:text-2xl font-bold text-color1 sm:hidden mb-3">
    Panel de control
</h2>

<!-- KPI Cards Grid -->
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6" role="region" aria-label="Indicadores clave">
    <KpiCard
        label="Total Estudiantes"
        value={totalStudents}
        icon="mdi:account-multiple"
        color="blue"
    />
    <KpiCard
        label="Representantes legales"
        value={totalRepresentatives}
        icon="mdi:account-group"
        color="green"
    />
    <KpiCard
        label="Deuda Pendiente"
        value="$" + totalOutstandingDebt
        icon="mdi:alert-circle"
        color="red"
    />
    <KpiCard
        label="Estudiantes en Riesgo"
        value={studentsAtRisk}
        icon="mdi:shield-alert"
        color="orange"
    />
    <KpiCard
        label="Tasa Cobranza Mes"
        value={collectionRate}
        icon="mdi:chart-line"
        color="purple"
    />
    <KpiCard
        label="Ingresos Este Mes"
        value="$" + thisMonthIncome
        icon="mdi:cash-multiple"
        color="teal"
    />
    <KpiCard
        label="Pagos Pendientes"
        value={pendingPayments}
        icon="mdi:clock-alert"
        color="amber"
    />
</div>

<div
    class="w-full    p-6 rounded-md max-w-[1200px] flex flex-col gap-4"
>
    <div>
        <div class="flex gap-10 items-start">
            <h3 class="text-lg font-bold text-gray-800 tracking-tight">
                Recaudación Anual vs. Flujo Mensual
            </h3>
            {#if schoolLapses}
                <Input
                    id="filterYear"
                    type="select"
                    on:change={(e) => {
                        console.log("Cambiando año a:", e.target.value);
                        getAnnualVsMonthlyFlowData(e.target.value);
                    }}
                    bind:value={annual_vs_monthly_flow_year_id}
                    classes={"max-w-[170px] mt-0 "}
                    style={"margin-top: 0"}
                >
                    {#each schoolLapses as lapse}
                        <option class="bg-gray-50" value={lapse.id.toString()}
                            >{lapse.start.slice(0, 4)} - {lapse.end.slice(
                                0,
                                4,
                            )}</option
                        >
                    {/each}
                </Input>
            {/if}
        </div>
    </div>

    

    <!-- <div class="mt-6 border-t border-gray-200 pt-6 flex flex-col gap-4">
        <div class="flex items-center justify-between">
            <h3 class="text-lg font-bold text-gray-800 tracking-tight">
                Deuda acumulada a favor (cobro de $1 por estudiante inscrito)
            </h3>
            <div class="bg-blue-50 text-blue-800 font-bold px-4 py-2 rounded-md text-xl">
                {formatCurrency(totalSchoolCharges)}
            </div>
        </div>

        {#if schoolChargesByLapse && schoolChargesByLapse.length > 0}
            <div class="flex flex-wrap gap-3">
                {#each schoolChargesByLapse as lapse}
                    <div class="bg-gray-50 border border-gray-200 rounded-md px-4 py-2 flex items-center gap-3">
                        <span class="text-sm font-medium text-gray-700">
                            {lapse.school_lapse}
                        </span>
                        <span class="text-xs text-gray-500">
                            {lapse.students} estudiantes
                        </span>
                        <span class="text-sm font-bold text-gray-800">
                            {formatCurrency(lapse.total)}
                        </span>
                    </div>
                {/each}
            </div>
        {/if}

        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead>
                    <tr class="border-b border-gray-200 text-left text-gray-500">
                        <th class="py-2 pr-4 font-medium">Estudiante</th>
                        <th class="py-2 pr-4 font-medium">Cédula</th>
                        <th class="py-2 pr-4 font-medium">Periodo Escolar</th>
                        <th class="py-2 font-medium text-right">Monto adeudado</th>
                    </tr>
                </thead>
                <tbody>
                    {#if schoolCharges && schoolCharges.length > 0}
                        {#each schoolCharges as charge}
                            <tr class="border-b border-gray-100">
                                <td class="py-2 pr-4 text-gray-800">{charge.student}</td>
                                <td class="py-2 pr-4 text-gray-600">{charge.ci}</td>
                                <td class="py-2 pr-4 text-gray-600">{charge.school_lapse}</td>
                                <td class="py-2 font-medium text-right text-gray-800">
                                    {formatCurrency(charge.amount)}
                                </td>
                            </tr>
                        {/each}
                    {:else}
                        <tr>
                            <td colspan="4" class="py-4 text-center text-gray-400">
                                Aún no hay cobros registrados. Se generan automáticamente al inscribir o reinscribir un estudiante.
                            </td>
                        </tr>
                    {/if}
                </tbody>
            </table>
        </div>
    </div> -->
</div>

<div bind:this={chartContainer} class="w-full h-[400px] neumorphism rounded-lg"></div>

    <!-- Additional Charts Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mt-6  ">
        <DebtByCourseChart schoolLapseId={annual_vs_monthly_flow_year_id} />
        <CollectionRateTrendChart years={5} />
    </div>
    <div class="mt-6 ">
        <TopDebtorsChart schoolLapseId={annual_vs_monthly_flow_year_id} limit={10} />
    </div>
