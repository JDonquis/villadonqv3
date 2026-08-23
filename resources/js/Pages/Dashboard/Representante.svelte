<script>
    export let students = [];
    export let total_children = 0;
</script>

<svelte:head>
    <title>Inicio</title>
</svelte:head>

<div
    class="w-full bg-white shadow-lg p-6 rounded-md max-w-[1200px] flex flex-col gap-4"
>
    <h3 class="text-lg font-bold text-gray-800 tracking-tight">Bienvenido</h3>
    <p class="text-gray-600 text-sm">
        Aquí puedes consultar la información de tus hijos y sus pagos.
    </p>

    {#if total_children === 0}
        <p class="text-gray-400 text-sm">
            No tienes hijos inscritos en el sistema.
        </p>
    {:else}
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            {#each students as student}
                <div
                    class="bg-gray-50 border border-gray-200 rounded-md p-4 flex flex-col gap-1"
                >
                    <span class="font-bold text-gray-800"
                        >{student.name} {student.last_name}</span
                    >
                    <span class="text-sm text-gray-600"
                        >C.I: {student.ci}</span
                    >
                    <span class="text-sm text-gray-600"
                        >{student.course} - Sección {student.section}</span
                    >
                </div>
            {/each}
        </div>

        <div class="flex gap-3 mt-2">
            <a
                href="/dashboard/mis-hijos"
                class="bg-blue-600 text-white px-4 py-2 rounded-md text-sm hover:opacity-90"
            >
                Mis Hijos
            </a>
            <a
                href="/dashboard/mis-pagos"
                class="bg-emerald-600 text-white px-4 py-2 rounded-md text-sm hover:opacity-90"
            >
                Mis Pagos
            </a>
        </div>
    {/if}
</div>
