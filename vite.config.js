import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/theme.css','resources/js/app.js',
                'resources/js/modules/documents-show.js',
                'resources/js/modules/platform-tenant-show.js',
                'resources/js/modules/platform-tenants.js',
            ],
            refresh: true,
        }),
    ],
});
