<script>
    export let students = [];
</script>

<svelte:head>
    <title>Mis Hijos</title>
</svelte:head>

<div
    class="w-full bg-white shadow-lg p-6 rounded-md max-w-[1200px] flex flex-col gap-4"
>
    <h3 class="text-lg font-bold text-gray-800 tracking-tight">Mis Hijos</h3>

    <div class="overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead>
                <tr class="border-b border-gray-200 text-left text-gray-500">
                    <th class="py-2 pr-4 font-medium">Nombres</th>
                    <th class="py-2 pr-4 font-medium">Apellidos</th>
                    <th class="py-2 pr-4 font-medium">C.I</th>
                    <th class="py-2 pr-4 font-medium">Año escolar</th>
                    <th class="py-2 pr-4 font-medium">Sección</th>
                    <th class="py-2 font-medium">Sexo</th>
                </tr>
            </thead>
            <tbody>
                {#if students.length > 0}
                    {#each students as student, i}
                        <tr class="border-b border-gray-100">
                            <td class="py-2 pr-4 text-gray-800">{student.name}</td>
                            <td class="py-2 pr-4 text-gray-800"
                                >{student.last_name}</td
                            >
                            <td class="py-2 pr-4 text-gray-600">{student.ci}</td>
                            <td class="py-2 pr-4 text-gray-600"
                                >{student.course}</td
                            >
                            <td class="py-2 pr-4 text-gray-600"
                                >{student.section}</td
                            >
                            <td class="py-2 text-gray-600">{student.sex}</td>
                        </tr>
                    {/each}
                {:else}
                    <tr>
                        <td colspan="6" class="py-4 text-center text-gray-400">
                            No tienes hijos inscritos.
                        </td>
                    </tr>
                {/if}
            </tbody>
        </table>
    </div>
</div>
