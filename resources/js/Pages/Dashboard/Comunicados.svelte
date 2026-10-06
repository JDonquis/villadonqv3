<script>
    import { useForm } from "@inertiajs/svelte";
    import Input from "../../components/Input.svelte";

    export let announcements = [];
    export let events = [];
    export let courses = [];

    let tab = "comunicados";

    const emptyAnnouncement = {
        id: null,
        title: "",
        body: "",
        audience: "all",
        course_id: "",
        published: true,
    };

    const emptyEvent = {
        id: null,
        title: "",
        description: "",
        type: "general",
        start_date: "",
        end_date: "",
        course_id: "",
        published: true,
    };

    let announcementForm = useForm({ ...emptyAnnouncement });
    let eventForm = useForm({ ...emptyEvent });

    function resetAnnouncement() {
        announcementForm = useForm({ ...emptyAnnouncement });
    }

    function resetEvent() {
        eventForm = useForm({ ...emptyEvent });
    }

    function editAnnouncement(item) {
        tab = "comunicados";
        announcementForm = useForm({
            id: item.id,
            title: item.title,
            body: item.body,
            audience: item.audience,
            course_id: item.course_id ?? "",
            published: item.status === 1,
        });
    }

    function submitAnnouncement() {
        const options = {
            preserveScroll: true,
            onSuccess: () => resetAnnouncement(),
        };

        if ($announcementForm.id) {
            announcementForm.put(`/dashboard/comunicados/${$announcementForm.id}`, options);
        } else {
            announcementForm.post("/dashboard/comunicados", options);
        }
    }

    function removeAnnouncement(item) {
        if (!confirm(`¿Eliminar el comunicado "${item.title}"?`)) return;
        announcementForm.delete(`/dashboard/comunicados/${item.id}`, { preserveScroll: true });
    }

    function editEvent(item) {
        tab = "eventos";
        eventForm = useForm({
            id: item.id,
            title: item.title,
            description: item.description ?? "",
            type: item.type,
            start_date: item.start_date ?? "",
            end_date: item.end_date ?? "",
            course_id: item.course_id ?? "",
            published: item.status === 1,
        });
    }

    function submitEvent() {
        const options = {
            preserveScroll: true,
            onSuccess: () => resetEvent(),
        };

        if ($eventForm.id) {
            eventForm.put(`/dashboard/eventos/${$eventForm.id}`, options);
        } else {
            eventForm.post("/dashboard/eventos", options);
        }
    }

    function removeEvent(item) {
        if (!confirm(`¿Eliminar la actividad "${item.title}"?`)) return;
        eventForm.delete(`/dashboard/eventos/${item.id}`, { preserveScroll: true });
    }
</script>

<svelte:head>
    <title>Comunicados</title>
</svelte:head>

<div class="flex items-center gap-2 mb-5">
    <button
        type="button"
        class={`px-4 py-2 rounded-lg text-sm font-semibold transition-colors ${tab === "comunicados" ? "bg-color1 text-white" : "bg-white text-gray-600 border border-gray-200"}`}
        on:click={() => (tab = "comunicados")}>Comunicados</button
    >
    <button
        type="button"
        class={`px-4 py-2 rounded-lg text-sm font-semibold transition-colors ${tab === "eventos" ? "bg-color1 text-white" : "bg-white text-gray-600 border border-gray-200"}`}
        on:click={() => (tab = "eventos")}>Actividades / Eventos</button
    >
</div>

{#if tab === "comunicados"}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-1 neumorphism rounded-xl border p-5 shadow-sm">
            <h3 class="text-base font-bold text-gray-800 mb-2">
                {$announcementForm.id ? "Editar comunicado" : "Nuevo comunicado"}
            </h3>
            <Input label="Título" bind:value={$announcementForm.title} />
            <Input label="Contenido" type="textarea" bind:value={$announcementForm.body} />
            <Input label="Audiencia" type="select" bind:value={$announcementForm.audience}>
                <option value="all">Todos</option>
                <option value="course">Un curso</option>
                <option value="student">Un estudiante</option>
            </Input>
            {#if $announcementForm.audience === "course"}
                <Input label="Curso" type="select" bind:value={$announcementForm.course_id}>
                    <option value="">Seleccione…</option>
                    {#each courses as course}
                        <option value={course.id}>{course.name}</option>
                    {/each}
                </Input>
            {/if}
            {#if $announcementForm.audience === "student"}
                <Input label="ID del estudiante" type="number" bind:value={$announcementForm.student_id} />
            {/if}
            <label class="flex items-center gap-2 mt-4 text-sm text-gray-700">
                <input type="checkbox" bind:checked={$announcementForm.published} />
                Publicar ahora
            </label>
            {#if $announcementForm.errors.title || $announcementForm.errors.body}
                <p class="text-xs text-red mt-2">
                    {$announcementForm.errors.title ?? $announcementForm.errors.body}
                </p>
            {/if}
            <div class="flex gap-2 mt-4">
                <button
                    type="button"
                    disabled={$announcementForm.processing}
                    on:click={submitAnnouncement}
                    class="px-4 py-2 rounded-lg bg-color1 text-white text-sm font-semibold disabled:opacity-50"
                >
                    {$announcementForm.id ? "Guardar cambios" : "Crear"}
                </button>
                {#if $announcementForm.id}
                    <button
                        type="button"
                        on:click={resetAnnouncement}
                        class="px-4 py-2 rounded-lg bg-gray-100 text-gray-600 text-sm font-semibold"
                    >Cancelar</button
                    >
                {/if}
            </div>
        </div>

        <div class="lg:col-span-2 space-y-3">
            {#if announcements.length}
                {#each announcements as item (item.id)}
                    <div class="neumorphism rounded-xl border p-4 shadow-sm">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="font-semibold text-gray-800 truncate">{item.title}</p>
                                <p class="text-xs text-gray-500 mt-1 whitespace-pre-line">{item.body}</p>
                                <p class="text-[11px] text-gray-400 mt-1">
                                    {item.audience === "all" ? "Todos" : item.course ?? item.section ?? "Dirigido"}
                                    · {item.status === 1 ? "Publicado" : "Borrador"}
                                </p>
                            </div>
                            <div class="flex gap-2 shrink-0">
                                <button
                                    type="button"
                                    on:click={() => editAnnouncement(item)}
                                    class="text-xs font-semibold text-color1 hover:text-color2"
                                >Editar</button
                                >
                                <button
                                    type="button"
                                    on:click={() => removeAnnouncement(item)}
                                    class="text-xs font-semibold text-red"
                                >Eliminar</button
                                >
                            </div>
                        </div>
                    </div>
                {/each}
            {:else}
                <p class="text-sm text-gray-400">Aún no hay comunicados.</p>
            {/if}
        </div>
    </div>
{:else}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-1 neumorphism rounded-xl border p-5 shadow-sm">
            <h3 class="text-base font-bold text-gray-800 mb-2">
                {$eventForm.id ? "Editar actividad" : "Nueva actividad"}
            </h3>
            <Input label="Título" bind:value={$eventForm.title} />
            <Input label="Descripción" type="textarea" bind:value={$eventForm.description} />
            <Input label="Tipo" type="select" bind:value={$eventForm.type}>
                <option value="general">General</option>
                <option value="exam">Evaluación</option>
                <option value="meeting">Reunión</option>
                <option value="holiday">Feriado</option>
            </Input>
            <Input label="Fecha inicio" type="date" bind:value={$eventForm.start_date} />
            <Input label="Fecha fin" type="date" bind:value={$eventForm.end_date} />
            <Input label="Curso (opcional)" type="select" bind:value={$eventForm.course_id}>
                <option value="">Todos</option>
                {#each courses as course}
                    <option value={course.id}>{course.name}</option>
                {/each}
            </Input>
            <label class="flex items-center gap-2 mt-4 text-sm text-gray-700">
                <input type="checkbox" bind:checked={$eventForm.published} />
                Publicar ahora
            </label>
            <div class="flex gap-2 mt-4">
                <button
                    type="button"
                    disabled={$eventForm.processing}
                    on:click={submitEvent}
                    class="px-4 py-2 rounded-lg bg-color1 text-white text-sm font-semibold disabled:opacity-50"
                >
                    {$eventForm.id ? "Guardar cambios" : "Crear"}
                </button>
                {#if $eventForm.id}
                    <button
                        type="button"
                        on:click={resetEvent}
                        class="px-4 py-2 rounded-lg bg-gray-100 text-gray-600 text-sm font-semibold"
                    >Cancelar</button
                    >
                {/if}
            </div>
        </div>

        <div class="lg:col-span-2 space-y-3">
            {#if events.length}
                {#each events as item (item.id)}
                    <div class="neumorphism rounded-xl border p-4 shadow-sm">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="font-semibold text-gray-800 truncate">{item.title}</p>
                                <p class="text-xs text-gray-500 mt-1">{item.description}</p>
                                <p class="text-[11px] text-gray-400 mt-1">
                                    {item.start_date}{item.end_date ? ` → ${item.end_date}` : ""}
                                    · {item.type} · {item.status === 1 ? "Publicado" : "Borrador"}
                                </p>
                            </div>
                            <div class="flex gap-2 shrink-0">
                                <button
                                    type="button"
                                    on:click={() => editEvent(item)}
                                    class="text-xs font-semibold text-color1 hover:text-color2"
                                >Editar</button
                                >
                                <button
                                    type="button"
                                    on:click={() => removeEvent(item)}
                                    class="text-xs font-semibold text-red"
                                >Eliminar</button
                                >
                            </div>
                        </div>
                    </div>
                {/each}
            {:else}
                <p class="text-sm text-gray-400">Aún no hay actividades.</p>
            {/if}
        </div>
    </div>
{/if}
