<script>
    import { router, page } from "@inertiajs/svelte";

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

    function timeLabel(hhmm) {
        const t = from24String(hhmm);
        return `${t.hour}:${t.minute} ${t.ampm}`;
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
        <div class="grid grid-cols-1 md:grid-cols-5 gap-4 mt-4">
            {#each DAYS as day}
                <div
                    class="border border-gray-200 rounded-xl bg-gray-50 p-3 flex flex-col"
                >
                    <h4 class="font-semibold text-gray-800 text-sm mb-3">
                        {day.label}
                    </h4>

                    {#if (days[day.key] || []).length === 0}
                        <p class="text-sm text-gray-400 text-center py-2">
                            Sin clases
                        </p>
                    {:else}
                        {#each days[day.key] || [] as row, index}
                            <div
                                class="mb-2 p-3 border border-gray-200 rounded-lg bg-white"
                            >
                                <div
                                    class="flex items-center justify-between mb-2"
                                >
                                    <span class="text-sm font-semibold text-gray-700"
                                        >Clase {index + 1}</span
                                    >
                                    <span
                                        class="text-xs font-medium text-gray-600"
                                        >{timeLabel(row.start_time)} – {timeLabel(row.end_time)}</span
                                    >
                                </div>
                                <div
                                    class="flex items-center gap-2 text-sm text-gray-700 mt-1"
                                >
                                    <iconify-icon
                                        icon="mdi:book-open-variant"
                                        class="text-gray-400"
                                    ></iconify-icon>
                                    <span class="font-medium"
                                        >{row.matter_name || "Sin materia"}</span
                                    >
                                </div>
                                <div
                                    class="mt-2 pt-2 border-t border-gray-100 text-xs text-gray-500"
                                >
                                    {row.course_name}
                                    {#if row.section_name} · Sección {row.section_name}{/if}
                                </div>
                            </div>
                        {/each}
                    {/if}
                </div>
            {/each}
        </div>
    {/if}
</div>
