<script>
    import { onMount, onDestroy } from "svelte";
    import * as echarts from "echarts";
    import axios from "axios";

    export let schoolLapseId = null;

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
                ? `/dashboard/graficos/debt-by-course/${schoolLapseId}`
                : `/dashboard/graficos/debt-by-course`;
            const response = await axios.get(url);
            const data = response.data.data;

            const colors = MATTER_PASTELS.map((c) => c.bg);

            option = {
                color: colors,
                tooltip: {
                    trigger: "axis",
                    axisPointer: { type: "shadow" },
                    valueFormatter: (value) => "$" + value.toLocaleString(),
                },
                legend: {
                    data: ["Inscripción", "Mensualidades"],
                    bottom: 0,
                },
                grid: {
                    left: "3%",
                    right: "4%",
                    bottom: "15%",
                    containLabel: true,
                },
                xAxis: {
                    type: "category",
                    data: data.labels,
                    axisLabel: { interval: 0, rotate: 30 },
                },
                yAxis: {
                    type: "value",
                    name: "Deuda ($)",
                    axisLabel: { formatter: "${value}" },
                },
                series: [
                    {
                        name: "Inscripción",
                        type: "bar",
                        stack: "total",
                        data: data.inscription,
                    },
                    {
                        name: "Mensualidades",
                        type: "bar",
                        stack: "total",
                        data: data.monthly,
                    },
                ],
            };

            if (myChart) {
                myChart.setOption(option);
            }
        } catch (error) {
            console.error("Error fetching debt by course:", error);
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
    <h3 class="text-lg font-bold text-gray-800 mb-4">Deuda por Curso/Grado</h3>
    <div bind:this={chartContainer} class="w-full h-[350px]"></div>
</div>