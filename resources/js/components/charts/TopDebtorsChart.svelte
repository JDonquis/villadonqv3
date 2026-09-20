<script>
    import { onMount, onDestroy } from "svelte";
    import * as echarts from "echarts";
    import axios from "axios";

    export let schoolLapseId = null;
    export let limit = 10;

    const MATTER_PASTELS = [
        { bg: "#fde68a", text: "#92400e" },
        { bg: "#bbf7d0", text: "#14532d" },
        { bg: "#bfdbfe", text: "#1e3a8a" },
        { bg: "#e9d5ff", text: "#581c87" },
        { bg: "#fed7aa", text: "#7c2d12" },
        { bg: "#fbcfe8", text: "#831843" },
        { bg: "#c7d2fe", text: "#312e81" },
        { bg: "#a5f3fc", text: "#164e63" },
        { bg: "#fecaca", text: "#7f1d1d" },
        { bg: "#d9f99d", text: "#365314" },
        { bg: "#fef3c7", text: "#78350f" },
        { bg: "#d1fae5", text: "#064e3b" },
        { bg: "#ede9fe", text: "#4c1d95" },
        { bg: "#ffedd5", text: "#9a3412" },
    ];

    let chartContainer;
    let myChart;
    let option = {};

    async function fetchData() {
        try {
            const url = schoolLapseId
                ? `/dashboard/graficos/top-debtors/${limit}/${schoolLapseId}`
                : `/dashboard/graficos/top-debtors/${limit}`;
            const response = await axios.get(url);
            const data = response.data.data;

            const labels = data.map((d) => `${d.name} (${d.course} ${d.section})`);
            const debts = data.map((d) => d.debt);

            const colors = MATTER_PASTELS.map((c) => c.bg);

            option = {
                color: colors,
                tooltip: {
                    trigger: "axis",
                    axisPointer: { type: "shadow" },
                    valueFormatter: (value) =>
                        "$" +
                        value.toLocaleString(undefined, {
                            minimumFractionDigits: 2,
                            maximumFractionDigits: 2,
                        }),
                },
                grid: {
                    left: "3%",
                    right: "4%",
                    bottom: "3%",
                    containLabel: true,
                },
                xAxis: {
                    type: "value",
                    name: "Deuda ($)",
                    axisLabel: { formatter: "${value}" },
                },
                yAxis: {
                    type: "category",
                    data: labels,
                    inverse: true,
                    axisLabel: { interval: 0, overflow: "truncate", width: 200 },
                },
                series: [
                    {
                        name: "Deuda",
                        type: "bar",
                        label: {
                            show: true,
                            position: "right",
                            formatter: (params) =>
                                "$" +
                                params.value.toLocaleString(undefined, {
                                    minimumFractionDigits: 2,
                                    maximumFractionDigits: 2,
                                }),
                            fontSize: 10,
                        },
                        data: debts,
                    },
                ],
            };

            if (myChart) {
                myChart.setOption(option);
            }
        } catch (error) {
            console.error("Error fetching top debtors:", error);
        }
    }

    onMount(() => {
        myChart = echarts.init(chartContainer);
        fetchData();
        window.addEventListener("resize", () => myChart?.resize());
    });

    onDestroy(() => {
        if (myChart) myChart.dispose();
    });
</script>

<div class="bg-white rounded-xl border p-5 shadow-sm">
    <h3 class="text-lg font-bold text-gray-800 mb-4">Top {limit} Deudores</h3>
    <div bind:this={chartContainer} class="w-full h-[350px]"></div>
</div>