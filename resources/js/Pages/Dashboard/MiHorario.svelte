<script>
    import { router, page } from "@inertiajs/svelte";
    import ScheduleWeekGrid from "../../components/ScheduleWeekGrid.svelte";

    export let data = [];

    const DAYS = [
        { key: 1, label: "Lunes" },
        { key: 2, label: "Martes" },
        { key: 3, label: "Miércoles" },
        { key: 4, label: "Jueves" },
        { key: 5, label: "Viernes" },
    ];

    const filters = $page.props.data?.filters || {};
    let selectedPeriod = Number(filters.school_lapse_id || data.lapse_id || 1);

    $: days = data.days || {};

    $: totalClasses = DAYS.reduce(
        (acc, day) => acc + (days[day.key] || []).length,
        0,
    );

    $: teacherSchedule = {
        days,
        recess_start: null,
        recess_duration_minutes: 0,
    };

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
            "/dashboard/mi-horario",
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
</script>

<div class="w-full">
    <div class="flex flex-col gap-2 mb-4">
        <h2 class="text-xl font-semibold text-gray-800">Mi Horario</h2>
        <p class="text-sm text-gray-500">
            Clases que impartes ({totalClasses} en total).
        </p>
    </div>

    <div class="flex flex-wrap items-center gap-4 border border-gray-200 bg-gray-50 rounded-xl p-4">
        <div class="flex flex-col">
            <label class="text-xs font-semibold text-gray-700 mb-1"
                >Periodo escolar</label
            >
            <select
                bind:value={selectedPeriod}
                on:change={changePeriod}
                class="border border-gray-300 rounded-md px-3 py-2 text-sm"
            >
                {#each data.periods as period}
                    <option value={period.id}>{period.name}</option>
                {/each}
            </select>
        </div>
    </div>

    {#if totalClasses === 0}
        <div
            class="mt-6 border border-gray-200 bg-gray-50 rounded-xl p-8 text-center text-gray-500"
        >
            No impartes clases en este periodo escolar.
        </div>
    {:else}
        <div class="mt-4">
            <ScheduleWeekGrid schedule={teacherSchedule} />
        </div>
    {/if}
</div>
