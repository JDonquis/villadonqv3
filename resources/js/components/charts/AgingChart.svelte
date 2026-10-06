<script>
    import { onMount, onDestroy } from "svelte";
    import * as echarts from "echarts";
    import axios from "axios";

    export let schoolLapseId = null;

    const COLORS = ["#34d399", "#fbbf24", "#fb923c", "#ef4444"];

    let chartContainer;
    let myChart;
    let option = {};
    let total = 0;

    function formatCurrency(value) {
        return "$" + Number(value || 0).toLocaleString(undefined, {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2,
        });
    }

    function handleResize() {
        myChart?.resize();
    }

    async function fetchData(lapseId) {
        try {
            const url = lapseId
                ? `/dashboard/graficos/aging/${lapseId}`
                : `/dashboard/graficos/aging`;
            const response = await axios.get(url);
            const data = response.data.data;
            total = data.total || 0;

            option = {
                color: COLORS,
                tooltip: {
                    trigger: "axis",
                    axisPointer: { type: "shadow" },
                    valueFormatter: (value) => formatCurrency(value),
                },
                grid: { left: "3%", right: "4%", bottom: "3%", containLabel: true },
                xAxis: {
                    type: "category",
                    data: data.labels,
                    axisLabel: { interval: 0, rotate: 20 },
                },
                yAxis: {
                    type: "value",
                    name: "Deuda ($)",
                    axisLabel: { formatter: "${value}" },
                },
                series: [
                    {
                        name: "Deuda",
                        type: "bar",
                        barMaxWidth: 60,
                        itemStyle: {
                            color: (params) => COLORS[params.dataIndex % COLORS.length],
                            borderRadius: [6, 6, 0, 0],
                        },
                        label: {
                            show: true,
                            position: "top",
                            formatter: (params) => formatCurrency(params.value),
                            fontSize: 10,
                        },
                        data: data.data,
                    },
                ],
            };
        } catch (error) {
            console.error("Error fetching aging:", error);
        }
    }

    $: if (myChart) {
        fetchData(schoolLapseId);
    }

    $: if (myChart && option) {
        myChart.setOption(option);
    }

    onMount(() => {
        myChart = echarts.init(chartContainer);
        window.addEventListener("resize", handleResize);
    });

    onDestroy(() => {
        window.removeEventListener("resize", handleResize);
        myChart?.dispose();
    });
</script>

<div class="neumorphism rounded-xl border p-5 shadow-sm">
    <div class="flex items-center justify-between mb-4">
        <h3 class="text-lg font-bold text-gray-800">Morosidad por Antigüedad</h3>
        <span class="text-sm font-semibold text-gray-500">Total: {formatCurrency(total)}</span>
    </div>
    <div bind:this={chartContainer} class="w-full h-[350px]"></div>
</div>
