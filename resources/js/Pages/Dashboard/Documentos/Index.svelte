<script>
    import { inertia } from "@inertiajs/svelte";

    export let options;
    export let summary = { students: 0, graduates: 0 };

    const documents = [
        {
            key: "sabana",
            title: "Sábana académica",
            description:
                "Resumen de calificaciones por año, sección y momento. Ideal para control interno y revisión de promedios.",
            href: "/dashboard/documentos/sabana-academica",
            icon: "mdi:table-large",
            color: "bg-color2",
        },
        {
            key: "certificado",
            title: "Certificado de notas",
            description:
                "Certificación individual con el historial de calificaciones por año escolar, planteles y firmas.",
            href: "/dashboard/documentos/certificado-notas",
            icon: "mdi:certificate-outline",
            color: "bg-color3",
        },
        {
            key: "resumen",
            title: "Resumen final",
            description:
                "Rendimiento final por área de formación, estadísticas de aprobados y profesores responsables.",
            href: "/dashboard/documentos/resumen-final",
            icon: "mdi:file-chart-outline",
            color: "bg-color1",
        },
        {
            key: "titulos",
            title: "Registro de títulos",
            description:
                "Hoja oficial de registro de títulos para egresados, con seriales, datos personales y autoridades.",
            href: "/dashboard/documentos/registro-titulos",
            icon: "mdi:school-outline",
            color: "bg-color1",
        },
    ];
</script>

<svelte:head>
    <title>Documentos</title>
</svelte:head>

<div class="mb-6">
    <h1 class="text-2xl font-bold text-color1">Documentos</h1>
    <p class="text-sm text-gray-500 mt-1">
        Gestione e imprima los documentos académicos oficiales del plantel en Excel o PDF.
    </p>
</div>

<div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4 mb-6">
    <div class="neumorphism rounded-xl border border-gray-100 p-4">
        <p class="text-xs uppercase text-gray-500 font-semibold">Estudiantes activos</p>
        <p class="text-3xl font-bold text-color1 mt-1">{summary.students}</p>
    </div>
    <div class="neumorphism rounded-xl border border-gray-100 p-4">
        <p class="text-xs uppercase text-gray-500 font-semibold">Egresados / títulos</p>
        <p class="text-3xl font-bold text-color1 mt-1">{summary.graduates}</p>
    </div>
    <div class="neumorphism rounded-xl border border-gray-100 p-4">
        <p class="text-xs uppercase text-gray-500 font-semibold">Períodos escolares</p>
        <p class="text-3xl font-bold text-color1 mt-1">{options.school_lapses?.length ?? 0}</p>
    </div>
    <div class="neumorphism rounded-xl border border-gray-100 p-4">
        <p class="text-xs uppercase text-gray-500 font-semibold">Años / grados</p>
        <p class="text-3xl font-bold text-color1 mt-1">{options.courses?.length ?? 0}</p>
    </div>
</div>

<div class="grid grid-cols-1 md:grid-cols-2 gap-5">
    {#each documents as document}
        <a
            href={document.href}
            use:inertia
            class="group neumorphism rounded-xl border border-gray-100 p-5 hover:border-color4 transition-colors flex gap-4"
        >
            <div class={`${document.color} text-white rounded-xl w-14 h-14 flex items-center justify-center text-3xl shrink-0`}>
                <iconify-icon icon={document.icon} />
            </div>
            <div>
                <h3 class="text-lg font-bold text-color1 group-hover:text-color2">
                    {document.title}
                </h3>
                <p class="text-sm text-gray-500 mt-1">{document.description}</p>
                <span class="inline-flex items-center gap-1 text-color2 text-sm font-semibold mt-3">
                    Abrir documento <iconify-icon icon="mdi:arrow-right" />
                </span>
            </div>
        </a>
    {/each}
</div>
