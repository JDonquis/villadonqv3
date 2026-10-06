// vite.config.js
import { defineConfig } from "file:///home/donquis/work/villadonqv3/node_modules/vite/dist/node/index.js";
import laravel from "file:///home/donquis/work/villadonqv3/node_modules/laravel-vite-plugin/dist/index.js";
import { svelte } from "file:///home/donquis/work/villadonqv3/node_modules/@sveltejs/vite-plugin-svelte/src/index.js";
import { VitePWA } from "file:///home/donquis/work/villadonqv3/node_modules/vite-plugin-pwa/dist/index.js";
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
//# sourceMappingURL=data:application/json;base64,ewogICJ2ZXJzaW9uIjogMywKICAic291cmNlcyI6IFsidml0ZS5jb25maWcuanMiXSwKICAic291cmNlc0NvbnRlbnQiOiBbImNvbnN0IF9fdml0ZV9pbmplY3RlZF9vcmlnaW5hbF9kaXJuYW1lID0gXCIvaG9tZS9kb25xdWlzL3dvcmsvdmlsbGFkb25xdjNcIjtjb25zdCBfX3ZpdGVfaW5qZWN0ZWRfb3JpZ2luYWxfZmlsZW5hbWUgPSBcIi9ob21lL2RvbnF1aXMvd29yay92aWxsYWRvbnF2My92aXRlLmNvbmZpZy5qc1wiO2NvbnN0IF9fdml0ZV9pbmplY3RlZF9vcmlnaW5hbF9pbXBvcnRfbWV0YV91cmwgPSBcImZpbGU6Ly8vaG9tZS9kb25xdWlzL3dvcmsvdmlsbGFkb25xdjMvdml0ZS5jb25maWcuanNcIjtpbXBvcnQgeyBkZWZpbmVDb25maWcgfSBmcm9tIFwidml0ZVwiO1xuaW1wb3J0IGxhcmF2ZWwgZnJvbSBcImxhcmF2ZWwtdml0ZS1wbHVnaW5cIjtcbmltcG9ydCB7IHN2ZWx0ZSB9IGZyb20gXCJAc3ZlbHRlanMvdml0ZS1wbHVnaW4tc3ZlbHRlXCI7XG5pbXBvcnQgeyBWaXRlUFdBIH0gZnJvbSBcInZpdGUtcGx1Z2luLXB3YVwiO1xuXG5leHBvcnQgZGVmYXVsdCBkZWZpbmVDb25maWcoe1xuICAgIHBsdWdpbnM6IFtcbiAgICAgICAgbGFyYXZlbCh7XG4gICAgICAgICAgICBpbnB1dDogW1wicmVzb3VyY2VzL2Nzcy9hcHAuY3NzXCIsIFwicmVzb3VyY2VzL2pzL2FwcC5qc1wiXSxcbiAgICAgICAgICAgIHJlZnJlc2g6IHRydWUsXG4gICAgICAgIH0pLFxuICAgICAgICBzdmVsdGUoe30pLFxuICAgICAgICBWaXRlUFdBKHtcbiAgICAgICAgICAgIHJlZ2lzdGVyVHlwZTogXCJhdXRvVXBkYXRlXCIsXG4gICAgICAgICAgICBpbmNsdWRlQXNzZXRzOiBbXG4gICAgICAgICAgICAgICAgXCJpbWcvSXNvdGlwby12aWxsYWRvbnEtYmxhbmNvLmljb1wiLFxuICAgICAgICAgICAgICAgIFwiaW1nL0lzb3RpcG8tdmlsbGFkb25xLWJsYW5jby5wbmdcIixcbiAgICAgICAgICAgICAgICBcImltZy9Mb2dvLXZpbGxhZG9ucS1henVsLW9zY3Vyby5wbmdcIixcbiAgICAgICAgICAgIF0sXG4gICAgICAgICAgICBtYW5pZmVzdDoge1xuICAgICAgICAgICAgICAgIGlkOiBcIi9cIixcbiAgICAgICAgICAgICAgICBuYW1lOiBcIlZpbGxhRG9ucVwiLFxuICAgICAgICAgICAgICAgIHNob3J0X25hbWU6IFwiVmlsbGFEb25xXCIsXG4gICAgICAgICAgICAgICAgZGVzY3JpcHRpb246IFwiU2lzdGVtYSBlc2NvbGFyIGRlIFZpbGxhRG9ucVwiLFxuICAgICAgICAgICAgICAgIHN0YXJ0X3VybDogXCIvXCIsXG4gICAgICAgICAgICAgICAgc2NvcGU6IFwiL1wiLFxuICAgICAgICAgICAgICAgIGRpc3BsYXk6IFwic3RhbmRhbG9uZVwiLFxuICAgICAgICAgICAgICAgIGJhY2tncm91bmRfY29sb3I6IFwiI2Y4ZmFmY1wiLFxuICAgICAgICAgICAgICAgIHRoZW1lX2NvbG9yOiBcIiMwZjE3MmFcIixcbiAgICAgICAgICAgICAgICBvcmllbnRhdGlvbjogXCJwb3J0cmFpdFwiLFxuICAgICAgICAgICAgICAgIGljb25zOiBbXG4gICAgICAgICAgICAgICAgICAgIHtcbiAgICAgICAgICAgICAgICAgICAgICAgIHNyYzogXCIvaW1nL0xvZ28tdmlsbGFkb25xLWF6dWwtb3NjdXJvLnBuZ1wiLFxuICAgICAgICAgICAgICAgICAgICAgICAgc2l6ZXM6IFwiMjkyeDY2XCIsXG4gICAgICAgICAgICAgICAgICAgICAgICB0eXBlOiBcImltYWdlL3BuZ1wiLFxuICAgICAgICAgICAgICAgICAgICAgICAgcHVycG9zZTogXCJhbnkgXCIsXG4gICAgICAgICAgICAgICAgICAgIH0sXG4gICAgICAgICAgICAgICAgICAgIHtcbiAgICAgICAgICAgICAgICAgICAgICAgIHNyYzogXCIvaW1nL0lzb3RpcG8tdmlsbGFkb25xLWJsYW5jby5wbmdcIixcbiAgICAgICAgICAgICAgICAgICAgICAgIHNpemVzOiBcIjQweDQwXCIsXG4gICAgICAgICAgICAgICAgICAgICAgICB0eXBlOiBcImltYWdlL3BuZ1wiLFxuICAgICAgICAgICAgICAgICAgICAgICAgcHVycG9zZTogXCJtYXNrYWJsZVwiLFxuICAgICAgICAgICAgICAgICAgICB9LFxuICAgICAgICAgICAgICAgICAgICB7XG4gICAgICAgICAgICAgICAgICAgICAgICBzcmM6IFwiL2ltZy8xNDRfSXNvdGlwby12aWxsYWRvbnEtYmxhbmNvLnBuZ1wiLFxuICAgICAgICAgICAgICAgICAgICAgICAgc2l6ZXM6IFwiMTQ0eDE0NFwiLFxuICAgICAgICAgICAgICAgICAgICAgICAgdHlwZTogXCJpbWFnZS9wbmdcIixcbiAgICAgICAgICAgICAgICAgICAgICAgIHB1cnBvc2U6IFwiYW55XCIsXG4gICAgICAgICAgICAgICAgICAgIH0sXG4gICAgICAgICAgICAgICAgXSxcbiAgICAgICAgICAgICAgICBzY3JlZW5zaG90czogW1xuICAgICAgICAgICAgICAgICAgICB7XG4gICAgICAgICAgICAgICAgICAgICAgICBzcmM6IFwiL2ltZy93aWRlVmlsbGFkb25xLndlYnBcIixcbiAgICAgICAgICAgICAgICAgICAgICAgIHNpemVzOiBcIjEzNTB4NjQyXCIsXG4gICAgICAgICAgICAgICAgICAgICAgICB0eXBlOiBcImltYWdlL3dlYnBcIixcbiAgICAgICAgICAgICAgICAgICAgICAgIGZvcm1fZmFjdG9yOiBcIndpZGVcIixcbiAgICAgICAgICAgICAgICAgICAgICAgIGxhYmVsOiBcIlZpc3RhIGRlIGVzY3JpdG9yaW8gZGVsIHNpc3RlbWEgZXNjb2xhclwiLFxuICAgICAgICAgICAgICAgICAgICB9LFxuICAgICAgICAgICAgICAgICAgICB7XG4gICAgICAgICAgICAgICAgICAgICAgICBzcmM6IFwiL2ltZy9tb2JpbGVWaWxsYWRvbnEud2VicFwiLFxuICAgICAgICAgICAgICAgICAgICAgICAgc2l6ZXM6IFwiMzE4eDY2NlwiLFxuICAgICAgICAgICAgICAgICAgICAgICAgdHlwZTogXCJpbWFnZS93ZWJwXCIsXG4gICAgICAgICAgICAgICAgICAgICAgICBsYWJlbDogXCJWaXN0YSBtXHUwMEYzdmlsIGRlbCBzaXN0ZW1hIGVzY29sYXJcIixcbiAgICAgICAgICAgICAgICAgICAgfSxcbiAgICAgICAgICAgICAgICBdLCBcbiAgICAgICAgICAgIH0sXG4gICAgICAgICAgICB3b3JrYm94OiB7XG4gICAgICAgICAgICAgICAgbWF4aW11bUZpbGVTaXplVG9DYWNoZUluQnl0ZXM6IDUgKiAxMDI0ICogMTAyNCwgLy8gNSBNQlxuICAgICAgICAgICAgICAgIGdsb2JQYXR0ZXJuczogW1wiKiovKi57anMsY3NzLGh0bWwsc3ZnLHBuZyxqcGcsanBlZyxpY299XCJdLFxuICAgICAgICAgICAgICAgIG5hdmlnYXRlRmFsbGJhY2s6IG51bGwsXG4gICAgICAgICAgICAgICAgc291cmNlbWFwOiBmYWxzZSxcbiAgICAgICAgICAgIH0sXG4gICAgICAgIH0pLFxuICAgIF0sXG59KTtcbiJdLAogICJtYXBwaW5ncyI6ICI7QUFBNFEsU0FBUyxvQkFBb0I7QUFDelMsT0FBTyxhQUFhO0FBQ3BCLFNBQVMsY0FBYztBQUN2QixTQUFTLGVBQWU7QUFFeEIsSUFBTyxzQkFBUSxhQUFhO0FBQUEsRUFDeEIsU0FBUztBQUFBLElBQ0wsUUFBUTtBQUFBLE1BQ0osT0FBTyxDQUFDLHlCQUF5QixxQkFBcUI7QUFBQSxNQUN0RCxTQUFTO0FBQUEsSUFDYixDQUFDO0FBQUEsSUFDRCxPQUFPLENBQUMsQ0FBQztBQUFBLElBQ1QsUUFBUTtBQUFBLE1BQ0osY0FBYztBQUFBLE1BQ2QsZUFBZTtBQUFBLFFBQ1g7QUFBQSxRQUNBO0FBQUEsUUFDQTtBQUFBLE1BQ0o7QUFBQSxNQUNBLFVBQVU7QUFBQSxRQUNOLElBQUk7QUFBQSxRQUNKLE1BQU07QUFBQSxRQUNOLFlBQVk7QUFBQSxRQUNaLGFBQWE7QUFBQSxRQUNiLFdBQVc7QUFBQSxRQUNYLE9BQU87QUFBQSxRQUNQLFNBQVM7QUFBQSxRQUNULGtCQUFrQjtBQUFBLFFBQ2xCLGFBQWE7QUFBQSxRQUNiLGFBQWE7QUFBQSxRQUNiLE9BQU87QUFBQSxVQUNIO0FBQUEsWUFDSSxLQUFLO0FBQUEsWUFDTCxPQUFPO0FBQUEsWUFDUCxNQUFNO0FBQUEsWUFDTixTQUFTO0FBQUEsVUFDYjtBQUFBLFVBQ0E7QUFBQSxZQUNJLEtBQUs7QUFBQSxZQUNMLE9BQU87QUFBQSxZQUNQLE1BQU07QUFBQSxZQUNOLFNBQVM7QUFBQSxVQUNiO0FBQUEsVUFDQTtBQUFBLFlBQ0ksS0FBSztBQUFBLFlBQ0wsT0FBTztBQUFBLFlBQ1AsTUFBTTtBQUFBLFlBQ04sU0FBUztBQUFBLFVBQ2I7QUFBQSxRQUNKO0FBQUEsUUFDQSxhQUFhO0FBQUEsVUFDVDtBQUFBLFlBQ0ksS0FBSztBQUFBLFlBQ0wsT0FBTztBQUFBLFlBQ1AsTUFBTTtBQUFBLFlBQ04sYUFBYTtBQUFBLFlBQ2IsT0FBTztBQUFBLFVBQ1g7QUFBQSxVQUNBO0FBQUEsWUFDSSxLQUFLO0FBQUEsWUFDTCxPQUFPO0FBQUEsWUFDUCxNQUFNO0FBQUEsWUFDTixPQUFPO0FBQUEsVUFDWDtBQUFBLFFBQ0o7QUFBQSxNQUNKO0FBQUEsTUFDQSxTQUFTO0FBQUEsUUFDTCwrQkFBK0IsSUFBSSxPQUFPO0FBQUE7QUFBQSxRQUMxQyxjQUFjLENBQUMseUNBQXlDO0FBQUEsUUFDeEQsa0JBQWtCO0FBQUEsUUFDbEIsV0FBVztBQUFBLE1BQ2Y7QUFBQSxJQUNKLENBQUM7QUFBQSxFQUNMO0FBQ0osQ0FBQzsiLAogICJuYW1lcyI6IFtdCn0K
