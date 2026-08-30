<script>
    export let schedule = null;

    const DAYS = [
        { key: 1, label: "Lunes" },
        { key: 2, label: "Martes" },
        { key: 3, label: "Miércoles" },
        { key: 4, label: "Jueves" },
        { key: 5, label: "Viernes" },
    ];

    const PX_PER_HOUR = 70;
    const BASE_HOUR = 7;

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

    function toMinutes(hhmm) {
        if (!hhmm) return 0;
        const [h, m] = hhmm.split(":").map((n) => parseInt(n));
        return h * 60 + (m || 0);
    }

    function timeLabel(hhmm) {
        const t = from24String(hhmm);
        return `${t.hour}:${t.minute} ${t.ampm}`;
    }

    function topOf(timeStr) {
        const [h, m] = String(timeStr || "").split(":").map((n) => parseInt(n, 10));
        return (h + m / 60 - BASE_HOUR) * PX_PER_HOUR;
    }

    function heightOf(start, end) {
        return Math.max(0, topOf(end) - topOf(start));
    }

    function recessEnd() {
        if (!schedule?.recess_start) return null;
        const dur = parseInt(schedule.recess_duration_minutes) || 0;
        if (dur <= 0) return schedule.recess_start;
        const startMin = toMinutes(schedule.recess_start);
        const endMin = startMin + dur;
        return `${String(Math.floor(endMin / 60)).padStart(2, "0")}:${String(
            endMin % 60,
        ).padStart(2, "0")}`;
    }

    function classesFor(day) {
        return schedule?.days?.[day] || [];
    }

    function dayHeight() {
        if (!schedule) return 0;
        let maxHours = BASE_HOUR;
        const days = schedule.days || {};
        for (const rows of Object.values(days)) {
            for (const c of rows || []) {
                if (c.end_time) maxHours = Math.max(maxHours, toMinutes(c.end_time) / 60);
            }
        }
        const rEnd = recessEnd();
        if (rEnd) maxHours = Math.max(maxHours, toMinutes(rEnd) / 60);
        return Math.max((maxHours - BASE_HOUR) * PX_PER_HOUR, 48 * 5);
    }

    function hasGridContent() {
        if (!schedule) return false;
        const days = schedule.days || {};
        for (const rows of Object.values(days)) {
            if (rows?.length) return true;
        }
        return (parseInt(schedule.recess_duration_minutes, 10) || 0) > 0;
    }

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

    function matterColor(matterId) {
        const id = Number(matterId) || 0;
        if (id > 0 && id <= MATTER_PASTELS.length) {
            return MATTER_PASTELS[id - 1];
        }
        return MATTER_PASTELS[(id - 1) % MATTER_PASTELS.length];
    }

    function formatTimeRange(start, end) {
        return `${timeLabel(start)} – ${timeLabel(end)}`;
    }
</script>

{#if !hasGridContent()}
    <div class="border border-gray-200 bg-gray-50 rounded-xl p-8 text-center text-gray-500">
        No hay un horario definido para esta sección en el periodo seleccionado.
    </div>
{:else}
    <div class="overflow-x-auto rounded-xl border border-gray-300">
        <div class="grid grid-cols-5 min-w-[680px]">
            {#each DAYS as day}
                <div class="border border-gray-300">
                    <div class="bg-color1 text-white text-center py-2 font-semibold text-sm">
                        {day.label}
                    </div>
                    <div class="relative" style="height: {dayHeight()}px">
                        {#if schedule?.recess_start}
                            {@const rEnd = recessEnd()}
                            <div
                                class="absolute inset-x-1 flex items-center justify-center rounded bg-amber-100 text-amber-800 z-0"
                                style={`top: ${topOf(schedule.recess_start)}px; height: ${heightOf(schedule.recess_start, rEnd)}px;`}
                            >
                                <span
                                    class="text-[10px] opacity-40 py-1 font-bold uppercase tracking-wider text-center"
                                >
                                    Receso ({timeLabel(schedule.recess_start)} – {timeLabel(rEnd)})
                                </span>
                            </div>
                        {/if}
                        {#each classesFor(day.key) as cls}
                            {@const color = matterColor(cls.matter_id)}
                            <div
                                class="absolute w-full px-2 py-1 overflow-hidden z-10"
                                style={`top: ${topOf(cls.start_time)}px; height: ${heightOf(cls.start_time, cls.end_time)}px; background-color: ${color.bg}; color: ${color.text};`}
                            >
                                <div class="text-[10px] font-semibold">
                                    {formatTimeRange(cls.start_time, cls.end_time)}
                                </div>
                                <div class="text-xs font-bold leading-tight truncate">
                                    {cls.matter_name || "—"}
                                </div>
                                <div class="text-[10px] font-semibold opacity-80 truncate">
                                    {#if cls.teacher_name}
                                        {cls.teacher_name}
                                    {/if}
                                    {#if cls.section_name}
                                        {#if cls.teacher_name} · {/if}Sección {cls.section_name}
                                    {/if}
                                    {#if cls.course_name}
                                        {#if cls.teacher_name || cls.section_name} · {/if}{cls.course_name}
                                    {/if}                                </div>
                            </div>
                        {/each}
                    </div>
                </div>
            {/each}
        </div>
    </div>
{/if}
