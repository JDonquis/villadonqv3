<script>
    import { page, router } from "@inertiajs/svelte";
    import axios from "axios";
    import { displayAlert } from "../../stores/alertStore";

    export let data;

    let filters = { ...data.filters };
    let lastFlash = null;
    let testing = false;

    $: flash = $page?.props?.flash?.payment;
    $: if (flash && JSON.stringify(flash) !== lastFlash) {
        lastFlash = JSON.stringify(flash);
        displayAlert({
            type: flash.status === "error" ? "error" : flash.status === "warning" ? "warning" : flash.status === "success" ? "success" : "info",
            message: flash.message,
        });
    }

    function applyFilters() {
        router.get("/dashboard/pagos/verificaciones-bnc", filters, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    }

    function post(url, payload = {}) {
        router.post(url, payload, {
            preserveScroll: true,
        });
    }

    function verify(id) {
        post(`/dashboard/pagos/verificaciones-bnc/${id}/verificar`);
    }

    function verifyManual(id) {
        const note = window.prompt("Nota de la verificación manual (opcional):", "Verificado manualmente por administración");
        if (note === null) return;
        post(`/dashboard/pagos/verificaciones-bnc/${id}/verificar-manual`, { note });
    }

    function reject(id) {
        const reason = window.prompt("Motivo del rechazo:");
        if (!reason) return;
        post(`/dashboard/pagos/verificaciones-bnc/${id}/rechazar`, { reason });
    }

    async function testConnection() {
        testing = true;
        try {
            const { data: result } = await axios.post("/dashboard/pagos/verificaciones-bnc/probar-conexion");
            displayAlert({
                type: result.ok ? "success" : "error",
                message: result.ok
                    ? `Conexión BNC correcta. Login: ${result.logged_at ?? "—"}`
                    : result.message,
            });
        } catch (error) {
            displayAlert({
                type: "error",
                message: error?.response?.data?.message || "No se pudo probar la conexión BNC.",
            });
        } finally {
            testing = false;
        }
    }

    function copyWebhook() {
        navigator.clipboard.writeText(data.config.webhook_url);
        displayAlert({ type: "success", message: "URL del webhook copiada." });
    }

    function statusClass(status) {
        return (
            {
                verified: "bg-emerald-100 text-emerald-800",
                pending: "bg-amber-100 text-amber-800",
                error: "bg-amber-100 text-amber-800",
                rejected: "bg-red-100 text-red-800",
                expired: "bg-gray-200 text-gray-700",
                processed: "bg-emerald-100 text-emerald-800",
                unmatched: "bg-orange-100 text-orange-800",
                duplicate: "bg-gray-200 text-gray-700",
                received: "bg-blue-100 text-blue-800",
                processing: "bg-blue-100 text-blue-800",
            }[status] || "bg-gray-100 text-gray-700"
        );
    }

    function money(value) {
        return Number(value || 0).toLocaleString("de-DE", {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2,
        });
    }
</script>

<svelte:head>
    <title>Verificación BNC</title>
</svelte:head>

<div class="p-3 md:p-6 text-gray-700">
    <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
        <div>
            <h1 class="text-xl font-bold text-color1">Verificación BNC</h1>
            <p class="text-sm text-gray-500">
                Pagos reportados por representantes y notificaciones del banco.
            </p>
        </div>
        <a
            href="/dashboard/pagos"
            class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-semibold hover:bg-gray-50"
        >
            Volver a Pagos
        </a>
    </div>

    <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-4 mb-5">
        <div class="rounded-xl bg-white p-4 shadow-sm border border-gray-100">
            <p class="text-xs uppercase tracking-wide text-gray-400">Pendientes</p>
            <p class="text-2xl font-bold text-amber-600">{data.stats.pending}</p>
        </div>
        <div class="rounded-xl bg-white p-4 shadow-sm border border-gray-100">
            <p class="text-xs uppercase tracking-wide text-gray-400">Verificados hoy</p>
            <p class="text-2xl font-bold text-emerald-600">{data.stats.verified_today}</p>
        </div>
        <div class="rounded-xl bg-white p-4 shadow-sm border border-gray-100">
            <p class="text-xs uppercase tracking-wide text-gray-400">Notificaciones sin conciliar</p>
            <p class="text-2xl font-bold text-orange-600">{data.stats.unmatched_notifications}</p>
        </div>
        <div class="rounded-xl bg-white p-4 shadow-sm border border-gray-100">
            <p class="text-xs uppercase tracking-wide text-gray-400">Rechazados / expirados</p>
            <p class="text-2xl font-bold text-gray-700">{data.stats.rejected + data.stats.expired}</p>
        </div>
    </div>

    <div class="rounded-xl bg-white p-4 shadow-sm border border-gray-100 mb-5">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <p class="font-semibold text-color1">Configuración BNC</p>
                <div class="mt-2 flex flex-wrap gap-2 text-xs">
                    <span class={`rounded-full px-2 py-1 ${data.config.enabled ? "bg-emerald-100 text-emerald-800" : "bg-gray-100 text-gray-600"}`}>
                        {data.config.enabled ? "Habilitado" : "Deshabilitado"}
                    </span>
                    <span class={`rounded-full px-2 py-1 ${data.config.configured ? "bg-emerald-100 text-emerald-800" : "bg-red-100 text-red-800"}`}>
                        {data.config.configured ? "Credenciales completas" : "Faltan credenciales"}
                    </span>
                    <span class={`rounded-full px-2 py-1 ${data.config.has_working_key ? "bg-emerald-100 text-emerald-800" : "bg-amber-100 text-amber-800"}`}>
                        WorkingKey: {data.config.has_working_key ? "en caché" : "sin renovar"}
                    </span>
                    <span class="rounded-full bg-gray-100 px-2 py-1">Modo: {data.config.mode}</span>
                    <span class="rounded-full bg-gray-100 px-2 py-1">Webhook: {data.config.webhook_auth}</span>
                </div>
            </div>
            <div class="flex flex-wrap gap-2">
                <button
                    type="button"
                    on:click={testConnection}
                    disabled={testing}
                    class="rounded-lg bg-color1 px-3 py-2 text-xs font-semibold text-white disabled:opacity-60"
                >
                    {testing ? "Probando..." : "Probar conexión"}
                </button>
                <button
                    type="button"
                    on:click={() => post("/dashboard/pagos/verificaciones-bnc/verificar-todas")}
                    class="rounded-lg bg-amber-500 px-3 py-2 text-xs font-semibold text-white"
                >
                    Verificar pendientes
                </button>
                <button
                    type="button"
                    on:click={() => post("/dashboard/pagos/verificaciones-bnc/procesar-notificaciones")}
                    class="rounded-lg bg-blue-600 px-3 py-2 text-xs font-semibold text-white"
                >
                    Procesar notificaciones
                </button>
            </div>
        </div>

        <div class="mt-4 grid gap-2 md:grid-cols-2 text-xs text-gray-600">
            <div class="rounded-lg bg-gray-50 p-3">
                <p class="font-semibold text-gray-700 mb-1">Webhook para el banco</p>
                <div class="flex items-center gap-2">
                    <code class="truncate">{data.config.webhook_url}</code>
                    <button type="button" on:click={copyWebhook} class="text-color1 font-semibold">Copiar</button>
                </div>
            </div>
            <div class="rounded-lg bg-gray-50 p-3">
                <p class="font-semibold text-gray-700 mb-1">Datos configurados</p>
                <p>ClientGUID: {data.config.client_guid || "—"}</p>
                <p>ClientID: {data.config.client_id || "—"}</p>
                <p>Cuenta BNC: {data.config.account_number || "—"}</p>
                <p>Último login: {data.config.last_logon_at || "—"}</p>
            </div>
        </div>

        {#if data.config.missing.length > 0}
            <p class="mt-3 text-xs text-red-600">
                Faltan: {data.config.missing.join(", ")}.
            </p>
        {/if}
    </div>

    <div class="rounded-xl bg-white p-4 shadow-sm border border-gray-100 mb-5">
        <div class="flex flex-wrap items-end gap-3 mb-3">
            <label class="text-xs font-semibold text-gray-500">
                Estado solicitud
                <select bind:value={filters.status} on:change={applyFilters} class="mt-1 block rounded-lg border-gray-300 text-sm">
                    <option value="pending">Pendientes / error</option>
                    <option value="verified">Verificadas</option>
                    <option value="rejected">Rechazadas</option>
                    <option value="expired">Expiradas</option>
                    <option value="all">Todas</option>
                </select>
            </label>
            <label class="text-xs font-semibold text-gray-500">
                Buscar
                <input
                    bind:value={filters.search}
                    on:keydown={(e) => e.key === "Enter" && applyFilters()}
                    placeholder="Referencia, representante..."
                    class="mt-1 block w-64 rounded-lg border-gray-300 text-sm"
                />
            </label>
            <button type="button" on:click={applyFilters} class="rounded-lg bg-gray-800 px-3 py-2 text-xs font-semibold text-white">
                Filtrar
            </button>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-xs">
                <thead class="bg-gray-100 text-gray-600 uppercase">
                    <tr>
                        <th class="p-2 text-left">ID</th>
                        <th class="p-2 text-left">Referencia</th>
                        <th class="p-2 text-left">Representante</th>
                        <th class="p-2 text-left">Estudiantes</th>
                        <th class="p-2 text-right">Monto</th>
                        <th class="p-2 text-left">Fecha</th>
                        <th class="p-2 text-left">Estado</th>
                        <th class="p-2 text-left">Intentos / error</th>
                        <th class="p-2 text-left">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    {#each data.requests.data as row}
                        <tr class="border-b border-gray-100 align-top">
                            <td class="p-2">{row.id}</td>
                            <td class="p-2 font-mono">{row.reference || "—"}</td>
                            <td class="p-2">
                                {row.user?.name} {row.user?.last_name}
                                <div class="text-gray-400">CI {row.user?.ci}</div>
                            </td>
                            <td class="p-2 max-w-[220px]">
                                {row.students.length > 0 ? row.students.join(", ") : "—"}
                            </td>
                            <td class="p-2 text-right font-semibold">
                                ${money(row.amount_usd)}
                                <div class="text-gray-400">Bs {money(row.amount_ves)}</div>
                            </td>
                            <td class="p-2">{row.transaction_date}</td>
                            <td class="p-2">
                                <span class={`rounded-full px-2 py-1 ${statusClass(row.status)}`}>{row.status_label}</span>
                                {#if row.verification_source}
                                    <div class="text-gray-400 mt-1">{row.verification_source}</div>
                                {/if}
                            </td>
                            <td class="p-2 max-w-[250px]">
                                {row.attempts}
                                {#if row.last_error}
                                    <div class="text-red-600 mt-1">{row.last_error}</div>
                                {/if}
                            </td>
                            <td class="p-2">
                                <div class="flex flex-col gap-1">
                                    {#if row.status !== "verified"}
                                        <button type="button" on:click={() => verify(row.id)} class="text-left text-blue-700 font-semibold">Verificar</button>
                                        <button type="button" on:click={() => verifyManual(row.id)} class="text-left text-emerald-700 font-semibold">Verificar manual</button>
                                        <button type="button" on:click={() => reject(row.id)} class="text-left text-red-600 font-semibold">Rechazar</button>
                                    {:else if row.payment_id}
                                        <a href={`/dashboard/pagos?search=${row.reference || ""}`} class="text-left text-emerald-700 font-semibold">Pago #{row.payment_id}</a>
                                    {/if}
                                </div>
                            </td>
                        </tr>
                    {/each}
                    {#if data.requests.data.length === 0}
                        <tr><td colspan="9" class="p-4 text-center text-gray-400">No hay solicitudes con este filtro.</td></tr>
                    {/if}
                </tbody>
            </table>
        </div>

        <div class="mt-3 flex flex-wrap gap-1">
            {#each data.requests.links as link}
                {#if link.url}
                    <a href={link.url} class={`rounded px-2 py-1 text-xs ${link.active ? "bg-color1 text-white" : "bg-gray-100 text-gray-700"}`}>{@html link.label}</a>
                {:else}
                    <span class="rounded px-2 py-1 text-xs text-gray-400">{@html link.label}</span>
                {/if}
            {/each}
        </div>
    </div>

    <div class="rounded-xl bg-white p-4 shadow-sm border border-gray-100">
        <div class="flex flex-wrap items-end justify-between gap-3 mb-3">
            <div>
                <p class="font-semibold text-color1">Notificaciones BNC</p>
                <p class="text-xs text-gray-400">Ping, pagos recibidos y notificaciones sin conciliar.</p>
            </div>
            <label class="text-xs font-semibold text-gray-500">
                Estado
                <select bind:value={filters.notification_status} on:change={applyFilters} class="mt-1 block rounded-lg border-gray-300 text-sm">
                    <option value="unmatched">Sin conciliar</option>
                    <option value="received">Recibidas</option>
                    <option value="processed">Procesadas</option>
                    <option value="duplicate">Duplicadas</option>
                    <option value="error">Error</option>
                    <option value="all">Todas</option>
                </select>
            </label>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-xs">
                <thead class="bg-gray-100 text-gray-600 uppercase">
                    <tr>
                        <th class="p-2 text-left">ID</th>
                        <th class="p-2 text-left">Tipo</th>
                        <th class="p-2 text-left">Referencia</th>
                        <th class="p-2 text-right">Monto</th>
                        <th class="p-2 text-left">Fecha</th>
                        <th class="p-2 text-left">Estado</th>
                        <th class="p-2 text-left">Pago / solicitud</th>
                        <th class="p-2 text-left">Detalle</th>
                    </tr>
                </thead>
                <tbody>
                    {#each data.notifications.data as row}
                        <tr class="border-b border-gray-100 align-top">
                            <td class="p-2">{row.id}</td>
                            <td class="p-2">{row.payment_type || "—"}</td>
                            <td class="p-2 font-mono">{row.origin_bank_reference || "—"}</td>
                            <td class="p-2 text-right font-semibold">
                                {money(row.amount)}
                                <div class="text-gray-400">{row.currency_code || "—"}</div>
                            </td>
                            <td class="p-2">{row.transaction_date || "—"}</td>
                            <td class="p-2">
                                <span class={`rounded-full px-2 py-1 ${statusClass(row.status)}`}>{row.status_label}</span>
                                {#if row.error}
                                    <div class="text-red-600 mt-1">{row.error}</div>
                                {/if}
                            </td>
                            <td class="p-2">
                                {#if row.payment_id}<div>Pago #{row.payment_id}</div>{/if}
                                {#if row.request_id}<div>Solicitud #{row.request_id}</div>{/if}
                                {#if !row.payment_id && !row.request_id}—{/if}
                            </td>
                            <td class="p-2">
                                <details>
                                    <summary class="cursor-pointer text-blue-700 font-semibold">Ver payload</summary>
                                    <pre class="mt-2 max-w-[420px] overflow-auto rounded bg-gray-50 p-2 text-[10px]">{JSON.stringify(row.raw_payload, null, 2)}</pre>
                                </details>
                            </td>
                        </tr>
                    {/each}
                    {#if data.notifications.data.length === 0}
                        <tr><td colspan="8" class="p-4 text-center text-gray-400">No hay notificaciones con este filtro.</td></tr>
                    {/if}
                </tbody>
            </table>
        </div>

        <div class="mt-3 flex flex-wrap gap-1">
            {#each data.notifications.links as link}
                {#if link.url}
                    <a href={link.url} class={`rounded px-2 py-1 text-xs ${link.active ? "bg-color1 text-white" : "bg-gray-100 text-gray-700"}`}>{@html link.label}</a>
                {:else}
                    <span class="rounded px-2 py-1 text-xs text-gray-400">{@html link.label}</span>
                {/if}
            {/each}
        </div>
    </div>
</div>
