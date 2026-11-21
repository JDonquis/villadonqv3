import { writable } from "svelte/store";

const emptyState = { unread_count: 0, items: [] };

export const notificationState = writable({ ...emptyState });

function normalize(item) {
    const data = item?.data && typeof item.data === "object" ? item.data : {};
    const merged = { ...data, ...item };
    const readAt = merged.read_at ?? null;

    return {
        id: merged.id ?? `local-${Date.now()}-${Math.random()}`,
        type: merged.type ?? "info",
        title: merged.title ?? "Notificación",
        body: merged.body ?? "",
        url: merged.url ?? null,
        icon: merged.icon ?? "mdi:bell-outline",
        severity: merged.severity ?? "info",
        meta: merged.meta ?? {},
        read_at: readAt,
        created_at: merged.created_at ?? new Date().toISOString(),
        created_at_label: merged.created_at_label ?? "Ahora",
        is_read: merged.is_read ?? Boolean(readAt),
    };
}

export function initNotifications(state) {
    const items = Array.isArray(state?.items)
        ? state.items.map(normalize)
        : [];

    notificationState.set({
        unread_count: Number(state?.unread_count ?? items.filter((item) => !item.is_read).length),
        items,
    });
}

export function pushNotification(notification) {
    const item = normalize(notification);

    notificationState.update((state) => {
        const exists = state.items.some((current) => current.id === item.id);
        const items = exists
            ? state.items
            : [item, ...state.items].slice(0, 15);

        return {
            items,
            unread_count: exists
                ? state.unread_count
                : state.unread_count + (item.is_read ? 0 : 1),
        };
    });
}

export function markReadLocal(id) {
    notificationState.update((state) => {
        let changed = false;

        const items = state.items.map((item) => {
            if (item.id !== id || item.is_read) return item;
            changed = true;
            return { ...item, is_read: true, read_at: new Date().toISOString() };
        });

        return {
            items,
            unread_count: changed
                ? Math.max(0, state.unread_count - 1)
                : state.unread_count,
        };
    });
}

export function markAllReadLocal() {
    notificationState.update((state) => ({
        unread_count: 0,
        items: state.items.map((item) => ({
            ...item,
            is_read: true,
            read_at: item.read_at ?? new Date().toISOString(),
        })),
    }));
}

export function removeLocal(id) {
    notificationState.update((state) => {
        const target = state.items.find((item) => item.id === id);
        return {
            items: state.items.filter((item) => item.id !== id),
            unread_count:
                target && !target.is_read
                    ? Math.max(0, state.unread_count - 1)
                    : state.unread_count,
        };
    });
}

export function clearAllLocal() {
    notificationState.set({ ...emptyState });
}
