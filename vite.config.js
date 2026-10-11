import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/core/theme.css',
                'resources/css/app.css', 
                'resources/js/app.js',
                'resources/css/main.css',
                'resources/css/crm-topbar.css',
                'resources/css/crm-navigation-pro.css',
                'resources/css/crm-layout.css',
                'resources/css/crm-marketing-plans.css',
                'resources/css/crm-material-requests.css',
                'resources/css/crm-product-serials.css'
            ],
            refresh: true,
        }),
        tailwindcss(),
    ],
});
