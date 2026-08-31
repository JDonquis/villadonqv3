<script>
    import { router, page } from "@inertiajs/svelte";
    import { displayAlert } from "../../stores/alertStore";
    import ScheduleWeekGrid from "../../components/ScheduleWeekGrid.svelte";

    export let data = [];

    const DAYS = [
        { key: 1, label: "Lunes" },
        { key: 2, label: "Martes" },
        { key: 3, label: "Miércoles" },
        { key: 4, label: "Jueves" },
        { key: 5, label: "Viernes" },
    ];

    const HOURS = Array.from({ length: 12 }, (_, i) => i + 1);
    const MINUTES = Array.from({ length: 60 }, (_, i) =>
        String(i).padStart(2, "0"),
    );

    // ---------- Helpers 12h <-> 24h ----------
    function to24String(hour, minute, ampm) {
        let h = parseInt(hour) || 0;
        const m = parseInt(minute) || 0;
        const a = ampm || "AM";
        if (a === "PM" && h < 12) h += 12;
        if (a === "AM" && h === 12) h = 0;
        return `${String(h).padStart(2, "0")}:${String(m).padStart(2, "0")}`;
    }

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

    // ---------- State ----------
    const filters = $page.props.data?.filters || {};
    let selectedPeriod = Number(filters.school_lapse_id || 1);
    let selectedCourse = Number(filters.course_id || 1);
    let selectedSection = String(filters.section_id || "");

    $: sectionsOfCourse =
        data.course_sections?.data?.[`course_${selectedCourse}`] || [];
    $: matters = data.matters || [];
    $: teachers = data.teachers || [];

    let recess = { hora: 10, minuto: "00", ampm: "AM", duracion: "30" };

    // day -> array of class rows
    let classes = { 1: [], 2: [], 3: [], 4: [], 5: [] };

    function emptyRow() {
        return {
            start: { hour: 7, minute: "00", ampm: "AM" },
            end: { hour: 8, minute: "30", ampm: "AM" },
            matter_id: "",
            teacher_id: "",
        };
    }

    // ---------- Load existing schedule into local state ----------
    function loadSchedule(sched) {
        const empty = { 1: [], 2: [], 3: [], 4: [], 5: [] };
        classes = empty;

        recess = {
            hora: 8,
            minuto: "00",
            ampm: "AM",
            duracion: "15",
        };

        if (!sched) return;

        if (sched.recess_start) {
            const r = from24String(sched.recess_start);
            recess = {
                hora: r.hour,
                minuto: r.minute,
                ampm: r.ampm,
                duracion: String(sched.recess_duration_minutes ?? "15"),
            };
        }

        const days = sched.days || {};
        const newClasses = { 1: [], 2: [], 3: [], 4: [], 5: [] };
        for (const [day, rows] of Object.entries(days)) {
            newClasses[day] = (rows || []).map((r) => ({
                start: from24String(r.start_time),
                end: from24String(r.end_time),
                matter_id: r.matter_id ?? "",
                teacher_id: r.teacher_id ?? "",
            }));
        }
        classes = newClasses;
    }

    $: if (data.schedule) loadSchedule(data.schedule);

    // ---------- Data loading on selector change ----------
    function reload(params) {
        router.get(
            "/dashboard/horarios",
            {
                ...filters,
                ...params,
            },
            {
                preserveScroll: true,
            },
        );
    }

    function changePeriod() {
        reload({ school_lapse_id: selectedPeriod });
    }

    function changeCourse() {
        selectedSection = String(sectionsOfCourse?.[0]?.id ?? "");
        reload({ course_id: selectedCourse, section_id: selectedSection });
    }

    function changeSection(sectionId) {
        selectedSection = String(sectionId);
        reload({ section_id: selectedSection });
    }

    // ---------- Teachers filtered by matter ----------
    function teachersFor(matterId) {
        if (!matterId) return [];
        return teachers.filter((t) =>
            (t.matter_ids || []).includes(Number(matterId)),
        );
    }

    // ---------- Class rows mutation ----------
    function insertClass(day, index) {
        const dayRows = [...(classes[day] || [])];
        const prev = index > 0 ? dayRows[index - 1] : null;
        const newRow = emptyRow();
        if (prev) {
            newRow.start = { ...prev.end };
            // ad 1 and a half hours to the end time of the previous class
            const prevEndMins =
                toMinutes(
                    to24String(prev.end.hour, prev.end.minute, prev.end.ampm),
                ) + 90;
            newRow.end = from24String(
                `${String(Math.floor(prevEndMins / 60)).padStart(2, "0")}:${String(
                    prevEndMins % 60,
                ).padStart(2, "0")}`,
            );
        }
        dayRows.splice(index, 0, newRow);
        classes[day] = dayRows;
        classes = classes;
    }

    function addClass(day) {
        insertClass(day, (classes[day] || []).length);
    }

    function removeClass(day, index) {
        const arr = [...(classes[day] || [])];
        arr.splice(index, 1);
        classes[day] = arr;
        classes = classes;
    }

    function startMustNotBeBefore(row, day, index) {
        const rows = classes[day] || [];
        if (index <= 0) return false;
        const prev = rows[index - 1];
        const prevEnd = toMinutes(
            to24String(prev.end.hour, prev.end.minute, prev.end.ampm),
        );
        const curStart = toMinutes(
            to24String(row.start.hour, row.start.minute, row.start.ampm),
        );
        return curStart < prevEnd;
    }

    // ---------- Teacher availability conflict (cross-section) ----------
    $: occupancy = data.occupancy || [];

    function teacherConflictInfo(row, day) {
        if (!row.teacher_id) return null;
        const curStart = toMinutes(
            to24String(row.start.hour, row.start.minute, row.start.ampm),
        );
        const curEnd = toMinutes(
            to24String(row.end.hour, row.end.minute, row.end.ampm),
        );
        if (curStart >= curEnd) return null;

        const teacherId = Number(row.teacher_id);
        const conflict = occupancy.find((block) => {
            if (Number(block.teacher_id) !== teacherId || Number(block.day) !== day) {
                return false;
            }
            const bStart = toMinutes(block.start_time);
            const bEnd = toMinutes(block.end_time);
            return curStart < bEnd && bStart < curEnd;
        });

        if (!conflict) return null;

        const parts = [];
        if (conflict.course_name) parts.push(conflict.course_name);
        if (conflict.section_name) parts.push(`Sección ${conflict.section_name}`);

        return `Este profesor ya tiene clase ${blocksLabel(blockDayName(conflict.day))} de ${timeLabel(conflict.start_time)} a ${timeLabel(conflict.end_time)} en ${parts.join(" · ")}.`;
    }

    function timeLabel(hhmm) {
        const t = from24String(hhmm);
        return `${t.hour}:${t.minute} ${t.ampm}`;
    }

    function blockDayName(day) {
        const names = ["", "lunes", "martes", "miércoles", "jueves", "viernes"];
        return names[Number(day)] || "";
    }

    function blocksLabel(label) {
        return label ? `el ${label}` : "";
    }

    // ---------- View mode: grid (default) vs form ----------
    let viewMode = "grid";

    function showForm() {
        viewMode = "form";
    }

    function showGrid() {
        viewMode = "grid";
    }

    // ---------- Grid (weekly matrix) ----------
    $: schedule = data.schedule || null;

    // ---------- Materias y horas semanales ----------
    $: subjectHours = (() => {
        const map = {};
        const days = schedule?.days || {};
        for (const rows of Object.values(days)) {
            for (const c of rows || []) {
                if (!c.matter_id || !c.start_time || !c.end_time) continue;
                const mins = toMinutes(c.end_time) - toMinutes(c.start_time);
                if (mins <= 0) continue;
                map[c.matter_id] = map[c.matter_id] || {
                    matter_id: c.matter_id,
                    matter_name: c.matter_name || "—",
                    minutes: 0,
                };
                map[c.matter_id].minutes += mins;
            }
        }
        return Object.values(map)
            .map((m) => ({ ...m, hours: m.minutes / 60 }))
            .sort((a, b) => b.hours - a.hours);
    })();

    // ---------- Save ----------
    const saving = false;
    function save() {
        const payload = {
            school_lapse_id: selectedPeriod,
            course_id: selectedCourse,
            section_id: selectedSection,
            recess_start: to24String(recess.hora, recess.minuto, recess.ampm),
            recess_duration_minutes: recess.duracion,
            days: {},
        };

        for (const day of DAYS) {
            payload.days[day.key] = (classes[day.key] || [])
                .filter((r) => r.matter_id)
                .map((r) => ({
                    start_time: to24String(
                        r.start.hour,
                        r.start.minute,
                        r.start.ampm,
                    ),
                    end_time: to24String(r.end.hour, r.end.minute, r.end.ampm),
                    matter_id: r.matter_id,
                    teacher_id: r.teacher_id,
                }));
        }

        router.post("/dashboard/horarios", payload, {
            preserveScroll: true,
            onSuccess: () => {
                router.reload({ only: ["data.schedule"] });
                displayAlert({
                    type: "success",
                    message: "Horario guardado exitosamente.",
                });
            },
            onError: (errors) => {
                displayAlert({
                    type: "error",
                    message: errors?.message || "Error al guardar el horario.",
                });
            },
        });
    }
</script>

<div class="w-full">
    {#if viewMode === "grid"}
        <div
            class="flex flex-wrap items-center gap-4 border border-gray-200 bg-gray-50 rounded-xl p-4"
        >
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

            <div class="flex flex-col">
                <label class="text-xs font-semibold text-gray-700 mb-1"
                    >Curso</label
                >
                <select
                    bind:value={selectedCourse}
                    on:change={changeCourse}
                    class="border border-gray-300 rounded-md px-3 py-2 text-sm"
                >
                    {#each data.courses as course}
                        <option value={course.id}>{course.name}</option>
                    {/each}
                </select>
            </div>

            <div class="flex flex-col">
                <span class="text-xs font-semibold text-gray-700 mb-1"
                    >Sección</span
                >
                <div class="flex items-center gap-2">
                    {#each sectionsOfCourse as section, i}
                        <button
                            type="button"
                            on:click={() => changeSection(section.id)}
                            class="px-4 py-2 text-sm font-semibold rounded-md transition-colors"
                            class:bg-yellow={String(section.id) === selectedSection}
                            class:bg-gray-200={String(section.id) !==
                                selectedSection}
                        >
                            {section.name}
                        </button>
                    {/each}
                </div>
            </div>

            <button
                type="button"
                on:click={showForm}
                class="ml-auto inline-flex items-center gap-2 px-4 py-2 text-sm font-semibold rounded-md bg-color1 text-white hover:opacity-90 transition"
            >
                <iconify-icon icon="mdi:pencil"></iconify-icon>
                Editar horario
            </button>
        </div>

        <div class="mt-4">
            <ScheduleWeekGrid {schedule} />
        </div>

        {#if subjectHours.length > 0}
            <div class="mt-7 rounded-xl w-fit overflow-hidden">
                <div
                    class=" text-gray-600 px-4 py-2 font-semibold text-sm"
                >
                    Materias y horas semanales
                </div>
                <table class="w-fit text-sm">
                    <tbody>
                        {#each subjectHours as s}
                            <tr class="border-t border-gray-100 ">
                                <td class="px-4 py-1.5 font-medium">
                                    {s.matter_name}
                                </td>
                                <td class="px-4 py-1.5 text-right">
                                    {Math.floor(s.minutes / 60)}h
                                    {#if s.minutes % 60}
                                        {s.minutes % 60}m
                                    {/if}
                                </td>
                            </tr>
                        {/each}
                    </tbody>
                </table>
            </div>
        {/if}
    {:else}
    <div
        class="flex flex-wrap items-center gap-4 border border-gray-200 bg-gray-50 rounded-xl p-4"
    >
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

        <div class="flex flex-col">
            <label class="text-xs font-semibold text-gray-700 mb-1">Curso</label
            >
            <select
                bind:value={selectedCourse}
                on:change={changeCourse}
                class="border border-gray-300 rounded-md px-3 py-2 text-sm"
            >
                {#each data.courses as course}
                    <option value={course.id}>{course.name}</option>
                {/each}
            </select>
        </div>

        <div class="flex flex-col">
            <span class="text-xs font-semibold text-gray-700 mb-1">Sección</span
            >
            <div class="flex items-center gap-2">
                {#each sectionsOfCourse as section, i}
                    <button
                        type="button"
                        on:click={() => changeSection(section.id)}
                        class="px-4 py-2 text-sm font-semibold rounded-md transition-colors"
                        class:bg-yellow={String(section.id) === selectedSection}
                        class:bg-gray-200={String(section.id) !==
                            selectedSection}
                    >
                        {section.name}
                    </button>
                {/each}
            </div>
        </div>
        <div class="flex flex-col">
            <label class="text-xs font-semibold text-gray-700 mb-1"
                >Receso — hora</label
            >
            <div class="flex items-center gap-1">
                <select
                    bind:value={recess.hora}
                    class="border border-gray-300 rounded-md px-2 py-2 text-sm"
                >
                    {#each HOURS as h}
                        <option value={h}>{h}</option>
                    {/each}
                </select>
                <span>:</span>
                <select
                    bind:value={recess.minuto}
                    class="border border-gray-300 rounded-md px-2 py-2 text-sm"
                >
                    {#each MINUTES as m}
                        <option value={m}>{m}</option>
                    {/each}
                </select>
                <select
                    bind:value={recess.ampm}
                    class="border border-gray-300 rounded-md px-2 py-2 text-sm"
                >
                    <option value="AM">AM</option>
                    <option value="PM">PM</option>
                </select>
            </div>
        </div>

        <div class="flex flex-col">
            <label class="text-xs font-semibold text-gray-700 mb-1"
                >Duración receso (min)</label
            >
            <input
                type="number"
                min="0"
                bind:value={recess.duracion}
                class="border border-gray-300 rounded-md px-3 py-2 text-sm w-28"
            />
        </div>
    </div>

    <p class="text-sm text-gray-500 mt-3">
        Receso:
        {recess.hora}:{recess.minuto}
        {recess.ampm} · {recess.duracion}
        minutos (aplica a todos los días)
    </p>

    <div class="grid grid-cols-1 md:grid-cols-5 border-gray-300 border-2 rounded-xl overflow-hidden  mt-4">
        {#each DAYS as day}
            <div
                class="border border-gray-200  bg-gray-50  flex flex-col"
            >
                <h4 class="font-semibold text-gray-100 text-center bg-color1 text-sm py-2">
                    {day.label}
                </h4>

                {#each classes[day.key] || [] as row, index}
                    <div class=" flex flex-col px-3">
                        <button
                            type="button"
                            on:click={() => insertClass(day.key, index)}
                            class="flex group hover:text-black text-gray-500 items-center justify-center gap-2 py-2 "
                            title="Insertar clase antes de esta"
                        >
                            <span
                                class="hidden group-hover:block opacity-0 group-hover:opacity-100 transition-opacity duration-150 py-1 border border-dashed border-gray-400 w-full rounded bg-gray-100"
                            >
                             <iconify-icon
                                icon="ic:baseline-plus"
                                class="shrink-0 relative top-0.5 "
                                width="14"
                            ></iconify-icon>    
                        </span>
                           
                        </button>
                        <div
                            class=" p-2 border border-gray-200 rounded-lg bg-white"
                        >
                        <div class="flex items-center justify-between mb-2">
                            <span class="text-xs font-medium text-gray-500"
                                >Clase {index + 1}</span
                            >
                            <button
                                type="button"
                                on:click={() => removeClass(day.key, index)}
                                class="text-red-500 hover:text-red-700"
                                title="Quitar clase"
                            >
                                <iconify-icon icon="mdi:close" width="16"
                                ></iconify-icon>
                            </button>
                        </div>

                        <div class="text-[11px] font-medium text-gray-500 mb-1">
                            Inicio
                        </div>
                        <div class="flex items-center gap-1 mb-2">
                            <select
                                bind:value={row.start.hour}
                                class="border border-gray-300 rounded-md px-1 py-1 text-xs"
                            >
                                {#each HOURS as h}
                                    <option value={h}>{h}</option>
                                {/each}
                            </select>
                            <span class="text-xs">:</span>
                            <select
                                bind:value={row.start.minute}
                                class="border border-gray-300 rounded-md px-1 py-1 text-xs"
                            >
                                {#each MINUTES as m}
                                    <option value={m}>{m}</option>
                                {/each}
                            </select>
                            <select
                                bind:value={row.start.ampm}
                                class="border border-gray-300 rounded-md px-1 py-1 text-xs"
                            >
                                <option value="AM">AM</option>
                                <option value="PM">PM</option>
                            </select>
                        </div>

                        <div class="text-[11px] font-medium text-gray-500 mb-1">
                            Fin
                        </div>
                        <div class="flex items-center gap-1 mb-3">
                            <select
                                bind:value={row.end.hour}
                                class="border border-gray-300 rounded-md px-1 py-1 text-xs"
                            >
                                {#each HOURS as h}
                                    <option value={h}>{h}</option>
                                {/each}
                            </select>
                            <span class="text-xs">:</span>
                            <select
                                bind:value={row.end.minute}
                                class="border border-gray-300 rounded-md px-1 py-1 text-xs"
                            >
                                {#each MINUTES as m}
                                    <option value={m}>{m}</option>
                                {/each}
                            </select>
                            <select
                                bind:value={row.end.ampm}
                                class="border border-gray-300 rounded-md px-1 py-1 text-xs"
                            >
                                <option value="AM">AM</option>
                                <option value="PM">PM</option>
                            </select>
                        </div>

                        {#if startMustNotBeBefore(row, day.key, index)}
                            <p class="text-[11px] text-red-500 mb-2">
                                La hora de inicio no puede ser menor al fin de
                                la clase anterior.
                            </p>
                        {/if}

                        {#if teacherConflictInfo(row, day.key)}
                            <p class="text-[11px] text-red-500 mb-2">
                                {teacherConflictInfo(row, day.key)}
                            </p>
                        {/if}

                        <div class="mb-2">
                            <select
                                bind:value={row.matter_id}
                                class="w-full border border-gray-300 rounded-md px-2 py-1.5 text-xs"
                            >
                                <option value="">Selecciona materia</option>
                                {#each matters as matter}
                                    <option value={matter.id}
                                        >{matter.name}</option
                                    >
                                {/each}
                            </select>
                        </div>

                        <div>
                            <select
                                bind:value={row.teacher_id}
                                class="w-full border border-gray-300 rounded-md px-2 py-1.5 text-xs"
                            >
                                <option value="">— Profesor —</option>
                                {#each teachersFor(row.matter_id) as teacher}
                                    <option value={teacher.id}
                                        >{teacher.full_name}</option
                                    >
                                {/each}
                            </select>
                            {#if row.matter_id && teachersFor(row.matter_id).length === 0}
                                <p class="text-[11px] text-amber-600 mt-1">
                                    No hay profesores con esta materia.
                                </p>
                            {/if}
                        </div>
                    </div>
                    </div>
                {/each}

                <button
                    type="button"
                    on:click={() => addClass(day.key)}
                    class=" flex items-center justify-center m-3 gap-1 text-sm font-medium text-gray-600 hover:text-gray-900 border border-dashed border-gray-300 rounded-lg py-2"
                >
                    <iconify-icon icon="ic:baseline-plus"></iconify-icon>
                    Agregar clase
                </button>
            </div>
        {/each}
    </div>

    <div class="flex justify-end items-center gap-3 mt-6">
        <button
            type="button"
            on:click={showGrid}
            class="px-4 py-2 text-sm font-semibold rounded-md border border-gray-300 text-gray-600 hover:bg-gray-100 transition"
        >
            Ver vista de horario
        </button>
        <button
            type="button"
            on:click={save}
            class="animated-button w-fitcontent"
        >
            <svg
                xmlns="http://www.w3.org/2000/svg"
                class="arr-2"
                viewBox="0 0 24 24"
            >
                <path
                    d="M16.1716 10.9999L10.8076 5.63589L12.2218 4.22168L20 11.9999L12.2218 19.778L10.8076 18.3638L16.1716 12.9999H4V10.9999H16.1716Z"
                ></path>
            </svg>
            <span class="text">Guardar horario</span>
            <span class="circle"></span>
            <svg
                xmlns="http://www.w3.org/2000/svg"
                class="arr-1"
                viewBox="0 0 24 24"
            >
                <path
                    d="M16.1716 10.9999L10.8076 5.63589L12.2218 4.22168L20 11.9999L12.2218 19.778L10.8076 18.3638L16.1716 12.9999H4V10.9999H16.1716Z"
                ></path>
            </svg>
        </button>
    </div>
    {/if}
</div>
