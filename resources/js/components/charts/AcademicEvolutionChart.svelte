<script>
    import { onMount, onDestroy } from "svelte";
    import * as echarts from "echarts";

    export let evolution = [];

    let chartContainer;
    let myChart;
    let option = {};

    function handleResize() {
        myChart?.resize();
    }

    $: option = {
        color: ["#1f4287"],
        tooltip: {
            trigger: "axis",
            valueFormatter: (value) => (value === null ? "Sin nota" : value + " pts"),
        },
        grid: { left: "3%", right: "4%", bottom: "3%", containLabel: true },
        xAxis: {
            type: "category",
            data: evolution.map((e) => e.label),
            boundaryGap: false,
        },
        yAxis: {
            type: "value",
            min: 0,
            max: 20,
            interval: 5,
        },
        series: [
            {
                name: "Promedio",
                type: "line",
                smooth: true,
                symbol: "circle",
                symbolSize: 8,
                connectNulls: true,
                lineStyle: { width: 3 },
                areaStyle: { opacity: 0.1 },
                data: evolution.map((e) => e.value),
            },
        ],
    };

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

<div bind:this={chartContainer} class="w-full h-[220px]"></div>
