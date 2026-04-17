import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/css/marketplace.css',
                'resources/css/admin.css',
                'resources/css/mitra.css',
                'resources/js/app.js',
                'resources/js/marketplace.js',
                'resources/js/panel.js',
            ],
            refresh: true,
        }),
    ],
});
