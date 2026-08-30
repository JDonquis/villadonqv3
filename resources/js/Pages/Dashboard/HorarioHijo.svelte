<script>
    import { router, page } from "@inertiajs/svelte";
    import ScheduleWeekGrid from "../../Components/ScheduleWeekGrid.svelte";

    export let data = [];

    const DAYS = [
        { key: 1, label: "Lunes" },
        { key: 2, label: "Martes" },
        { key: 3, label: "Miércoles" },
        { key: 4, label: "Jueves" },
        { key: 5, label: "Viernes" },
    ];

    const filters = $page.props.data?.filters || {};
    let selectedPeriod = Number(filters.school_lapse_id || 1);

    $: days = data.schedule?.days || {};

    $: totalClasses = DAYS.reduce(
        (acc, day) => acc + (days[day.key] || []).length,
        0,
    );

    function from24String(hhmm) {
        if (!hhmm) return { hour: 12, minute: "00", ampm: "AM" };
        const [h, m] = hhmm.split(":").map((n) => parseInt(n));
        let hour12 = h % 12;
        if (hour12 === 0) hour12 = 12;
        return {
            hour: hour12,
            minute: String(m).padStart(2, "0"),
            ampm: h < 12 ? "AM" : "PM",
        };
    }

    function reload(params) {
        router.get(
            `/dashboard/mis-hijos/${data.student.id}/horario`,
            {
                ...filters,
                ...params,
            },
            {
                preserveState: true,
                preserveScroll: true,
            },
        );
    }

    function changePeriod() {
        reload({ school_lapse_id: selectedPeriod });
    }

    function timeLabel(hhmm) {
        const t = from24String(hhmm);
        return `${t.hour}:${t.minute} ${t.ampm}`;
    }
</script>

<svelte:head>
    <title>Horario del Estudiante</title>
</svelte:head>

<div class="w-full">
    <div class="flex flex-col gap-2 mb-4">
        <h2 class="text-xl font-semibold text-gray-800">
            Horario de {data.student.name} {data.student.last_name}
        </h2>
        <p class="text-sm text-gray-500">
            C.I {data.student.ci} · {data.student.course} · Sección
            {data.student.section}
        </p>
    </div>



    {#if !data.schedule || totalClasses === 0}
        <div
            class="mt-6 border border-gray-200 bg-gray-50 rounded-xl p-8 text-center text-gray-500"
        >
            Este estudiante no tiene horario definido para este periodo.
        </div>
    {:else}
        <div class="mt-4">
            <ScheduleWeekGrid schedule={data.schedule} />
        </div>
    {/if}
</div>
