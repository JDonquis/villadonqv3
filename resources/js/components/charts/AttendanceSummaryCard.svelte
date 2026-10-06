<script>
    import axios from "axios";
    import KpiCard from "../KpiCard.svelte";

    export let schoolLapseId = null;

    let data = null;
    let loading = true;
    let lastLapseId = "__init__";

    async function fetchData(lapseId) {
        loading = true;
        try {
            const url = lapseId
                ? `/dashboard/graficos/attendance-summary/${lapseId}`
                : `/dashboard/graficos/attendance-summary`;
            const response = await axios.get(url);
            data = response.data.data;
        } catch (error) {
            console.error("Error fetching attendance summary:", error);
        } finally {
            loading = false;
        }
    }

    $: if (schoolLapseId !== lastLapseId) {
        lastLapseId = schoolLapseId;
        fetchData(schoolLapseId);
    }
</script>

<div class="mt-6">
    <h3 class="text-lg font-bold text-gray-800 tracking-tight mb-3">
        Matrícula y Asistencia
    </h3>
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <KpiCard
            label="Estudiantes Activos"
            value={data?.active?.toLocaleString() ?? "—"}
            icon="mdi:account-check"
            color="blue"
        />
        <KpiCard
            label="Retirados"
            value={data?.withdrawn?.toLocaleString() ?? "—"}
            icon="mdi:account-off"
            color="orange"
        />
        <KpiCard
            label="Graduados"
            value={data?.graduated?.toLocaleString() ?? "—"}
            icon="mdi:school"
            color="green"
        />
        <KpiCard
            label="Asistencia Hoy ({data?.attendance?.date ?? "—"})"
            value={data?.attendance?.rate !== null && data?.attendance?.rate !== undefined
                ? data.attendance.rate + "%"
                : "Sin datos"}
            icon="mdi:clipboard-check"
            color="purple"
        />
    </div>
    {#if data?.attendance?.total > 0}
        <p class="text-xs text-gray-500 mt-2">
            {data.attendance.sessions} sesiones registradas hoy ·
            {data.attendance.present} presentes ·
            {data.attendance.excused} justificadas ·
            {data.attendance.absent} ausentes
        </p>
    {:else if !loading}
        <p class="text-xs text-gray-400 mt-2">
            Aún no hay sesiones de asistencia registradas hoy.
        </p>
    {/if}
</div>
