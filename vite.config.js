import { defineConfig } from "vite";
import laravel from "laravel-vite-plugin";
import { svelte } from "@sveltejs/vite-plugin-svelte";
import { VitePWA } from "vite-plugin-pwa";

export default defineConfig({
    plugins: [
        laravel({
            input: ["resources/css/app.css", "resources/js/app.js"],
            refresh: true,
        }),
        svelte({}),
        VitePWA({
            registerType: "autoUpdate",
            includeAssets: [
                "img/Isotipo-villadonq-blanco.ico",
                "img/Isotipo-villadonq-blanco.png",
                "img/Logo-villadonq-azul-oscuro.png",
            ],
            manifest: {
                id: "/",
                name: "VillaDonq",
                short_name: "VillaDonq",
                description: "Sistema escolar de VillaDonq",
                start_url: "/",
                scope: "/",
                display: "standalone",
                background_color: "#f8fafc",
                theme_color: "#0f172a",
                orientation: "portrait",
                icons: [
                    {
                        src: "/img/icon-144x144.png",
                        sizes: "144x144",
                        type: "image/png",
                        purpose: "any",
                    },
                    {
                        src: "/img/icon-192x192.png",
                        sizes: "192x192",
                        type: "image/png",
                        purpose: "any",
                    },
                    {
                        src: "/img/icon-512x512.png",
                        sizes: "512x512",
                        type: "image/png",
                        purpose: "any maskable", // Permite adaptarlo a diferentes formas de iconos en Android
                    },
                ],
                screenshots: [
                    {
                        src: "/img/wideVilladonq.webp",
                        sizes: "1350x642",
                        type: "image/webp",
                        form_factor: "wide",
                        label: "Vista de escritorio del sistema escolar",
                    },
                    {
                        src: "/img/mobileVilladonq.webp",
                        sizes: "318x666",
                        type: "image/webp",
                        label: "Vista móvil del sistema escolar",
                    },
                ],
            },
            workbox: {
                maximumFileSizeToCacheInBytes: 5 * 1024 * 1024, // 5 MB
                globPatterns: ["**/*.{js,css,html,svg,png,jpg,jpeg,ico}"],
                navigateFallback: null,
                sourcemap: false,
            },
        }),
    ],
});
