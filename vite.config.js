import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
        tailwindcss(),
    ],
    resolve: {
        alias: {
            '@': '/resources/js',
        },
    },
    server: {
        port: 3000,
        host: '0.0.0.0',
        origin: 'http://localhost:3000',
        cors: {
            origin: ['http://localhost:8000', 'http://127.0.0.1:8000', 'http://localhost:3000'],
            credentials: true,
            methods: ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS'],
            allowedHeaders: ['*'],
            exposedHeaders: ['*']
        },
        hmr: {
            host: 'localhost',
            protocol: 'ws',
        }
    },
    build: {
        rollupOptions: {
            output: {
                manualChunks: {
                    vendor: ['chart.js'],
                }
            }
        }
    }
});
