<script>
    import { inertia } from "@inertiajs/svelte";

    export let active = "sabana";

    const tabs = [
        {
            key: "sabana",
            label: "Sábana académica",
            href: "/dashboard/documentos/sabana-academica",
            icon: "mdi:table-large",
        },
        {
            key: "certificado",
            label: "Certificado de notas",
            href: "/dashboard/documentos/certificado-notas",
            icon: "mdi:certificate-outline",
        },
        {
            key: "resumen",
            label: "Resumen final",
            href: "/dashboard/documentos/resumen-final",
            icon: "mdi:file-chart-outline",
        },
        {
            key: "titulos",
            label: "Registro de títulos",
            href: "/dashboard/documentos/registro-titulos",
            icon: "mdi:school-outline",
        },
    ];
</script>

<div class="flex flex-wrap items-center gap-2 mb-5">
    <a
        href="/dashboard/documentos"
        use:inertia
        class="px-3 py-2 rounded-lg text-sm font-semibold bg-white text-gray-600 border border-gray-200 hover:bg-gray-50 flex items-center gap-1"
    >
        <iconify-icon icon="mdi:arrow-left" /> Documentos
    </a>
    {#each tabs as tab}
        <a
            href={tab.href}
            use:inertia
            class={`px-4 py-2 rounded-lg text-sm font-semibold transition-colors flex items-center gap-1 ${
                active === tab.key
                    ? "bg-color1 text-white shadow"
                    : "bg-white text-gray-600 border border-gray-200 hover:bg-gray-50"
            }`}
        >
            <iconify-icon icon={tab.icon} />
            {tab.label}
        </a>
    {/each}
</div>
