import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';

export default defineConfig({
    optimizeDeps: {
        // Pre-bundle echarts stack at dev server start so Monitor.vue doesn’t trigger
        // on-demand optimize mid-session (stale chunk URLs in open tabs → 404).
        include: [
            'vue-echarts',
            'echarts/core',
            'echarts/charts',
            'echarts/components',
            'echarts/renderers',
        ],
    },
    plugins: [
        laravel({
            input: 'resources/js/app.js',
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
    server: {
        host: '0.0.0.0',
        port: 5173,
        strictPort: true,
        hmr: {
            host: 'localhost',
            port: 5173,
        },
        watch: {
            usePolling: true,
            interval: 1000,
            ignored: ['**/node_modules/**', '**/.git/**'],
        },
    },
});
