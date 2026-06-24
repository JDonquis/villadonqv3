import { createInertiaApp } from "@inertiajs/svelte";
import Layout from "./components/DashboardLayout.svelte";

createInertiaApp({
    resolve: (name) => {
        const pages = import.meta.glob("./Pages/**/*.svelte", { eager: true });
        let page = pages[`./Pages/${name}.svelte`];
        return {
            default: page.default,
            layout: name.startsWith("Dashboard/") || name.startsWith("Dashboard")  ?  Layout : undefined ,
        };
        return page;
    },
    setup({ el, App, props }) {
        new App({ target: el, props });
    },
});

