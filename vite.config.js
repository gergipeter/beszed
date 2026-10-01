import { defineConfig } from 'vite';
import vue from '@vitejs/plugin-vue';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        vue(),
        laravel({
            input: ['resources/js/app.js'],
            refresh: true,
        }),
    ],
    build: {
        rollupOptions: {
            output: {
                // Framework code changes rarely: its own chunk stays cached across app deploys.
                manualChunks(id) {
                    if (!id.includes('node_modules')) return;
                    if (/node_modules\/(vue|@vue|vue-router|pinia)\//.test(id)) return 'vendor-vue';
                    if (/node_modules\/(axios|follow-redirects)\//.test(id)) return 'vendor-axios';
                },
            },
        },
    },
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
