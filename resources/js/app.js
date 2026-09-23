import { createInertiaApp } from "@inertiajs/svelte";
import { registerSW } from 'virtual:pwa-register';
import Layout from "./components/DashboardLayout.svelte";

const updateSW = registerSW({
    immediate: true,
    onOfflineReady() {
        console.log('PWA ready to work offline');
    },
    onNeedRefresh() {
        console.log('New app version available');
    },
});

createInertiaApp({
    resolve: (name) => {
        const pages = import.meta.glob("./Pages/**/*.svelte", { eager: true });
        let page = pages[`./Pages/${name}.svelte`];
        return {
            default: page.default,
            layout: name.startsWith("Dashboard/") || name.startsWith("Dashboard") ? Layout : undefined,
        };
        return page;
    },
    setup({ el, App, props }) {
        new App({ target: el, props });
    },
});

window.addEventListener('beforeinstallprompt', (event) => {
    event.preventDefault();
    window.deferredPrompt = event;
});

export { updateSW };

