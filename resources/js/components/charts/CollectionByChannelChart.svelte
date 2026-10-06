<script>
    import { onMount, onDestroy } from "svelte";
    import * as echarts from "echarts";
    import axios from "axios";

    export let schoolLapseId = null;

    const COLORS = [
        "#fde68a", "#bbf7d0", "#bfdbfe", "#e9d5ff", "#fed7aa",
        "#fbcfe8", "#c7d2fe", "#a5f3fc", "#fecaca", "#d9f99d",
    ];

    let chartContainer;
    let myChart;
    let option = {};
    let currency = "usd";
    let payload = { labels: [], usd: [], bs: [] };

    function handleResize() {
        myChart?.resize();
    }

    function buildOption() {
        const values = currency === "usd" ? payload.usd : payload.bs;
        const symbol = currency === "usd" ? "$" : "Bs ";

        option = {
            color: COLORS,
            tooltip: {
                trigger: "item",
                valueFormatter: (value) =>
                    symbol + Number(value || 0).toLocaleString(undefined, {
                        minimumFractionDigits: 2,
                        maximumFractionDigits: 2,
                    }),
            },
            legend: { bottom: 0, type: "scroll" },
            series: [
                {
                    name: "Cobro",
                    type: "pie",
                    radius: ["45%", "70%"],
                    avoidLabelOverlap: true,
                    itemStyle: { borderColor: "#fff", borderWidth: 2 },
                    label: { formatter: "{b}\n{d}%" },
                    data: payload.labels.map((label, index) => ({
                        name: label,
                        value: values[index] ?? 0,
                        itemStyle: { color: COLORS[index % COLORS.length] },
                    })),
                },
            ],
        };
    }

    async function fetchData(lapseId) {
        try {
            const url = lapseId
                ? `/dashboard/graficos/collection-by-channel/${lapseId}`
                : `/dashboard/graficos/collection-by-channel`;
            const response = await axios.get(url);
            payload = response.data.data;
            buildOption();
        } catch (error) {
            console.error("Error fetching collection by channel:", error);
        }
    }

    function setCurrency(value) {
        currency = value;
        buildOption();
    }

    $: if (myChart) {
        fetchData(schoolLapseId);
    }

    $: if (myChart && option) {
        myChart.setOption(option, true);
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
        <h3 class="text-lg font-bold text-gray-800">Distribución de Cobro por Canal</h3>
        <div class="flex rounded-lg overflow-hidden border border-gray-200 text-xs font-semibold">
            <button
                type="button"
                class={currency === "usd" ? "px-3 py-1 bg-color1 text-white" : "px-3 py-1 bg-white text-gray-600"}
                on:click={() => setCurrency("usd")}>USD</button
            >
            <button
                type="button"
                class={currency === "bs" ? "px-3 py-1 bg-color1 text-white" : "px-3 py-1 bg-white text-gray-600"}
                on:click={() => setCurrency("bs")}>Bs</button
            >
        </div>
    </div>
    <div bind:this={chartContainer} class="w-full h-[350px]"></div>
</div>
