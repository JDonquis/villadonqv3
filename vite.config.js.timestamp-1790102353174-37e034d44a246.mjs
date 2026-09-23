// vite.config.js
import { defineConfig } from "file:///C:/xampp/htdocs/VillaDonqV3/node_modules/vite/dist/node/index.js";
import laravel from "file:///C:/xampp/htdocs/VillaDonqV3/node_modules/laravel-vite-plugin/dist/index.js";
import { svelte } from "file:///C:/xampp/htdocs/VillaDonqV3/node_modules/@sveltejs/vite-plugin-svelte/src/index.js";
import { VitePWA } from "file:///C:/xampp/htdocs/VillaDonqV3/node_modules/vite-plugin-pwa/dist/index.js";
var vite_config_default = defineConfig({
  plugins: [
    laravel({
      input: ["resources/css/app.css", "resources/js/app.js"],
      refresh: true
    }),
    svelte({}),
    VitePWA({
      registerType: "autoUpdate",
      includeAssets: [
        "img/Isotipo-villadonq-blanco.ico",
        "img/Isotipo-villadonq-blanco.png",
        "img/Logo-villadonq-azul-oscuro.png"
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
            src: "/img/Logo-villadonq-azul-oscuro.png",
            sizes: "292x66",
            type: "image/png",
            purpose: "any "
          },
          {
            src: "/img/Isotipo-villadonq-blanco.png",
            sizes: "40x40",
            type: "image/png",
            purpose: "maskable"
          },
          {
            src: "/img/144_Isotipo-villadonq-blanco.png",
            sizes: "144x144",
            type: "image/png",
            purpose: "any"
          }
        ],
        screenshots: [
          {
            src: "/img/wideVilladonq.webp",
            sizes: "1350x642",
            type: "image/webp",
            form_factor: "wide",
            label: "Vista de escritorio del sistema escolar"
          },
          {
            src: "/img/mobileVilladonq.webp",
            sizes: "318x666",
            type: "image/webp",
            label: "Vista m\xF3vil del sistema escolar"
          }
        ]
      },
      workbox: {
        maximumFileSizeToCacheInBytes: 5 * 1024 * 1024,
        // 5 MB
        globPatterns: ["**/*.{js,css,html,svg,png,jpg,jpeg,ico}"],
        navigateFallback: null,
        sourcemap: false
      }
    })
  ]
});
export {
  vite_config_default as default
};
//# sourceMappingURL=data:application/json;base64,ewogICJ2ZXJzaW9uIjogMywKICAic291cmNlcyI6IFsidml0ZS5jb25maWcuanMiXSwKICAic291cmNlc0NvbnRlbnQiOiBbImNvbnN0IF9fdml0ZV9pbmplY3RlZF9vcmlnaW5hbF9kaXJuYW1lID0gXCJDOlxcXFx4YW1wcFxcXFxodGRvY3NcXFxcVmlsbGFEb25xVjNcIjtjb25zdCBfX3ZpdGVfaW5qZWN0ZWRfb3JpZ2luYWxfZmlsZW5hbWUgPSBcIkM6XFxcXHhhbXBwXFxcXGh0ZG9jc1xcXFxWaWxsYURvbnFWM1xcXFx2aXRlLmNvbmZpZy5qc1wiO2NvbnN0IF9fdml0ZV9pbmplY3RlZF9vcmlnaW5hbF9pbXBvcnRfbWV0YV91cmwgPSBcImZpbGU6Ly8vQzoveGFtcHAvaHRkb2NzL1ZpbGxhRG9ucVYzL3ZpdGUuY29uZmlnLmpzXCI7aW1wb3J0IHsgZGVmaW5lQ29uZmlnIH0gZnJvbSBcInZpdGVcIjtcclxuaW1wb3J0IGxhcmF2ZWwgZnJvbSBcImxhcmF2ZWwtdml0ZS1wbHVnaW5cIjtcclxuaW1wb3J0IHsgc3ZlbHRlIH0gZnJvbSBcIkBzdmVsdGVqcy92aXRlLXBsdWdpbi1zdmVsdGVcIjtcclxuaW1wb3J0IHsgVml0ZVBXQSB9IGZyb20gXCJ2aXRlLXBsdWdpbi1wd2FcIjtcclxuXHJcbmV4cG9ydCBkZWZhdWx0IGRlZmluZUNvbmZpZyh7XHJcbiAgICBwbHVnaW5zOiBbXHJcbiAgICAgICAgbGFyYXZlbCh7XHJcbiAgICAgICAgICAgIGlucHV0OiBbXCJyZXNvdXJjZXMvY3NzL2FwcC5jc3NcIiwgXCJyZXNvdXJjZXMvanMvYXBwLmpzXCJdLFxyXG4gICAgICAgICAgICByZWZyZXNoOiB0cnVlLFxyXG4gICAgICAgIH0pLFxyXG4gICAgICAgIHN2ZWx0ZSh7fSksXHJcbiAgICAgICAgVml0ZVBXQSh7XHJcbiAgICAgICAgICAgIHJlZ2lzdGVyVHlwZTogXCJhdXRvVXBkYXRlXCIsXHJcbiAgICAgICAgICAgIGluY2x1ZGVBc3NldHM6IFtcclxuICAgICAgICAgICAgICAgIFwiaW1nL0lzb3RpcG8tdmlsbGFkb25xLWJsYW5jby5pY29cIixcclxuICAgICAgICAgICAgICAgIFwiaW1nL0lzb3RpcG8tdmlsbGFkb25xLWJsYW5jby5wbmdcIixcclxuICAgICAgICAgICAgICAgIFwiaW1nL0xvZ28tdmlsbGFkb25xLWF6dWwtb3NjdXJvLnBuZ1wiLFxyXG4gICAgICAgICAgICBdLFxyXG4gICAgICAgICAgICBtYW5pZmVzdDoge1xyXG4gICAgICAgICAgICAgICAgaWQ6IFwiL1wiLFxyXG4gICAgICAgICAgICAgICAgbmFtZTogXCJWaWxsYURvbnFcIixcclxuICAgICAgICAgICAgICAgIHNob3J0X25hbWU6IFwiVmlsbGFEb25xXCIsXHJcbiAgICAgICAgICAgICAgICBkZXNjcmlwdGlvbjogXCJTaXN0ZW1hIGVzY29sYXIgZGUgVmlsbGFEb25xXCIsXHJcbiAgICAgICAgICAgICAgICBzdGFydF91cmw6IFwiL1wiLFxyXG4gICAgICAgICAgICAgICAgc2NvcGU6IFwiL1wiLFxyXG4gICAgICAgICAgICAgICAgZGlzcGxheTogXCJzdGFuZGFsb25lXCIsXHJcbiAgICAgICAgICAgICAgICBiYWNrZ3JvdW5kX2NvbG9yOiBcIiNmOGZhZmNcIixcclxuICAgICAgICAgICAgICAgIHRoZW1lX2NvbG9yOiBcIiMwZjE3MmFcIixcclxuICAgICAgICAgICAgICAgIG9yaWVudGF0aW9uOiBcInBvcnRyYWl0XCIsXHJcbiAgICAgICAgICAgICAgICBpY29uczogW1xyXG4gICAgICAgICAgICAgICAgICAgIHtcclxuICAgICAgICAgICAgICAgICAgICAgICAgc3JjOiBcIi9pbWcvTG9nby12aWxsYWRvbnEtYXp1bC1vc2N1cm8ucG5nXCIsXHJcbiAgICAgICAgICAgICAgICAgICAgICAgIHNpemVzOiBcIjI5Mng2NlwiLFxyXG4gICAgICAgICAgICAgICAgICAgICAgICB0eXBlOiBcImltYWdlL3BuZ1wiLFxyXG4gICAgICAgICAgICAgICAgICAgICAgICBwdXJwb3NlOiBcImFueSBcIixcclxuICAgICAgICAgICAgICAgICAgICB9LFxyXG4gICAgICAgICAgICAgICAgICAgIHtcclxuICAgICAgICAgICAgICAgICAgICAgICAgc3JjOiBcIi9pbWcvSXNvdGlwby12aWxsYWRvbnEtYmxhbmNvLnBuZ1wiLFxyXG4gICAgICAgICAgICAgICAgICAgICAgICBzaXplczogXCI0MHg0MFwiLFxyXG4gICAgICAgICAgICAgICAgICAgICAgICB0eXBlOiBcImltYWdlL3BuZ1wiLFxyXG4gICAgICAgICAgICAgICAgICAgICAgICBwdXJwb3NlOiBcIm1hc2thYmxlXCIsXHJcbiAgICAgICAgICAgICAgICAgICAgfSxcclxuICAgICAgICAgICAgICAgICAgICB7XHJcbiAgICAgICAgICAgICAgICAgICAgICAgIHNyYzogXCIvaW1nLzE0NF9Jc290aXBvLXZpbGxhZG9ucS1ibGFuY28ucG5nXCIsXHJcbiAgICAgICAgICAgICAgICAgICAgICAgIHNpemVzOiBcIjE0NHgxNDRcIixcclxuICAgICAgICAgICAgICAgICAgICAgICAgdHlwZTogXCJpbWFnZS9wbmdcIixcclxuICAgICAgICAgICAgICAgICAgICAgICAgcHVycG9zZTogXCJhbnlcIixcclxuICAgICAgICAgICAgICAgICAgICB9LFxyXG4gICAgICAgICAgICAgICAgXSxcclxuICAgICAgICAgICAgICAgIHNjcmVlbnNob3RzOiBbXHJcbiAgICAgICAgICAgICAgICAgICAge1xyXG4gICAgICAgICAgICAgICAgICAgICAgICBzcmM6IFwiL2ltZy93aWRlVmlsbGFkb25xLndlYnBcIixcclxuICAgICAgICAgICAgICAgICAgICAgICAgc2l6ZXM6IFwiMTM1MHg2NDJcIixcclxuICAgICAgICAgICAgICAgICAgICAgICAgdHlwZTogXCJpbWFnZS93ZWJwXCIsXHJcbiAgICAgICAgICAgICAgICAgICAgICAgIGZvcm1fZmFjdG9yOiBcIndpZGVcIixcclxuICAgICAgICAgICAgICAgICAgICAgICAgbGFiZWw6IFwiVmlzdGEgZGUgZXNjcml0b3JpbyBkZWwgc2lzdGVtYSBlc2NvbGFyXCIsXHJcbiAgICAgICAgICAgICAgICAgICAgfSxcclxuICAgICAgICAgICAgICAgICAgICB7XHJcbiAgICAgICAgICAgICAgICAgICAgICAgIHNyYzogXCIvaW1nL21vYmlsZVZpbGxhZG9ucS53ZWJwXCIsXHJcbiAgICAgICAgICAgICAgICAgICAgICAgIHNpemVzOiBcIjMxOHg2NjZcIixcclxuICAgICAgICAgICAgICAgICAgICAgICAgdHlwZTogXCJpbWFnZS93ZWJwXCIsXHJcbiAgICAgICAgICAgICAgICAgICAgICAgIGxhYmVsOiBcIlZpc3RhIG1cdTAwRjN2aWwgZGVsIHNpc3RlbWEgZXNjb2xhclwiLFxyXG4gICAgICAgICAgICAgICAgICAgIH0sXHJcbiAgICAgICAgICAgICAgICBdLCBcclxuICAgICAgICAgICAgfSxcclxuICAgICAgICAgICAgd29ya2JveDoge1xyXG4gICAgICAgICAgICAgICAgbWF4aW11bUZpbGVTaXplVG9DYWNoZUluQnl0ZXM6IDUgKiAxMDI0ICogMTAyNCwgLy8gNSBNQlxyXG4gICAgICAgICAgICAgICAgZ2xvYlBhdHRlcm5zOiBbXCIqKi8qLntqcyxjc3MsaHRtbCxzdmcscG5nLGpwZyxqcGVnLGljb31cIl0sXHJcbiAgICAgICAgICAgICAgICBuYXZpZ2F0ZUZhbGxiYWNrOiBudWxsLFxyXG4gICAgICAgICAgICAgICAgc291cmNlbWFwOiBmYWxzZSxcclxuICAgICAgICAgICAgfSxcclxuICAgICAgICB9KSxcclxuICAgIF0sXHJcbn0pO1xyXG4iXSwKICAibWFwcGluZ3MiOiAiO0FBQTJRLFNBQVMsb0JBQW9CO0FBQ3hTLE9BQU8sYUFBYTtBQUNwQixTQUFTLGNBQWM7QUFDdkIsU0FBUyxlQUFlO0FBRXhCLElBQU8sc0JBQVEsYUFBYTtBQUFBLEVBQ3hCLFNBQVM7QUFBQSxJQUNMLFFBQVE7QUFBQSxNQUNKLE9BQU8sQ0FBQyx5QkFBeUIscUJBQXFCO0FBQUEsTUFDdEQsU0FBUztBQUFBLElBQ2IsQ0FBQztBQUFBLElBQ0QsT0FBTyxDQUFDLENBQUM7QUFBQSxJQUNULFFBQVE7QUFBQSxNQUNKLGNBQWM7QUFBQSxNQUNkLGVBQWU7QUFBQSxRQUNYO0FBQUEsUUFDQTtBQUFBLFFBQ0E7QUFBQSxNQUNKO0FBQUEsTUFDQSxVQUFVO0FBQUEsUUFDTixJQUFJO0FBQUEsUUFDSixNQUFNO0FBQUEsUUFDTixZQUFZO0FBQUEsUUFDWixhQUFhO0FBQUEsUUFDYixXQUFXO0FBQUEsUUFDWCxPQUFPO0FBQUEsUUFDUCxTQUFTO0FBQUEsUUFDVCxrQkFBa0I7QUFBQSxRQUNsQixhQUFhO0FBQUEsUUFDYixhQUFhO0FBQUEsUUFDYixPQUFPO0FBQUEsVUFDSDtBQUFBLFlBQ0ksS0FBSztBQUFBLFlBQ0wsT0FBTztBQUFBLFlBQ1AsTUFBTTtBQUFBLFlBQ04sU0FBUztBQUFBLFVBQ2I7QUFBQSxVQUNBO0FBQUEsWUFDSSxLQUFLO0FBQUEsWUFDTCxPQUFPO0FBQUEsWUFDUCxNQUFNO0FBQUEsWUFDTixTQUFTO0FBQUEsVUFDYjtBQUFBLFVBQ0E7QUFBQSxZQUNJLEtBQUs7QUFBQSxZQUNMLE9BQU87QUFBQSxZQUNQLE1BQU07QUFBQSxZQUNOLFNBQVM7QUFBQSxVQUNiO0FBQUEsUUFDSjtBQUFBLFFBQ0EsYUFBYTtBQUFBLFVBQ1Q7QUFBQSxZQUNJLEtBQUs7QUFBQSxZQUNMLE9BQU87QUFBQSxZQUNQLE1BQU07QUFBQSxZQUNOLGFBQWE7QUFBQSxZQUNiLE9BQU87QUFBQSxVQUNYO0FBQUEsVUFDQTtBQUFBLFlBQ0ksS0FBSztBQUFBLFlBQ0wsT0FBTztBQUFBLFlBQ1AsTUFBTTtBQUFBLFlBQ04sT0FBTztBQUFBLFVBQ1g7QUFBQSxRQUNKO0FBQUEsTUFDSjtBQUFBLE1BQ0EsU0FBUztBQUFBLFFBQ0wsK0JBQStCLElBQUksT0FBTztBQUFBO0FBQUEsUUFDMUMsY0FBYyxDQUFDLHlDQUF5QztBQUFBLFFBQ3hELGtCQUFrQjtBQUFBLFFBQ2xCLFdBQVc7QUFBQSxNQUNmO0FBQUEsSUFDSixDQUFDO0FBQUEsRUFDTDtBQUNKLENBQUM7IiwKICAibmFtZXMiOiBbXQp9Cg==
