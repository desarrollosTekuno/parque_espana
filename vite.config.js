import { defineConfig } from "vite";
import laravel from "laravel-vite-plugin";
import vue from "@vitejs/plugin-vue";

export default defineConfig({
    plugins: [
        laravel({
            input: "resources/js/app.js",
            refresh: true,
        }),
        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
    ],
    optimizeDeps: {
        exclude: ["@schedule-x/calendar"],
    },
    server: {
        host: "0.0.0.0",
        cors: true,
        allowedHosts: [".trycloudflare.com"],
        // Para el tunnel rápido (cloudflared tunnel --url), la URL cambia
        // cada vez que se corre — se lee de una variable de entorno en vez
        // de dejarla fija. Sin VITE_TUNNEL_HOST, cae de vuelta a localhost
        // (desarrollo normal sin tunnel).
        origin: process.env.VITE_TUNNEL_URL,
        hmr: process.env.VITE_TUNNEL_HOST
            ? { host: process.env.VITE_TUNNEL_HOST, protocol: "wss", clientPort: 443 }
            : undefined,
    },
});
