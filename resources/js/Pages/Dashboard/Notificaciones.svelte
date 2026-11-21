<script>
    import { router } from "@inertiajs/svelte";

    export let items = [];
    export let pagination = {};
    export let filter = "all";
    export let unread_count = 0;

    function severityClass(severity) {
        return (
            {
                success: "bg-emerald-50 text-emerald-600 border-emerald-100",
                warning: "bg-amber-50 text-amber-600 border-amber-100",
                danger: "bg-red-50 text-red-600 border-red-100",
                info: "bg-sky-50 text-sky-600 border-sky-100",
            }[severity] ?? "bg-sky-50 text-sky-600 border-sky-100"
        );
    }

    function changeFilter(next) {
        if (next === filter) return;
        router.get(
            "/dashboard/notificaciones",
            next === "all" ? {} : { filter: next },
            { preserveState: true, preserveScroll: true },
        );
    }

    function markRead(item) {
        if (item.is_read) return;
        router.post(
            `/dashboard/notificaciones/${item.id}/leer`,
            {},
            { preserveScroll: true, preserveState: true },
        );
    }

    function markAll() {
        if (!unread_count) return;
        router.post(
            "/dashboard/notificaciones/leer-todas",
            {},
            { preserveScroll: true, preserveState: true },
        );
    }

    function removeItem(item) {
        router.delete(`/dashboard/notificaciones/${item.id}`, {
            preserveScroll: true,
            preserveState: true,
        });
    }

    function removeAll() {
        if (!items.length) return;
        if (!confirm("¿Eliminar todas las notificaciones? Esta acción no se puede deshacer.")) return;

        router.delete("/dashboard/notificaciones", {
            preserveScroll: true,
            preserveState: true,
        });
    }

    function openItem(item) {
        if (!item.url) {
            markRead(item);
            return;
        }

        if (item.is_read) {
            router.visit(item.url);
            return;
        }

        router.post(
            `/dashboard/notificaciones/${item.id}/leer`,
            {},
            {
                preserveScroll: true,
                preserveState: true,
                onSuccess: () => router.visit(item.url),
            },
        );
    }

    function go(url) {
        if (url) router.visit(url);
    }
</script>

<svelte:head>
    <title>Notificaciones</title>
</svelte:head>

<div class="max-w-4xl mx-auto">
    <div class="flex flex-wrap items-center justify-between gap-3 mb-5">
        <div>
            <h1 class="text-xl font-extrabold text-color1 tracking-tight">Notificaciones</h1>
            <p class="text-xs text-gray-400 mt-0.5">
                {unread_count} sin leer · {pagination.total ?? items.length} en total
            </p>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            {#if unread_count > 0}
                <button
                    type="button"
                    on:click={markAll}
                    class="px-3 py-2 rounded-xl text-xs font-semibold bg-white border border-grayBlue/40 text-color1 hover:bg-slate-50"
                >
                    Marcar todas como leídas
                </button>
            {/if}
            {#if items.length}
                <button
                    type="button"
                    on:click={removeAll}
                    class="px-3 py-2 rounded-xl text-xs font-semibold bg-red/10 text-red hover:bg-red/15"
                >
                    Eliminar todas
                </button>
            {/if}
        </div>
    </div>

    <div class="flex items-center gap-2 mb-4">
        <button
            type="button"
            on:click={() => changeFilter("all")}
            class={`px-4 py-2 rounded-xl text-xs font-bold border transition-colors ${filter === "all" ? "bg-color1 text-white border-color1" : "bg-white text-gray-600 border-grayBlue/40 hover:bg-slate-50"}`}
        >
            Todas
        </button>
        <button
            type="button"
            on:click={() => changeFilter("unread")}
            class={`px-4 py-2 rounded-xl text-xs font-bold border transition-colors ${filter === "unread" ? "bg-color1 text-white border-color1" : "bg-white text-gray-600 border-grayBlue/40 hover:bg-slate-50"}`}
        >
            No leídas
        </button>
    </div>

    <div class="bg-white rounded-2xl border border-grayBlue/30 shadow-sm overflow-hidden">
        {#if items.length}
            {#each items as item (item.id)}
                <div
                    class={`group flex items-start gap-3 px-4 py-3.5 border-b border-grayBlue/10 last:border-b-0 ${item.is_read ? "bg-white" : "bg-sky-50/40"}`}
                >
                    <button
                        type="button"
                        on:click={() => openItem(item)}
                        class="flex-1 min-w-0 text-left flex items-start gap-3"
                    >
                        <span
                            class={`mt-0.5 w-9 h-9 rounded-xl border flex items-center justify-center shrink-0 ${severityClass(item.severity)}`}
                        >
                            <iconify-icon icon={item.icon} class="text-lg"></iconify-icon>
                        </span>
                        <span class="min-w-0">
                            <span class="flex items-center gap-2">
                                <span class="text-sm font-bold text-gray-800">
                                    {item.title}
                                </span>
                                {#if !item.is_read}
                                    <span class="w-2 h-2 rounded-full bg-color2 shrink-0"></span>
                                {/if}
                            </span>
                            <span class="block text-xs text-gray-500 whitespace-pre-line mt-1">
                                {item.body}
                            </span>
                            <span class="block text-[11px] text-gray-400 mt-1.5">
                                {item.created_at_label}
                            </span>
                        </span>
                    </button>

                    <div class="flex items-center gap-1 shrink-0">
                        {#if !item.is_read}
                            <button
                                type="button"
                                on:click={() => markRead(item)}
                                class="w-8 h-8 rounded-lg text-color2 hover:bg-color2/10 flex items-center justify-center"
                                title="Marcar como leída"
                                aria-label="Marcar como leída"
                            >
                                <iconify-icon icon="mdi:check-circle-outline" class="text-lg"></iconify-icon>
                            </button>
                        {/if}
                        <button
                            type="button"
                            on:click={() => removeItem(item)}
                            class="w-8 h-8 rounded-lg text-gray-300 hover:text-red hover:bg-red/10 flex items-center justify-center"
                            title="Eliminar notificación"
                            aria-label="Eliminar notificación"
                        >
                            <iconify-icon icon="mdi:trash-can-outline" class="text-lg"></iconify-icon>
                        </button>
                    </div>
                </div>
            {/each}
        {:else}
            <div class="px-4 py-16 text-center">
                <iconify-icon
                    icon="mdi:bell-sleep-outline"
                    class="text-4xl text-gray-300"
                ></iconify-icon>
                <p class="text-sm text-gray-400 mt-3">
                    {filter === "unread"
                        ? "No tienes notificaciones sin leer."
                        : "Todavía no tienes notificaciones."}
                </p>
            </div>
        {/if}
    </div>

    {#if pagination.last_page > 1}
        <div class="flex items-center justify-between mt-4">
            <button
                type="button"
                disabled={!pagination.prev_page_url}
                on:click={() => go(pagination.prev_page_url)}
                class="px-3 py-2 rounded-xl text-xs font-semibold bg-white border border-grayBlue/40 text-color1 disabled:opacity-40"
            >
                Anterior
            </button>
            <p class="text-xs text-gray-400">
                Página {pagination.current_page} de {pagination.last_page}
            </p>
            <button
                type="button"
                disabled={!pagination.next_page_url}
                on:click={() => go(pagination.next_page_url)}
                class="px-3 py-2 rounded-xl text-xs font-semibold bg-white border border-grayBlue/40 text-color1 disabled:opacity-40"
            >
                Siguiente
            </button>
        </div>
    {/if}
</div>
