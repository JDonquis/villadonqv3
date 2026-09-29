<script>
    import { router } from "@inertiajs/svelte";
    import axios from "axios";
    import { displayAlert } from "../stores/alertStore";

    export let planId = null;
    export let students = [];

    let attendanceSessions = [];
    let attendanceData = {}; // sessionId -> { studentId: status }
    let newSessionDate = new Date().toISOString().split("T")[0];
    let attendanceSaving = false;
    let attendanceDirty = false;
    let lastLoadedPlanId = null;

    async function loadAttendanceMatrix(id) {
        if (!id) return;
        try {
            const response = await axios.get(
                `/dashboard/mis-estudiantes/asistencia/${id}`,
            );
            const matrix = response.data.data;
            attendanceSessions = matrix.sessions || [];
            attendanceData = matrix.attendance || {};
            attendanceDirty = false;
        } catch (error) {
            console.error("Error loading attendance:", error);
            displayAlert({
                type: "error",
                message: "Error al cargar asistencia",
            });
        }
    }

    async function createSession() {
        if (!planId || !newSessionDate) return;

        try {
            const response = await axios.post(
                "/dashboard/mis-estudiantes/asistencia/session",
                {
                    plan_id: planId,
                    date: newSessionDate,
                },
            );
            const session = response.data.data;
            attendanceSessions = [
                ...attendanceSessions,
                {
                    id: session.id,
                    date: session.date,
                    order: session.order,
                    day_of_week: formatDayOfWeek(session.date),
                },
            ].sort(
                (a, b) =>
                    a.order - b.order || a.date.localeCompare(b.date),
            );

            // Initialize attendance for new session
            attendanceData = {
                ...attendanceData,
                [session.id]: {},
            };
            attendanceDirty = true;
            displayAlert({
                type: "success",
                message: "Sesión creada correctamente",
            });
        } catch (error) {
            console.error("Error creating session:", error);
            displayAlert({ type: "error", message: "Error al crear sesión" });
        }
    }

    async function deleteSession(sessionId) {
        if (
            !confirm(
                "¿Eliminar esta sesión de asistencia? Se borrarán todos los registros.",
            )
        )
            return;

        try {
            await axios.delete(
                `/dashboard/mis-estudiantes/asistencia/session/${sessionId}`,
            );
            attendanceSessions = attendanceSessions.filter(
                (s) => s.id !== sessionId,
            );
            const nextData = { ...attendanceData };
            delete nextData[sessionId];
            attendanceData = nextData;
            // Only dirty when there is something left to persist.
            attendanceDirty = attendanceSessions.length > 0;
            displayAlert({ type: "success", message: "Sesión eliminada" });
        } catch (error) {
            console.error("Error deleting session:", error);
            displayAlert({
                type: "error",
                message: "Error al eliminar sesión",
            });
        }
    }

    function toggleAttendance(sessionId, studentId) {
        const current = attendanceData[sessionId]?.[studentId] || "absent";
        const next =
            current === "absent"
                ? "present"
                : current === "present"
                  ? "excused"
                  : "absent";

        attendanceData = {
            ...attendanceData,
            [sessionId]: {
                ...attendanceData[sessionId],
                [studentId]: next,
            },
        };
        attendanceDirty = true;
    }

    function isAllPresent(sessionId) {
        return !!allPresentBySession[sessionId];
    }

    // `attendanceData` is passed explicitly so Svelte tracks it as a dependency
    // of this reactive statement (reads inside functions are not tracked).
    function buildAllPresentMap(attendance, sessions, studentList) {
        const studentIds = studentList.map((student) => student.id);
        return sessions.reduce((acc, session) => {
            const sessionData = attendance[session.id] || {};
            acc[session.id] =
                studentIds.length > 0 &&
                studentIds.every(
                    (studentId) => sessionData[studentId] === "present",
                );
            return acc;
        }, {});
    }

    $: allPresentBySession = buildAllPresentMap(
        attendanceData,
        attendanceSessions,
        students,
    );

    function toggleAllInSession(sessionId) {
        const studentIds = students.map((student) => student.id);
        if (!studentIds.length) return;

        const target = isAllPresent(sessionId) ? "absent" : "present";

        attendanceData = {
            ...attendanceData,
            [sessionId]: Object.fromEntries(
                studentIds.map((studentId) => [studentId, target]),
            ),
        };
        attendanceDirty = true;
    }

    function formatDayOfWeek(dateStr) {
        const date = new Date(`${dateStr}T00:00:00`);
        return date
            .toLocaleDateString("es-VE", { weekday: "short" })
            .replace(/\./g, "")
            .trim();
    }

    function getAttendanceClass(status) {
        return status === "present"
            ? "bg-green/20 text-green"
            : status === "excused"
              ? "bg-orange/20 text-orange"
              : "bg-gray-50 text-gray-300";
    }

    function formatDate(dateStr) {
        if (!dateStr) return "—";
        const date = new Date(`${dateStr}T00:00:00`);
        return date.toLocaleDateString("es-VE", {
            day: "2-digit",
            month: "2-digit",
        });
    }

    async function saveAttendance() {
        if (!planId) return;

        attendanceSaving = true;

        // Build records array
        const records = [];
        attendanceSessions.forEach((session) => {
            Object.entries(attendanceData[session.id] || {}).forEach(
                ([studentId, status]) => {
                    records.push({
                        session_id: session.id,
                        student_id: parseInt(studentId),
                        status,
                    });
                },
            );
        });

        try {
            await router.post(
                "/dashboard/mis-estudiantes/asistencia/save",
                {
                    plan_id: planId,
                    records,
                },
                {
                    preserveScroll: true,
                    onSuccess: () => {
                        attendanceDirty = false;
                        attendanceSaving = false;
                        displayAlert({
                            type: "success",
                            message: "Asistencia guardada correctamente",
                        });
                    },
                    onError: (errors) => {
                        attendanceSaving = false;
                        displayAlert({
                            type: "error",
                            message:
                                errors.message ||
                                "Error al guardar asistencia",
                        });
                    },
                },
            );
        } catch (error) {
            attendanceSaving = false;
            console.error("Error saving attendance:", error);
            displayAlert({
                type: "error",
                message: "Error al guardar asistencia",
            });
        }
    }

    // Load once on mount and whenever the selected plan changes.
    $: if (planId && planId !== lastLoadedPlanId) {
        lastLoadedPlanId = planId;
        loadAttendanceMatrix(planId);
    }
</script>

<div class="bg-white border border-gray-200 rounded-lg shadow overflow-hidden">
    <!-- Add Session Bar -->
    <div
        class="p-4 bg-gray-50 border-b border-gray-200 flex flex-col md:flex-row md:items-center gap-4"
    >
        <div class="flex items-center gap-3">
            <label class="text-sm font-semibold text-gray-700"
                >Nueva sesión:</label
            >
            <input
                type="date"
                bind:value={newSessionDate}
                max={new Date().toISOString().split("T")[0]}
                class="rounded-md border border-gray-300 px-3 py-2 text-sm"
            />
            <button
                on:click={createSession}
                class="bg-color1 text-white px-4 py-2 rounded-md hover:bg-color1/90 text-sm"
                disabled={!newSessionDate}
            >
                Agregar sesión
            </button>
        </div>
    </div>

    {#if attendanceSessions.length === 0}
        <div class="p-8 text-center text-gray-400">
            No hay sesiones de asistencia. Agrega una sesión para comenzar.
        </div>
    {:else}
        <div class="overflow-x-auto">
            <!-- table-fixed: respeta el ancho de la primera columna en vez de
                 estirarla con el nombre más largo (truncate = white-space: nowrap
                 convertía el min-content de la celda en el ancho completo del texto). -->
            <table class="text-sm table-fixed">
                <thead class="bg-gray-50">
                    <tr>
                        <th
                            class="px-2 py-2.5 md:px-5 md:py-3.5 text-left sticky left-0 z-20 bg-gray-50/95 backdrop-blur-sm w-[104px] min-w-[104px] max-w-[104px] sm:w-[220px] sm:min-w-[220px] sm:max-w-[220px] shadow-[1px_0_0_rgba(199,210,218,0.4)]"
                        >
                            <div
                                class="font-semibold text-gray-800 text-[11px] md:text-sm truncate"
                            >
                                Estudiante
                            </div>
                        </th>
                        {#each attendanceSessions as session}
                            <th
                                class="px-1 py-3 w-[48px] min-w-[48px] max-w-[48px] md:px-2 text-center justify-center relative group"
                            >
                                <div class="flex flex-col items-center gap-1">
                                    <span
                                        class="font-semibold text-gray-800 text-[10px] md:text-sm leading-tight text-center"
                                        >{formatDate(session.date)}</span
                                    >
                                    <span
                                        class="text-[10px] md:text-xs text-gray-500 capitalize leading-tight"
                                        >{session.day_of_week}</span
                                    >
                                    <button
                                        class="absolute top-1 right-1 text-[10px] px-1.5 py-0.5 rounded bg-red-100 text-red-700 hover:bg-red-200 opacity-0 group-hover:opacity-100 transition-opacity"
                                        on:click={() =>
                                            deleteSession(session.id)}
                                        title="Eliminar sesión"
                                    >
                                        ✕
                                    </button>
                                </div>
                                <!-- Mark All Toggle -->
                                <input
                                    type="checkbox"
                                    class="h-4 w-4 accent-green-600 cursor-pointer"
                                    checked={allPresentBySession[session.id]}
                                    title="marcar /desmarcar todos"
                                    on:change={() =>
                                        toggleAllInSession(session.id)}
                                    aria-label={`Marcar todos de la sesión ${session.date}`}
                                />
                            </th>
                        {/each}
                    </tr>
                </thead>
                <tbody>
                    {#each students as student}
                        <tr class="transition-colors border-b ">
                            <td
                                class="px-2 md:px-5 py-2 sticky left-0 bg-white z-10 w-[104px] min-w-[104px] max-w-[104px] sm:w-[220px] sm:min-w-[220px] sm:max-w-[220px] shadow-[1px_0_0_rgba(199,210,218,0.4)]"
                            >
                                <p
                                    class="font-semibold truncate text-gray-800 capitalize text-[11px] md:text-sm leading-tight"
                                >
                                    {student.last_name}, {student.name}
                                </p>
                                <p
                                    class="text-[10px] md:text-xs text-gray-400 truncate"
                                >
                                    C.I {student.ci}
                                </p>
                            </td>
                            {#each attendanceSessions as session}
                                {@const status =
                                    attendanceData[session.id]?.[student.id] ||
                                    "absent"}
                                <td
                                    class="px-1 w-[48px] min-w-[48px] max-w-[48px] md:px-2 aspect-square h-[44px] min-h-[44px] py-2 text-center"
                                >
                                    <button
                                        class="w-[44px] hover:text-gray-400 hover:bg-gray-200 aspect-square h-[44px] min-h-[44px] flex items-center justify-center text-xl md:text-2xl transition-colors rounded {getAttendanceClass(
                                            status,
                                        )}"
                                        on:click={() =>
                                            toggleAttendance(
                                                session.id,
                                                student.id,
                                            )}
                                    >
                                        {#if status === "present"}
                                            <iconify-icon
                                                icon="mdi:check-bold"
                                                class="text-2xl"
                                            ></iconify-icon>
                                        {:else if status === "excused"}
                                            J
                                        {:else}
                                            —
                                        {/if}
                                    </button>
                                </td>
                            {/each}
                        </tr>
                    {/each}
                </tbody>
            </table>
        </div>
    {/if}
</div>

<!-- Save Button -->
<div
    class="mt-4 fixed bottom-16 md:bottom-8 right-10 gap-3 flex justify-end max-w-[600px] ml-auto items-center"
>
    {#if attendanceDirty}
        <button
            on:click={saveAttendance}
            class="animated-button flex items-center gap-3"
            disabled={attendanceSaving}
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
            {#if attendanceSaving}
                <span class="text">Guardando...</span>
            {:else}
                <iconify-icon
                    icon="material-symbols:save"
                    class="text"
                    width="20"
                    height="20"
                ></iconify-icon>
                <span class="text hidden md:inline px-2">Guardar asistencia</span>
            {/if}
            <span class="circle"></span>
           
        </button>
    {/if}
</div>
