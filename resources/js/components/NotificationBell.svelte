<script>
    import { onMount } from "svelte";
    import { page, router } from "@inertiajs/svelte";
    import { initEcho } from "../echo";
    import {
        clearAllLocal,
        initNotifications,
        markAllReadLocal,
        markReadLocal,
        notificationState,
        removeLocal,
    } from "../stores/notifications";

    let open = false;
    let root;
    let pushSupported = false;
    let pushEnabled = false;
    let pushBusy = false;
    let pushMessage = "";
    let pushMessageType = "info";

    $: if ($page?.props?.notification_state) {
        initNotifications($page.props.notification_state);
    }

    $: if ($page?.props?.auth?.id) {
        initEcho($page.props.auth.id);
    }

    onMount(() => {
        const onClick = (event) => {
            if (root && !root.contains(event.target)) {
                open = false;
            }
        };

        document.addEventListener("click", onClick);

        pushSupported =
            "serviceWorker" in navigator &&
            "PushManager" in window &&
            "Notification" in window &&
            Boolean(import.meta.env.VITE_VAPID_PUBLIC_KEY);

        refreshPushState();

        return () => document.removeEventListener("click", onClick);
    });

    function sameBytes(a, b) {
        if (!a || !b || a.length !== b.length) return false;

        for (let i = 0; i < a.length; i++) {
            if (a[i] !== b[i]) return false;
        }

        return true;
    }

    function urlBase64ToUint8Array(base64String) {
        const padding = "=".repeat((4 - (base64String.length % 4)) % 4);
        const base64 = (base64String + padding)
            .replace(/-/g, "+")
            .replace(/_/g, "/");
        const rawData = window.atob(base64);
        const outputArray = new Uint8Array(rawData.length);

        for (let i = 0; i < rawData.length; i++) {
            outputArray[i] = rawData.charCodeAt(i);
        }

        return outputArray;
    }

    function setPushMessage(message, type = "info") {
        pushMessage = message;
        pushMessageType = type;
    }

    async function getPushRegistration() {
        if (!("serviceWorker" in navigator)) {
            return null;
        }

        const registrations = await navigator.serviceWorker.getRegistrations();

        return (
            registrations.find(
                (registration) =>
                    registration.active && registration.pushManager,
            ) ?? null
        );
    }

    async function getOrCreatePushRegistration() {
        let registration = await getPushRegistration();

        if (registration) {
            return registration;
        }

        try {
            const pending = await navigator.serviceWorker.register("/build/sw.js");
            const worker = pending.installing || pending.waiting;

            if (!pending.active && worker) {
                await new Promise((resolve) => {
                    const done = () => {
                        if (
                            worker.state === "activated" ||
                            worker.state === "redundant"
                        ) {
                            resolve();
                        }
                    };

                    worker.addEventListener("statechange", done);
                    done();
                    setTimeout(resolve, 8000);
                });
            }

            return (await getPushRegistration()) ?? (pending.active ? pending : null);
        } catch (error) {
            console.error("No se pudo registrar el service worker de push", error);
            return null;
        }
    }

    async function refreshPushState() {
        if (!pushSupported) return;

        try {
            if (Notification.permission === "denied") {
                pushEnabled = false;
                setPushMessage(
                    "Las notificaciones están bloqueadas para este sitio. Habilítalas desde la configuración del navegador.",
                    "warning",
                );
                return;
            }

            const registration = await getPushRegistration();

            if (!registration) {
                return;
            }

            const subscription = await registration.pushManager.getSubscription();
            pushEnabled = Boolean(subscription);
        } catch (error) {
            console.error("Error al consultar la suscripción push", error);
        }
    }

    async function enablePush() {
        if (!pushSupported || pushBusy) return;

        pushBusy = true;
        setPushMessage("");

        try {
            const permission = await Notification.requestPermission();

            if (permission !== "granted") {
                setPushMessage(
                    "El navegador no dio permiso para mostrar notificaciones. Habilítalas en la configuración del sitio e inténtalo de nuevo.",
                    "warning",
                );
                return;
            }

            const registration = await getOrCreatePushRegistration();

            if (!registration) {
                setPushMessage(
                    "No se encontró el service worker de notificaciones. Ejecuta yarn run build y recarga la página, o revisa que /build/sw.js esté accesible.",
                    "warning",
                );
                return;
            }

            const applicationServerKey = urlBase64ToUint8Array(
                import.meta.env.VITE_VAPID_PUBLIC_KEY,
            );

            let subscription = await registration.pushManager.getSubscription();

            if (subscription?.options?.applicationServerKey) {
                const currentKey = new Uint8Array(
                    subscription.options.applicationServerKey,
                );

                if (!sameBytes(currentKey, applicationServerKey)) {
                    await subscription.unsubscribe();
                    subscription = null;
                }
            }

            if (!subscription) {
                subscription = await registration.pushManager.subscribe({
                    userVisibleOnly: true,
                    applicationServerKey,
                });
            }

            const payload = subscription.toJSON();
            payload.content_encoding = (PushManager.supportedContentEncodings || [
                "aes128gcm",
            ])[0];

            const response = await fetch("/dashboard/push/subscribe", {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                    "X-CSRF-TOKEN": document
                        .querySelector('meta[name="csrf-token"]')
                        ?.getAttribute("content"),
                    Accept: "application/json",
                },
                body: JSON.stringify(payload),
            });

            if (!response.ok) {
                let message = `No se pudo guardar la suscripción (HTTP ${response.status}).`;

                try {
                    const body = await response.json();
                    message = body.message || message;
                } catch (error) {
                    // La respuesta no era JSON.
                }

                throw new Error(message);
            }

            pushEnabled = true;
            setPushMessage("Notificaciones del navegador activadas correctamente.", "success");
        } catch (error) {
            console.error("Error al activar notificaciones push", error);
            setPushMessage(
                error?.message ||
                    "No se pudieron activar las notificaciones del navegador.",
                "danger",
            );
        } finally {
            pushBusy = false;
        }
    }

    async function disablePush() {
        if (pushBusy) return;

        pushBusy = true;
        setPushMessage("");

        try {
            const registration = await getPushRegistration();
            const subscription = await registration?.pushManager.getSubscription();

            if (subscription) {
                await fetch("/dashboard/push/subscribe", {
                    method: "DELETE",
                    headers: {
                        "Content-Type": "application/json",
                        "X-CSRF-TOKEN": document
                            .querySelector('meta[name="csrf-token"]')
                            ?.getAttribute("content"),
                        Accept: "application/json",
                    },
                    body: JSON.stringify({ endpoint: subscription.endpoint }),
                });

                await subscription.unsubscribe();
            }

            pushEnabled = false;
            setPushMessage("Notificaciones del navegador desactivadas.", "info");
        } catch (error) {
            console.error("Error al desactivar notificaciones push", error);
            setPushMessage(
                "No se pudieron desactivar las notificaciones del navegador.",
                "danger",
            );
        } finally {
            pushBusy = false;
        }
    }

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

    function openItem(item) {
        const go = () => {
            open = false;
            if (item.url) {
                router.visit(item.url);
            }
        };

        if (item.is_read) {
            go();
            return;
        }

        markReadLocal(item.id);
        router.post(
            `/dashboard/notificaciones/${item.id}/leer`,
            {},
            {
                preserveScroll: true,
                preserveState: true,
                onSuccess: go,
                onError: go,
            },
        );
    }

    function markAll() {
        if (!$notificationState.unread_count) return;
        markAllReadLocal();
        router.post(
            "/dashboard/notificaciones/leer-todas",
            {},
            { preserveScroll: true, preserveState: true },
        );
    }

    function removeItem(item) {
        removeLocal(item.id);
        router.delete(`/dashboard/notificaciones/${item.id}`, {
            preserveScroll: true,
            preserveState: true,
        });
    }

    function removeAll() {
        if (!$notificationState.items.length) return;
        if (!confirm("¿Eliminar todas las notificaciones?")) return;

        clearAllLocal();
        router.delete("/dashboard/notificaciones", {
            preserveScroll: true,
            preserveState: true,
        });
    }

    function viewAll() {
        open = false;
        router.visit("/dashboard/notificaciones");
    }
</script>

<div class="relative" bind:this={root}>
    <button
        type="button"
        on:click|stopPropagation={() => (open = !open)}
        class="relative w-10 h-10 rounded-xl border border-grayBlue/40 bg-slate-50/80 hover:bg-gray-100 text-color1 hover:text-color2 flex items-center justify-center transition-all duration-150 shadow-2xs"
        title="Notificaciones"
        aria-label="Notificaciones"
        aria-expanded={open}
    >
        <iconify-icon icon="mdi:bell-outline" class="text-xl leading-none"></iconify-icon>
        {#if $notificationState.unread_count > 0}
            <span
                class="absolute -top-1.5 -right-1.5 min-w-5 h-5 px-1 rounded-full bg-red text-white text-[10px] font-bold flex items-center justify-center border-2 border-white"
            >
                {$notificationState.unread_count > 99
                    ? "99+"
                    : $notificationState.unread_count}
            </span>
        {/if}
    </button>

    {#if open}
        <div
            class="absolute right-0 top-full mt-2 w-[360px] max-w-[calc(100vw-2rem)] rounded-2xl bg-white border border-grayBlue/40 shadow-2xl z-50 overflow-hidden"
        >
            <div class="flex items-center justify-between px-4 py-3 border-b border-grayBlue/20">
                <div>
                    <p class="text-sm font-bold text-color1">Notificaciones</p>
                    <p class="text-[11px] text-gray-400">
                        {$notificationState.unread_count} sin leer
                    </p>
                </div>
                {#if $notificationState.items.length}
                    <button
                        type="button"
                        on:click={markAll}
                        class="text-[11px] font-semibold text-color2 hover:text-color1"
                    >
                        Marcar todas
                    </button>
                {/if}
            </div>

            <div class="max-h-96 overflow-y-auto">
                {#if $notificationState.items.length}
                    {#each $notificationState.items as item (item.id)}
                        <div
                            class={`group flex items-start gap-2 px-3 py-3 border-b border-grayBlue/10 last:border-b-0 ${item.is_read ? "bg-white" : "bg-sky-50/50"}`}
                        >
                            <button
                                type="button"
                                on:click={() => openItem(item)}
                                class="flex-1 min-w-0 text-left flex items-start gap-2.5"
                            >
                                <span
                                    class={`mt-0.5 w-8 h-8 rounded-lg border flex items-center justify-center shrink-0 ${severityClass(item.severity)}`}
                                >
                                    <iconify-icon icon={item.icon} class="text-base"></iconify-icon>
                                </span>
                                <span class="min-w-0">
                                    <span class="flex items-center gap-1.5">
                                        <span class="text-xs font-bold text-gray-800 truncate">
                                            {item.title}
                                        </span>
                                        {#if !item.is_read}
                                            <span class="w-1.5 h-1.5 rounded-full bg-color2 shrink-0"></span>
                                        {/if}
                                    </span>
                                    <span class="block text-[11px] text-gray-500 leading-snug line-clamp-2 mt-0.5">
                                        {item.body}
                                    </span>
                                    <span class="block text-[10px] text-gray-400 mt-1">
                                        {item.created_at_label}
                                    </span>
                                </span>
                            </button>
                            <button
                                type="button"
                                on:click|stopPropagation={() => removeItem(item)}
                                class="w-6 h-6 rounded-md text-gray-300 hover:text-red hover:bg-red/10 flex items-center justify-center shrink-0 opacity-0 group-hover:opacity-100 transition-opacity"
                                title="Eliminar notificación"
                                aria-label="Eliminar notificación"
                            >
                                <iconify-icon icon="mdi:trash-can-outline" class="text-sm"></iconify-icon>
                            </button>
                        </div>
                    {/each}
                {:else}
                    <div class="px-4 py-10 text-center">
                        <iconify-icon
                            icon="mdi:bell-sleep-outline"
                            class="text-3xl text-gray-300"
                        ></iconify-icon>
                        <p class="text-xs text-gray-400 mt-2">No tienes notificaciones.</p>
                    </div>
                {/if}
            </div>

            <div class="px-3 py-2.5 border-t border-grayBlue/20 bg-slate-50/60">
                {#if pushSupported}
                    <button
                        type="button"
                        disabled={pushBusy}
                        on:click={pushEnabled ? disablePush : enablePush}
                        class={`w-full mb-2 px-3 py-2 rounded-lg text-[11px] font-semibold border transition-colors disabled:opacity-60 disabled:cursor-wait ${pushEnabled ? "bg-emerald-50 text-emerald-700 border-emerald-100" : "bg-white text-color1 border-grayBlue/40 hover:bg-slate-50"}`}
                    >
                        {pushBusy
                            ? "Procesando notificaciones..."
                            : pushEnabled
                              ? "Notificaciones del navegador activas"
                              : "Activar notificaciones del navegador"}
                    </button>
                    {#if pushMessage}
                        <p
                            class={`mb-2 text-[10px] leading-snug font-medium ${pushMessageType === "success" ? "text-emerald-700" : pushMessageType === "warning" ? "text-amber-700" : pushMessageType === "danger" ? "text-red-600" : "text-gray-500"}`}
                        >
                            {pushMessage}
                        </p>
                    {/if}
                {/if}
                <div class="flex items-center justify-between gap-2">
                    <button
                        type="button"
                        on:click={viewAll}
                        class="text-[11px] font-bold text-color2 hover:text-color1"
                    >
                        Ver todas
                    </button>
                    {#if $notificationState.items.length}
                        <button
                            type="button"
                            on:click={removeAll}
                            class="text-[11px] font-semibold text-red hover:text-red/80"
                        >
                            Eliminar todas
                        </button>
                    {/if}
                </div>
            </div>
        </div>
    {/if}
</div>
