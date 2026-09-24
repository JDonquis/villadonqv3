<script>
    import { useForm, router } from "@inertiajs/svelte";
    import Alert from "../../components/Alert.svelte";
    import { displayAlert } from "../../stores/alertStore";
    import axios from "axios";

    export let data = [];

    // View mode: 'notas' | 'asistencia'
    let viewMode = 'notas';

    // Grades state
    let editable = {};
    let rasgosEditable = {};

    // Attendance state
    let attendanceSessions = [];
    let attendanceData = {}; // sessionId -> { studentId: status }
    let newSessionDate = new Date().toISOString().split('T')[0];
    let attendanceSaving = false;
    let attendanceDirty = false;
    let lastLoadedPlanId = null;

    function getInitialGrades(matrix) {
        const grades = [];
        matrix?.students?.forEach((student) => {
            matrix.items.forEach((item) => {
                const value = student.scores[item.id];
                grades.push({
                    plan_item_id: item.id,
                    student_id: student.id,
                    score: value != null ? parseFloat(value) : null,
                });
            });
        });
        return grades;
    }

    function getGradesFromEditable() {
        const grades = [];
        data.matrix?.students?.forEach((student) => {
            data.matrix.items.forEach((item) => {
                const value = editable[`${student.id}_${item.id}`];
                grades.push({
                    plan_item_id: item.id,
                    student_id: student.id,
                    score:
                        value === "" || value === null || value === undefined
                            ? null
                            : parseFloat(value),
                });
            });
        });
        return grades;
    }

    function rebuildEditable(matrix) {
        editable = {};
        rasgosEditable = {};
        matrix?.students?.forEach((s) => {
            rasgosEditable[s.id] =
                s.rasgos != null && s.rasgos !== "" ? String(s.rasgos) : "";
            matrix.items.forEach((item) => {
                const key = `${s.id}_${item.id}`;
                const val = s.scores[item.id];
                editable[key] = val != null ? String(val) : "";
            });
        });
    }

    let sortState = {
        key: "student",
        direction: "asc",
    };
    let definitiveByStudent = {};

    function toggleSort(key) {
        if (sortState.key === key) {
            sortState.direction =
                sortState.direction === "asc" ? "desc" : "asc";
            return;
        }

        sortState.key = key;
        sortState.direction = "asc";
    }

    function getSortIndicator(key) {
        if (sortState.key !== key) return "↕";
        return sortState.direction === "asc" ? "▲" : "▼";
    }

    $: if (data?.matrix?.plan?.id) rebuildEditable(data.matrix);
    $: if (data?.matrix) {
        // Register reactive deps so the definitive refreshes while typing.
        void editable;
        void rasgosEditable;
        void planRasgosMax;
        definitiveByStudent = {};
        for (const student of data.matrix.students || []) {
            definitiveByStudent[student.id] = computeDefinitive(student);
        }
    }
    $: sortedStudents = [...(data.matrix?.students || [])].sort((a, b) => {
        const direction = sortState.direction === "asc" ? 1 : -1;

        if (sortState.key === "student") {
            const lastNameCompare = (a.last_name || "").localeCompare(
                b.last_name || "",
                "es",
                { sensitivity: "base" },
            );
            if (lastNameCompare !== 0) return lastNameCompare * direction;
            return (
                (a.name || "").localeCompare(b.name || "", "es", {
                    sensitivity: "base",
                }) * direction
            );
        }

        if (sortState.key === "definitive") {
            const aVal = definitiveByStudent[a.id];
            const bVal = definitiveByStudent[b.id];
            if (aVal === null && bVal === null) return 0;
            if (aVal === null) return 1 * direction;
            if (bVal === null) return -1 * direction;
            return (aVal - bVal) * direction;
        }

        const itemId = Number(sortState.key.replace("item_", ""));
        const aVal = a.scores?.[itemId] ?? null;
        const bVal = b.scores?.[itemId] ?? null;

        if (aVal === null && bVal === null) return 0;
        if (aVal === null) return 1 * direction;
        if (bVal === null) return -1 * direction;

        return (Number(aVal) - Number(bVal)) * direction;
    });

    let form = useForm({
        plan_id: data.selected_plan_id || "",
        grades: getInitialGrades(data.matrix),
        rasgos: [],
    });

    $: canPublish = data.matrix?.grade_state?.can_publish === true;
    $: planRasgosMax = data.matrix?.plan?.rasgos_points || 0;

    function updateGrade(key, value) {
        editable[key] = value;
        editable = { ...editable };
        $form.grades = getGradesFromEditable();
    }

    function updateRasgos(studentId, value) {
        rasgosEditable[studentId] = value;
        rasgosEditable = { ...rasgosEditable };
        const planId = data.matrix?.plan?.id;
        $form.rasgos = (data.matrix?.students || []).map((s) => {
            const raw = rasgosEditable[s.id];
            return {
                plan_id: planId,
                student_id: s.id,
                rasgos_score:
                    raw === "" || raw === null || raw === undefined
                        ? null
                        : parseInt(raw, 10),
            };
        });
    }

    function getActiveMomentId(schoolLapse) {
        if (!schoolLapse?.lapses?.length) return "";

        const today = new Date().toISOString().slice(0, 10);
        const active = schoolLapse.lapses.find(
            (lap) => today >= (lap.start || "") && today <= (lap.end || ""),
        );

        return String(
            active?.id ??
                schoolLapse.lapses[schoolLapse.lapses.length - 1]?.id ??
                "",
        );
    }

    $: selectedSchoolLapse =
        (data.school_lapses || []).find(
            (l) => String(l.id) === String(data.school_lapse_id),
        ) ||
        (data.school_lapses || [])[0] ||
        null;
    $: momentOptions = selectedSchoolLapse?.lapses || [];

    // Store the currently selected moment. We default to the server value
    // / active-by-date only when the user hasn't made a manual selection.
    let selectedLapseId = "";
    let userSelectedLapse = false;

    $: if (!userSelectedLapse) {
        const serverLapse = String(
            data.lapse_id || getActiveMomentId(selectedSchoolLapse) || "",
        );
        if (selectedLapseId !== serverLapse) selectedLapseId = serverLapse;
    }

    function selectSchoolLapse(schoolLapseId) {
        const nextSchool = (data.school_lapses || []).find(
            (l) => String(l.id) === String(schoolLapseId),
        );
        const nextMoment = getActiveMomentId(nextSchool);
        // Reset user selection when switching school lapse so the new
        // period's default moment can be applied.
        userSelectedLapse = false;
        router.get(
            "/dashboard/mis-estudiantes",
            {
                school_lapse_id: schoolLapseId,
                lapse_id: nextMoment,
                plan_id: "",
            },
            {
                preserveState: true,
                replace: true,
            },
        );
    }

    function selectMoment(momentId) {
        // Mark that the user explicitly chose a moment so we don't
        // overwrite their selection with the date-based default.
        userSelectedLapse = true;
        if (selectedLapseId !== momentId) selectedLapseId = momentId;

        router.get(
            "/dashboard/mis-estudiantes",
            {
                school_lapse_id: data.school_lapse_id || "",
                lapse_id: momentId,
                plan_id: "",
            },
            {
                preserveState: true,
                replace: true,
            },
        );
    }

    function selectPlan(planId) {
        router.get(
            "/dashboard/mis-estudiantes",
            {
                school_lapse_id: data.school_lapse_id || "",
                lapse_id: data.lapse_id || selectedLapseId || "",
                plan_id: planId,
            },
            {
                preserveState: true,
                replace: true,
            },
        );
    }

    function formatTooltipDate(date) {
        if (!date) return "—";

        const parsed = new Date(`${date}T00:00:00`);
        if (Number.isNaN(parsed.getTime())) return "—";

        return parsed.toLocaleDateString("es-VE", {
            weekday: "short",
            day: "numeric",
            month: "short",
        });
    }

    function getUnitMetaForItem(item) {
        const unit = (data.matrix?.units || []).find((entry) =>
            (entry.topics || []).some(
                (topic) =>
                    String(topic.id) === `topic_${item.id}` ||
                    (topic.name === item.name &&
                        Number(topic.percentage) === Number(item.percentage)),
            ),
        );

        return {
            unit_number: unit?.unit_number ?? item.unit_number ?? 1,
            unit_name: unit?.name ?? item.unit_name ?? "Sin unidad",
            assessment_type:
                unit?.topics?.find(
                    (topic) =>
                        String(topic.id) === `topic_${item.id}` ||
                        (topic.name === item.name &&
                            Number(topic.percentage) ===
                                Number(item.percentage)),
                )?.assessment_type ??
                item.assessment_type ??
                "—",
            scheduled_date:
                unit?.topics?.find(
                    (topic) =>
                        String(topic.id) === `topic_${item.id}` ||
                        (topic.name === item.name &&
                            Number(topic.percentage) ===
                                Number(item.percentage)),
                )?.scheduled_date ??
                item.scheduled_date ??
                null,
        };
    }

    function computeDefinitive(student) {
        if (!data.matrix) return null;

        let total = 0;
        for (const item of data.matrix.items) {
            const val = editable[`${student.id}_${item.id}`];
            if (val === "" || val === null || val === undefined) return null;
            const num = parseFloat(val);
            if (isNaN(num)) return null;
            total += num * (item.percentage / 100);
        }

        if (planRasgosMax > 0) {
            const raw = rasgosEditable[student.id];
            if (raw === "" || raw === null || raw === undefined) return null;
            const rasgosNum = parseFloat(raw);
            if (isNaN(rasgosNum)) return null;
            total += rasgosNum;
        }

        return Math.round(total * 100) / 100;
    }

    function getDefinitiveMedal(student) {
        if (!data.matrix) return null;

        const studentScore = definitiveByStudent[student.id];
        if (studentScore === null || studentScore < 10) return null;

        const scores = data.matrix.students
            .map((entry) => ({
                id: entry.id,
                score: definitiveByStudent[entry.id],
            }))
            .filter((entry) => entry.score !== null && entry.score >= 10)
            .sort((a, b) => b.score - a.score);

        if (!scores.length) return null;

        const uniqueScores = [...new Set(scores.map((entry) => entry.score))];
        const medalByScore = new Map();

        uniqueScores.forEach((score, index) => {
            if (index === 0) medalByScore.set(score, "gold");
            else if (index === 1) medalByScore.set(score, "silver");
            else if (index === 2) medalByScore.set(score, "bronze");
        });

        return medalByScore.get(studentScore) ?? null;
    }

    function getMedalIcon(medal) {
        if (medal === "gold")
            return {
                icon: "fluent-emoji-flat:1st-place-medal",
                class: "text-2xl",
            };
        if (medal === "silver")
            return {
                icon: "fluent-emoji-flat:2nd-place-medal",
                class: "text-xl",
            };
        if (medal === "bronze")
            return {
                icon: "fluent-emoji-flat:3rd-place-medal",
                class: "text-xl",
            };
        return "";
    }

    function handleSave() {
        if (!data.matrix) return;

        const grades = [];
        data.matrix.students.forEach((s) => {
            data.matrix.items.forEach((item) => {
                const val = editable[`${s.id}_${item.id}`];
                grades.push({
                    plan_item_id: item.id,
                    student_id: s.id,
                    score:
                        val === "" || val === null || val === undefined
                            ? null
                            : parseFloat(val),
                });
            });
        });

        const rasgos = [];
        data.matrix.students.forEach((s) => {
            const raw = rasgosEditable[s.id];
            rasgos.push({
                student_id: s.id,
                rasgos_score:
                    raw === "" || raw === null || raw === undefined
                        ? null
                        : parseInt(raw, 10),
            });
        });

        $form.clearErrors();
        $form.plan_id = data.matrix.plan.id;
        $form.grades = grades;
        $form.rasgos = rasgos;
        $form.post("/dashboard/mis-estudiantes/guardar-notas", {
            preserveScroll: true,
            onSuccess: () => {
                $form.defaults();
                $form.reset();
                displayAlert({
                    type: "success",
                    message: "Notas guardadas correctamente",
                });
            },
            onError: (errors) => {
                displayAlert({
                    type: "error",
                    message: errors.message || "Error al guardar las notas",
                });
            },
        });
    }

    function handlePublish() {
        if (!data.matrix?.plan?.id || !canPublish) return;

        $form.plan_id = data.matrix.plan.id;
        $form.post("/dashboard/mis-estudiantes/publicar-notas", {
            preserveScroll: true,
            onSuccess: () => {
                displayAlert({
                    type: "success",
                    message: "Notas publicadas correctamente",
                });
            },
            onError: (errors) => {
                displayAlert({
                    type: "error",
                    message: errors.message || "Error al publicar las notas",
                });
            },
        });
    }

    // ===== ATTENDANCE FUNCTIONS =====

    async function loadAttendanceMatrix(planId) {
        try {
            const response = await axios.get(`/dashboard/mis-estudiantes/asistencia/${planId}`);
            const matrix = response.data.data;
            attendanceSessions = matrix.sessions || [];
            attendanceData = matrix.attendance || {};
            attendanceDirty = false;
        } catch (error) {
            console.error('Error loading attendance:', error);
            displayAlert({ type: 'error', message: 'Error al cargar asistencia' });
        }
    }

    async function createSession() {
        if (!data.matrix?.plan?.id || !newSessionDate) return;

        try {
            const response = await axios.post('/dashboard/mis-estudiantes/asistencia/session', {
                plan_id: data.matrix.plan.id,
                date: newSessionDate,
            });
            const session = response.data.data;
            attendanceSessions = [...attendanceSessions, {
                id: session.id,
                date: session.date,
                order: session.order,
                day_of_week: formatDayOfWeek(session.date),
            }].sort((a, b) => a.order - b.order || a.date.localeCompare(b.date));
            
            // Initialize attendance for new session
            attendanceData[session.id] = {};
            attendanceDirty = true;
            displayAlert({ type: 'success', message: 'Sesión creada correctamente' });
        } catch (error) {
            console.error('Error creating session:', error);
            displayAlert({ type: 'error', message: 'Error al crear sesión' });
        }
    }

    async function deleteSession(sessionId) {
        if (!confirm('¿Eliminar esta sesión de asistencia? Se borrarán todos los registros.')) return;

        try {
            await axios.delete(`/dashboard/mis-estudiantes/asistencia/session/${sessionId}`);
            attendanceSessions = attendanceSessions.filter(s => s.id !== sessionId);
            delete attendanceData[sessionId];
            attendanceDirty = true;
            displayAlert({ type: 'success', message: 'Sesión eliminada' });
        } catch (error) {
            console.error('Error deleting session:', error);
            displayAlert({ type: 'error', message: 'Error al eliminar sesión' });
        }
    }

    function toggleAttendance(sessionId, studentId) {
        const current = attendanceData[sessionId]?.[studentId] || 'absent';
        const next = current === 'absent' ? 'present' :
                     current === 'present' ? 'excused' : 'absent';
        
        attendanceData[sessionId] = { ...attendanceData[sessionId], [studentId]: next };
        attendanceData = { ...attendanceData };
        attendanceDirty = true;
    }

    function toggleAllInSession(sessionId) {
        const studentIds = (data.matrix?.students || []).map((student) => student.id);
        if (!studentIds.length) return;

        const sessionData = attendanceData[sessionId] || {};
        const allPresent = studentIds.length > 0 && studentIds.every((studentId) => sessionData[studentId] === 'present');
        const target = allPresent ? 'absent' : 'present';

        attendanceData[sessionId] = Object.fromEntries(
            studentIds.map((studentId) => [studentId, target]),
        );
        attendanceData = { ...attendanceData };
        attendanceDirty = true;

    }

    function formatDayOfWeek(dateStr) {
        const date = new Date(`${dateStr}T00:00:00`);
        return date.toLocaleDateString('es-VE', { weekday: 'short' }).replace(/\./g, '').trim();
    }

    function getAttendanceClass(status) {
        return status === 'present' ? 'bg-green-50 text-green-600' :
               status === 'excused' ? 'bg-orange-50 text-orange-600' :
               'bg-gray-50 text-gray-300';
    }

    function renderAttendanceIcon(status) {
        if (status === 'present') return '<iconify-icon icon="mdi:check-bold" class="text-2xl"></iconify-icon>';
        if (status === 'excused') return 'J';
        return '—';
    }

    function isAllPresent(sessionId) {
        const studentIds = (data.matrix?.students || []).map((student) => student.id);
        if (!studentIds.length) return false;

        const sessionData = attendanceData[sessionId] || {};

       return studentIds.every((studentId) => sessionData[studentId] === 'present');
    }

    function formatDate(dateStr) {
        if (!dateStr) return '—';
        const date = new Date(`${dateStr}T00:00:00`);
        return date.toLocaleDateString('es-VE', { day: '2-digit', month: '2-digit' });
    }

    async function saveAttendance() {
        if (!data.matrix?.plan?.id) return;
        
        attendanceSaving = true;
        
        // Build records array
        const records = [];
        attendanceSessions.forEach(session => {
            Object.entries(attendanceData[session.id] || {}).forEach(([studentId, status]) => {
                records.push({
                    session_id: session.id,
                    student_id: parseInt(studentId),
                    status,
                });
            });
        });

        try {
            await router.post('/dashboard/mis-estudiantes/asistencia/save', {
                plan_id: data.matrix.plan.id,
                records,
            }, {
                preserveScroll: true,
                onSuccess: () => {
                    attendanceDirty = false;
                    attendanceSaving = false;
                    displayAlert({ type: 'success', message: 'Asistencia guardada correctamente' });
                },
                onError: (errors) => {
                    attendanceSaving = false;
                    displayAlert({ type: 'error', message: errors.message || 'Error al guardar asistencia' });
                },
            });
        } catch (error) {
            attendanceSaving = false;
            console.error('Error saving attendance:', error);
            displayAlert({ type: 'error', message: 'Error al guardar asistencia' });
        }
    }

    // Watch for plan change to load attendance
    $: if (viewMode === 'asistencia' && data.matrix?.plan?.id && data.matrix.plan.id !== lastLoadedPlanId) {
        lastLoadedPlanId = data.matrix.plan.id;
        loadAttendanceMatrix(data.matrix.plan.id);
    }

    // Reset attendance state when switching away from asistencia or changing plan
    $: if (viewMode !== 'asistencia') {
        attendanceSessions = [];
        attendanceData = {};
        attendanceDirty = false;
    }
</script>

<svelte:head>
    <title>Mis Estudiantes</title>
</svelte:head>

<Alert />

<div class="flex justify-between items-center mb-4 flex-wrap gap-2">
    <h2 class="text-xl md:text-2xl font-bold text-color1 sm:hidden">Mis Estudiantes</h2>
</div>

<div
    class="bg-white border border-gray-200 rounded-lg p-4 mb-4 flex flex-col md:flex-row flex-wrap md:items-center gap-5 md:gap-6"
>
    <div class="flex flex-col md:flex-row md:items-center gap-2">
        <label class="text-sm font-semibold text-gray-600">
            Período escolar
        </label>
        <select
            class="rounded-md border border-gray-300 px-3 py-2 text-sm"
            value={String(data.school_lapse_id || "")}
            on:change={(e) => selectSchoolLapse(e.target.value)}
        >
            {#each data.school_lapses || [] as lapse}
                <option value={String(lapse.id)}>{lapse.label}</option>
            {/each}
        </select>
    </div>

    <div class="flex flex-col md:flex-row md:items-center gap-2">
        <label class="text-sm font-semibold text-gray-600">
            Momento escolar
        </label>
        <select
            class="rounded-md border border-gray-300 px-3 py-2 text-sm"
            value={selectedLapseId}
            on:change={(e) => selectMoment(e.target.value)}
        >
            {#each momentOptions as lap}
                <option value={String(lap.id)}>{lap.label}</option>
            {/each}
        </select>
    </div>

    <div class="flex-col md:flex-row items-center gap-2">
        <label class="text-sm font-semibold text-gray-600">
            Plan de evaluación
        </label>
        <select
            class="rounded-md border border-gray-300 px-3 py-2 text-sm min-w-[260px]"
            value={data.selected_plan_id || ""}
            on:change={(e) => selectPlan(e.target.value)}
        >
            {#each data.plans as plan}
                <option value={plan.id}>
                    {plan.matter_name} · {plan.course_name} · {plan.section_name}
                </option>
            {/each}
        </select>
    </div>
</div>

<!-- View Mode Toggle -->
<div class="flex gap-2 mb-4">
    <button
        class={`px-4 py-2 rounded-lg font-medium transition ${
            viewMode === 'notas' 
                ? 'bg-yellow text-gray-900 shadow' 
                : 'bg-white opacity-70 text-gray-700 hover:bg-gray-200'
        }`}
        on:click={() => viewMode = 'notas'}
    >
        Notas
    </button>
    <button
        class={`px-4 py-2 rounded-lg font-medium transition ${
            viewMode === 'asistencia' 
                ? 'bg-yellow text-gray-900 shadow' 
                : 'bg-white opacity-70 text-gray-700 hover:bg-gray-200'
        }`}
        on:click={() => {
            viewMode = 'asistencia';
            if (data.matrix?.plan?.id && !attendanceSessions.length) {
                loadAttendanceMatrix(data.matrix.plan.id);
            }
        }}
    >
        Asistencia
    </button>
</div>
{#if !data.plans?.length}
    <div
        class="bg-white border border-gray-200 rounded-lg p-8 text-center text-gray-400"
    >
        Aún no tienes planes de evaluación. Crea un plan en "Planes de
        Evaluación" para poder calificar estudiantes.
    </div>
{/if}

{#if data.matrix && viewMode === 'notas'}
    {#if data.matrix.students.length === 0}
        <div
            class="bg-white border border-gray-200 rounded-lg p-8 text-center text-gray-400"
        >
            No hay estudiantes en {data.matrix.plan.course_name} · Sección
            {data.matrix.plan.section_name}.
        </div>
    {:else}
        <div
            class="bg-white  mb-14  rounded-lg shadow overflow-x-auto"
        >
            <table class="w-full text-sm ">
                <thead class="bg-gray-50">
                    <tr>
                        <th
                            class="px-3 py-3 text-left sticky left-0 bg-gray-50"
                        >
                            <button
                                type="button"
                                class="flex items-center gap-1 font-semibold text-left"
                                on:click={() => toggleSort("student")}
                            >
                                <span>Estudiante</span>
                                <span class="text-[10px] text-gray-500">
                                    {getSortIndicator("student")}
                                </span>
                            </button>
                        </th>
                        {#each data.matrix.items as item}
                            {@const meta = getUnitMetaForItem(item)}
                            <th
                                class="px-3 py-3 text-left min-w-[150px] relative group"
                                title=""
                            >
                                <button
                                    type="button"
                                    class="flex w-full items-center justify-between gap-2 text-left"
                                    on:click={() =>
                                        toggleSort(`item_${item.id}`)}
                                >
                                    <span>
                                        <span
                                            class="font-semibold block text-xs"
                                            >{item.name}</span
                                        >
                                        <span
                                            class="text-xs text-gray-400 font-normal"
                                        >
                                            {item.percentage}%
                                        </span>
                                    </span>
                                    <span class="text-[10px] text-gray-500">
                                        {getSortIndicator(`item_${item.id}`)}
                                    </span>
                                </button>
                                <div
                                    class="pointer-events-none absolute left-1/2 top-12 z-20 mt-2 w-64 -translate-x-1/2 rounded-lg border border-gray-200 bg-white p-2 text-left text-[11px] text-gray-700 opacity-0 shadow-lg transition-opacity duration-150 group-hover:opacity-100"
                                >
                                    <div class="font-semibold text-gray-800">
                                        Unidad {meta.unit_number}: "{meta.unit_name}"
                                    </div>
                                    <div class="mt-1">
                                        Tipo: {meta.assessment_type}
                                    </div>
                                    <div class="mt-1">
                                        Fecha: {formatTooltipDate(
                                            meta.scheduled_date,
                                        )}
                                    </div>
                                </div>
                            </th>
                        {/each}
                        {#if planRasgosMax > 0}
                            <th
                                class="px-3 py-3 text-center bg-gray-50"
                                title="Puntos de rasgos (conducta)"
                            >
                                <span class="font-semibold"
                                    >Rasgos ({planRasgosMax})</span
                                >
                            </th>
                        {/if}
                        <th class="px-3 py-3 text-left bg-gray-50">
                            <button
                                type="button"
                                class="flex items-center gap-1 font-semibold text-left"
                                on:click={() => toggleSort("definitive")}
                            >
                                <span
                                    >Definitiva ({data.matrix.plan
                                        .lapse_label})</span
                                >
                                <span class="text-[10px] text-gray-500">
                                    {getSortIndicator("definitive")}
                                </span>
                            </button>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    {#each sortedStudents as student}
                        {@const definitive = definitiveByStudent[student.id]}
                        <tr class="border-t border-gray-100">
                            <td class="px-3 py-2 sticky left-0 bg-white">
                                <p
                                    class="font-semibold text-gray-800 capitalize"
                                >
                                    {student.last_name}, {student.name}
                                </p>
                                <p class="text-xs text-gray-400">
                                    C.I {student.ci}
                                </p>
                            </td>
                            {#each data.matrix.items as item}
                                <td class="px-3 py-2">
                                    <input
                                        type="number"
                                        min="0"
                                        max="20"
                                        step="0.5"
                                        class="w-20 rounded-md border border-gray-300 px-2 py-1.5 text-sm"
                                        inputmode="decimal"
                                        on:wheel|preventDefault
                                        on:focus={(e) =>
                                            e.currentTarget.select()}
                                        on:keydown={(e) => {
                                            if (
                                                [
                                                    "ArrowUp",
                                                    "ArrowDown",
                                                    "PageUp",
                                                    "PageDown",
                                                    "Home",
                                                    "End",
                                                ].includes(e.key)
                                            ) {
                                                e.stopPropagation();
                                            }
                                        }}
                                        value={editable[
                                            `${student.id}_${item.id}`
                                        ]}
                                        on:input={(event) =>
                                            updateGrade(
                                                `${student.id}_${item.id}`,
                                                event.currentTarget.value,
                                            )}
                                    />
                                </td>
                            {/each}
                            {#if planRasgosMax > 0}
                                <td class="px-3 py-2 text-center bg-gray-50">
                                    <input
                                        type="number"
                                        min="0"
                                        max={planRasgosMax}
                                        step="1"
                                        class="w-16 rounded-md border border-gray-300 px-2 py-1.5 text-sm text-center"
                                        inputmode="numeric"
                                        title="Puntos de rasgos (0-{planRasgosMax})"
                                        value={rasgosEditable[student.id]}
                                        on:input={(event) =>
                                            updateRasgos(
                                                student.id,
                                                event.currentTarget.value,
                                            )}
                                    />
                                </td>
                            {/if}
                            <td class="px-3 py-2 bg-gray-50">
                                {#if definitive !== null}
                                    {@const medal = getDefinitiveMedal(student)}
                                    <div class="flex items-center gap-2">
                                        {#if medal}
                                            <span
                                                class="text-lg leading-none"
                                                title={medal === "gold"
                                                    ? "Primero"
                                                    : medal === "silver"
                                                      ? "Segundo"
                                                      : "Tercero"}
                                            >
                                                <iconify-icon
                                                    icon={getMedalIcon(medal)
                                                        .icon}
                                                    class={getMedalIcon(medal)
                                                        .class}
                                                ></iconify-icon>
                                            </span>
                                        {/if}
                                        <span
                                            class="font-bold {definitive >= 10
                                                ? 'text-green-600'
                                                : 'text-red'}"
                                        >
                                            {definitive}
                                        </span>
                                        <span
                                            class="ml-1 text-xs px-2 py-0.5 rounded font-bold {definitive >=
                                            10
                                                ? 'bg-green-100 text-green-700'
                                                : 'bg-red text-white'}"
                                        >
                                            {definitive >= 10 ? "" : "R"}
                                        </span>
                                    </div>
                                {:else}
                                    <span
                                        class="text-xs px-2 py-0.5 rounded bg-yellow text-gray-800 font-bold"
                                    >
                                        En curso
                                    </span>
                                {/if}
                            </td>
                        </tr>
                    {/each}
                </tbody>
            </table>
        </div>

        <div
            class="mt-4 fixed bottom-8 right-10  gap-3 flex justify-end max-w-[600px] ml-auto items-center"
        >
            {#if $form.isDirty}
                <button
                    on:click={handleSave}
                    class="max-w-[300px] animated-button flex items-center gap-10 hover:shadow-lg "
                    disabled={$form.processing}
                >
                    {#if $form.processing}
                        Guardando...
                    {:else}
                        <span class="text">Guardar notas</span>
                        <span class="circle"></span>
                    {/if}
                </button>
            {/if}

            {#if !$form.isDirty && canPublish}

            <button type="button" on:click={handlePublish} disabled={$form.processing} class="bg-color4  hover:bg-color4/80 hover:shadow-lg  rounded-full text-dark min-w-fit font-bold py-3 px-4 mt-5">
                Publicar notas
            </button>
            {/if}
        </div>
    {/if}
{:else if viewMode === 'asistencia'}
    {#if !data.matrix}
        <div class="bg-white border border-gray-200 rounded-lg p-8 text-center text-gray-400">
            Selecciona un plan para cargar la matriz de asistencia.
        </div>
    {:else}
        <div class="bg-white border border-gray-200 rounded-lg shadow overflow-hidden">
            <!-- Add Session Bar -->
            <div class="p-4 bg-gray-50 border-b border-gray-200 flex flex-col md:flex-row md:items-center gap-4">
                <div class="flex items-center gap-3">
                    <label class="text-sm font-semibold text-gray-700">Nueva sesión:</label>
                    <input
                        type="date"
                        bind:value={newSessionDate}
                        max={new Date().toISOString().split('T')[0]}
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
                    <table class="w-fit text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-3 py-3 text-left sticky left-0 bg-gray-50 z-10">
                                    <div class="font-semibold text-gray-800">Estudiante</div>
                                </th>
                                {#each attendanceSessions as session}
                                    <th class="px-2 py-3 text-center justify-center relative group">
                                        <div class="flex flex-col items-center gap-1">
                                            <span class="font-semibold text-gray-800">{formatDate(session.date)}</span>
                                            <span class="text-xs text-gray-500 capitalize">{session.day_of_week}</span>
                                            <button
                                                class="absolute top-1 right-1 text-[10px] px-1.5 py-0.5 rounded bg-red-100 text-red-700 hover:bg-red-200 opacity-0 group-hover:opacity-100 transition-opacity"
                                                on:click={() => deleteSession(session.id)}
                                                title="Eliminar sesión"
                                            >
                                                ✕
                                            </button>
                                        </div>
                                        <!-- Mark All Toggle -->
                                        <input
                                            type="checkbox"
                                            class="h-4 w-4 accent-green-600 cursor-pointer"
                                            checked={isAllPresent(session.id)}
                                            title="marcar /desmarcar todos"
                                            on:change={() => toggleAllInSession(session.id)}
                                            aria-label={`Marcar todos de la sesión ${session.date}`}
                                        />
                                    </th>
                                {/each}
                            </tr>
                        </thead>
                        <tbody>
                            {#each sortedStudents as student}
                                <tr class="border-t border-gray-100">
                                    <td class="px-3 py-2 sticky left-0 bg-white z-10">
                                        <p class="font-semibold text-gray-800 capitalize">{student.last_name}, {student.name}</p>
                                        <p class="text-xs text-gray-400">C.I {student.ci}</p>
                                    </td>
                                    {#each attendanceSessions as session}
                                        <td class="px-2 w-[44px] aspect-square h-[44px] min-h-[44px] py-2 text-center">
                                            <button
                                                class="w-[44px] hover:text-gray-400 hover:bg-gray-200 aspect-square h-[44px] min-h-[44px] flex items-center justify-center text-2xl transition-colors rounded {getAttendanceClass(attendanceData[session.id]?.[student.id] || 'absent')}"
                                                on:click={() => toggleAttendance(session.id, student.id)}
                                            >
                                                {#if (attendanceData[session.id]?.[student.id] || 'absent') === 'present'}
                                                    <iconify-icon icon="mdi:check-bold" class="text-2xl"></iconify-icon>
                                                {:else if (attendanceData[session.id]?.[student.id] || 'absent') === 'excused'}
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
            class="mt-4 fixed bottom-8 right-10  gap-3 flex justify-end max-w-[600px] ml-auto items-center"
        >
            {#if attendanceDirty}
                <button
                    on:click={saveAttendance}
                    class="animated-button flex items-center gap-3"
                    disabled={attendanceSaving}
                >
                    <svg xmlns="http://www.w3.org/2000/svg" class="arr-2" viewBox="0 0 24 24">
                        <path d="M16.1716 10.9999L10.8076 5.63589L12.2218 4.22168L20 11.9999L12.2218 19.778L10.8076 18.3638L16.1716 12.9999H4V10.9999H16.1716Z"></path>
                    </svg>
                    {#if attendanceSaving}
                        <span class="text">Guardando...</span>
                    {:else}
                        <iconify-icon icon="material-symbols:save" class="text" width="20" height="20"></iconify-icon>
                        <span class="text">Guardar asistencia</span>
                    {/if}
                    <span class="circle"></span>
                    <svg xmlns="http://www.w3.org/2000/svg" class="arr-1" viewBox="0 0 24 24">
                        <path d="M16.1716 10.9999L10.8076 5.63589L12.2218 4.22168L20 11.9999L12.2218 19.778L10.8076 18.3638L16.1716 12.9999H4V10.9999H16.1716Z"></path>
                    </svg>
                </button>
            {/if}
        </div>
    {/if}
{/if}
