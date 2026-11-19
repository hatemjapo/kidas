import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';
import react from '@vitejs/plugin-react';

export default defineConfig(({ mode }) => ({
    plugins: [
        ...(mode === 'test'
            ? []
            : [
                laravel({
                    input: ['resources/css/app.css', 'resources/js/app.jsx'],
                    refresh: true,
                }),
            ]),
        tailwindcss(),
        react(),
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
    test: {
        environment: 'jsdom',
        globals: true,
        setupFiles: ['tests/js/setup.js'],
        include: ['tests/js/**/*.test.{js,jsx}'],
    },
}));
