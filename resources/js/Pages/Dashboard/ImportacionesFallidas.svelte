<script>
    import { router } from "@inertiajs/svelte";
    import { onMount, afterUpdate } from "svelte";
    import axios from "axios";

    export let data = { failedImports: [] };

    let typeFilter = data.importType || 'all';
    let editingRows = {};
    let retryLoading = {};
    let deleteAllLoading = false;
    let tableEl;

    const studentFieldGroups = [
        { label: "Estudiante", fields: ['student_name', 'student_last_name', 'student_ci', 'student_document_type', 'student_date_birth', 'student_email', 'student_phone_number', 'student_sex', 'student_previous_school'] },
        { label: "Representante", fields: ['rep_name', 'rep_last_name', 'rep_ci', 'rep_document_type', 'rep_phone_number', 'rep_phone_number2', 'rep_email', 'rep_profession', 'rep_workplace', 'rep_relationship'] },
        { label: "2do Representante", fields: ['second_rep_name', 'second_rep_last_name', 'second_rep_ci', 'second_rep_document_type', 'second_rep_phone_number', 'second_rep_phone_number2', 'second_rep_email', 'second_rep_profession', 'second_rep_workplace', 'second_rep_relationship'] },
        { label: "Curso/Sección", fields: ['course_name', 'section_name'] },
        { label: "Exoneración", fields: ['is_exempt', 'exemption_percentage', 'exemption_observations'] },
        { label: "Dirección", fields: ['address', 'state', 'city'] },
    ];

    const teacherFieldGroups = [
        { label: "Datos", fields: ['ci', 'name', 'last_name', 'email', 'phone_number', 'address', 'matters'] },
    ];

    function getFieldGroups() {
        const t = typeFilter === 'all' ? data.failedImports[0]?.data ? 'student' : 'student' : typeFilter;
        return t === 'teacher' ? teacherFieldGroups : studentFieldGroups;
    }

    function startEdit(id) {
        editingRows[id] = true;
    }

    function cancelEdit(id) {
        editingRows[id] = false;
    }

    async function retry(id) {
        retryLoading[id] = true;
        const fi = data.failedImports.find(f => f.id === id);
        const baseUrl = fi && fi.row_number !== undefined ? '/dashboard/importaciones-fallidas' : '/dashboard/importaciones-fallidas';
        const endpoint = fi?.data?.ci ? '/dashboard/importaciones-fallidas-profesores' : '/dashboard/importaciones-fallidas';
        try {
            const { data: result } = await axios.post(`${endpoint}/${id}/reintentar`);
            if (result.success) {
                data.failedImports = data.failedImports.filter(f => f.id !== id);
            }
        } catch (err) {
            const msg = err.response?.data?.error || 'Error al reintentar';
            alert(msg);
        } finally {
            retryLoading[id] = false;
        }
    }

    async function updateRow(id) {
        const row = data.failedImports.find(fi => fi.id === id);
        if (!row) return;

        const isTeacher = row.data?.ci !== undefined && row.data?.student_name === undefined;
        const endpoint = isTeacher ? '/dashboard/importaciones-fallidas-profesores' : '/dashboard/importaciones-fallidas';
        const fields = isTeacher ? ['ci', 'name', 'last_name', 'email', 'phone_number', 'address', 'matters'] : getFieldGroups().flatMap(g => g.fields);

        const payload = {};
        fields.forEach(field => {
            const val = row.data[field];
            if (val !== undefined && val !== null && val !== '') {
                payload[field] = val;
            }
        });

        try {
            await axios.put(`${endpoint}/${id}`, payload);
            editingRows[id] = false;
        } catch (err) {
            alert(err.response?.data?.error || 'Error al guardar');
        }
    }

    async function remove(id) {
        const row = data.failedImports.find(fi => fi.id === id);
        const endpoint = row?.data?.ci !== undefined && row?.data?.student_name === undefined
            ? '/dashboard/importaciones-fallidas-profesores'
            : '/dashboard/importaciones-fallidas';
        if (!confirm('¿Eliminar este registro fallido?')) return;
        try {
            await axios.delete(`${endpoint}/${id}`);
            data.failedImports = data.failedImports.filter(fi => fi.id !== id);
        } catch (err) {
            alert(err.response?.data?.error || 'Error al eliminar');
        }
    }

    function isTeacherRow(fi) {
        return fi.data?.ci !== undefined && fi.data?.student_name === undefined;
    }

    function isStudentRow(fi) {
        return fi.data?.student_name !== undefined;
    }

    async function deleteAll() {
        const count = filteredImports.length;
        const typeLabel = typeFilter === 'teacher' ? 'Profesores' : typeFilter === 'student' ? 'Estudiantes' : 'Todos';
        if (!confirm(`¿Eliminar ${count} registro(s) en "${typeLabel}"? Esta acción no se puede deshacer.`)) return;

        deleteAllLoading = true;
        try {
            await axios.delete('/dashboard/importaciones-fallidas/eliminar-todas', { params: { type: typeFilter } });
            if (typeFilter === 'all') {
                data.failedImports = [];
            } else if (typeFilter === 'teacher') {
                data.failedImports = data.failedImports.filter(fi => !isTeacherRow(fi));
            } else {
                data.failedImports = data.failedImports.filter(fi => !isStudentRow(fi));
            }
        } catch (err) {
            alert(err.response?.data?.error || 'Error al eliminar');
        } finally {
            deleteAllLoading = false;
        }
    }

    function formatDate(dateStr) {
        if (!dateStr) return '';
        const d = new Date(dateStr);
        return d.toLocaleString('es-ES', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' });
    }

    const fieldLabels = {
        student_name: 'Nombre del estudiante',
        student_last_name: 'Apellido del estudiante',
        student_ci: 'Cédula del estudiante',
        student_document_type: 'Tipo de documento',
        student_date_birth: 'Fecha de nacimiento',
        student_email: 'Correo del estudiante',
        student_phone_number: 'Teléfono del estudiante',
        student_sex: 'Sexo',
        student_previous_school: 'Colegio anterior',
        rep_name: 'Nombre del representante',
        rep_last_name: 'Apellido del representante',
        rep_ci: 'Cédula del representante',
        rep_document_type: 'Tipo de documento',
        rep_phone_number: 'Teléfono del representante',
        rep_phone_number2: 'Teléfono 2 del representante',
        rep_email: 'Correo del representante',
        rep_profession: 'Profesión',
        rep_workplace: 'Lugar de trabajo',
        rep_relationship: 'Parentesco',
        second_rep_name: 'Nombre del 2do representante',
        second_rep_last_name: 'Apellido del 2do representante',
        second_rep_ci: 'Cédula del 2do representante',
        second_rep_document_type: 'Tipo de documento',
        second_rep_phone_number: 'Teléfono del 2do representante',
        second_rep_phone_number2: 'Teléfono 2 del 2do representante',
        second_rep_email: 'Correo del 2do representante',
        second_rep_profession: 'Profesión del 2do rep.',
        second_rep_workplace: 'Lugar de trabajo del 2do rep.',
        second_rep_relationship: 'Parentesco del 2do rep.',
        course_name: 'Curso',
        section_name: 'Sección',
        is_exempt: 'Exonerado',
        exemption_percentage: 'Porcentaje exoneración',
        exemption_observations: 'Observaciones exoneración',
        address: 'Dirección',
        state: 'Estado',
        city: 'Ciudad',
        ci: 'Cédula',
        name: 'Nombre',
        last_name: 'Apellido',
        email: 'Correo',
        phone_number: 'Teléfono',
        matters: 'Materias',
    };

    function fieldLabel(field) {
        return fieldLabels[field] || field.replace(/_/g, ' ').replace(/^\w/, c => c.toUpperCase());
    }

    function alignStickyError() {
        if (!tableEl) return;
        const acc = tableEl.querySelector('.acciones-sticky');
        const width = acc ? acc.offsetWidth : 0;
        if (!width) return;
        tableEl.querySelectorAll('.error-sticky').forEach(el => {
            el.style.right = width + 'px';
        });
    }

    afterUpdate(() => {
        requestAnimationFrame(alignStickyError);
    });

    onMount(() => {
        window.addEventListener('resize', alignStickyError);
        return () => window.removeEventListener('resize', alignStickyError);
    });

    $: filteredImports = typeFilter === 'all'
        ? data.failedImports
        : data.failedImports.filter(fi => {
            if (typeFilter === 'teacher') return isTeacherRow(fi);
            return isStudentRow(fi);
        });
</script>

<div class="p-6">
    <h2 class="text-xl md:text-2xl font-bold text-color1 sm:hidden mb-6">
    Importaciones con errores
</h2>

    {#if data.failedImports.length > 0}
        <div class="flex gap-2 mb-4">
            <button
                class="toolbar-secondary {typeFilter === 'all' ? 'bg-gray-800 text-white' : ''}"
                on:click={() => typeFilter = 'all'}
            >
                Todos ({data.failedImports.length})
            </button>
            <button
                class="toolbar-secondary {typeFilter === 'student' ? 'bg-blue-600 text-white' : ''}"
                on:click={() => typeFilter = 'student'}
            >
                Estudiantes ({data.failedImports.filter(fi => fi.data?.student_name !== undefined).length})
            </button>
            <button
                class="toolbar-secondary {typeFilter === 'teacher' ? 'bg-green-600 text-white' : ''}"
                on:click={() => typeFilter = 'teacher'}
            >
                Profesores ({data.failedImports.filter(fi => fi.data?.ci !== undefined && fi.data?.student_name === undefined).length})
            </button>
            <button
                class="toolbar-secondary danger ml-auto"
                on:click={deleteAll}
                disabled={deleteAllLoading}
            >
                {deleteAllLoading ? 'Eliminando...' : 'Eliminar todas'}
            </button>
        </div>
    {/if}

    {#if filteredImports.length === 0}
        <div class="text-center py-12 bg-gray-50 rounded-lg">
            <iconify-icon icon="material-symbols:check-circle" width="64" height="64" class="text-green-500 mx-auto"></iconify-icon>
            <p class="text-gray-500 mt-4 text-lg">No hay registros con errores para el filtro seleccionado.</p>
            <a href="/dashboard/matricula" class="inline-block mt-4 text-blue-600 hover:underline">Volver a Matrícula</a>
        </div>
    {:else}
        <p class="text-gray-600 mb-4">
            {filteredImports.length} registro(s) con errores. Editá los campos y reintentá la creación.
        </p>

        <div class="overflow-x-auto">
            <table bind:this={tableEl} class="min-w-full bg-white border border-gray-200 rounded-lg">
                <thead class="bg-gray-100 sticky top-0 z-30">
                    <tr>
                        <th class="px-3 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Fila</th>
                        {#each getFieldGroups() as group}
                            {#each group.fields as field}
                                <th class="px-2 py-3 text-left text-xs font-semibold text-gray-600 uppercase whitespace-nowrap" title="{fieldLabel(field)}">{fieldLabel(field)}</th>
                            {/each}
                        {/each}
                        <th class="error-sticky sticky right-0 z-20 px-3 py-3 text-left text-xs font-semibold text-gray-600 uppercase min-w-[400px] bg-gray-100">Error</th>
                        <th class="acciones-sticky sticky right-0 z-30 w-40 px-3 py-3 text-left text-xs font-semibold text-gray-600 uppercase whitespace-nowrap bg-gray-100">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    {#each filteredImports as fi}
                        <tr class="hover:bg-gray-50 group">
                            <td class="px-3 py-2 text-sm font-medium">{fi.row_number}</td>
                            {#each getFieldGroups() as group}
                                {#each group.fields as field}
                                    <td class="px-2 py-2 min-w-[100px]">
                                        {#if editingRows[fi.id]}
                                            {#if field === 'matters'}
                                                <input type="text" bind:value={fi.data[field]} class="border rounded px-1 text-sm w-48" />
                                            {:else}
                                                <input type="text" bind:value={fi.data[field]} class="border rounded px-1 text-sm w-full" />
                                            {/if}
                                        {:else}
                                            <span class="text-sm">{fi.data[field] || '-'}</span>
                                        {/if}
                                    </td>
                                {/each}
                            {/each}
                            <td class="error-sticky sticky right-0 z-10 px-3 py-2 text-sm text-red-600 min-w-[400px] bg-white group-hover:bg-gray-50">
                                {fi.error_message}
                            </td>
                            <td class="sticky right-0 z-20 w-40 px-3 py-2 text-sm whitespace-nowrap bg-white group-hover:bg-gray-50">
                                {#if editingRows[fi.id]}
                                    <button class="text-green-600 hover:underline mr-2" on:click={() => updateRow(fi.id)}>Guardar</button>
                                    <button class="text-gray-600 hover:underline mr-2" on:click={() => cancelEdit(fi.id)}>Cancelar</button>
                                {:else}
                                    <button class="text-blue-600 hover:opacity-70 mr-2" title="Editar" on:click={() => startEdit(fi.id)}>
                                        <iconify-icon icon="material-symbols:edit" width="22" height="22" />
                                    </button>
                                    <button class="text-green-600 hover:opacity-70 disabled:opacity-50 mr-2" title="Reintentar" on:click={() => retry(fi.id)} disabled={retryLoading[fi.id]}>
                                        <iconify-icon
                                            icon={retryLoading[fi.id] ? 'material-symbols:progress-activity' : 'material-symbols:refresh'}
                                            width="22"
                                            height="22"
                                        />
                                    </button>
                                    <button class="text-red-600 hover:opacity-70" title="Eliminar" on:click={() => remove(fi.id)}>
                                        <iconify-icon icon="material-symbols:delete" width="22" height="22" />
                                    </button>
                                {/if}
                            </td>
                        </tr>
                    {/each}
                </tbody>
            </table>
        </div>

        <div class="mt-4 text-xs text-gray-400">
            Clic en "Editar" para habilitar los campos de toda la fila. Guardá para actualizar o "Reintentar" para crear el registro inmediatamente.
        </div>
    {/if}
</div>
