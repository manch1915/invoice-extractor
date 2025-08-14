import { fileURLToPath, URL } from 'node:url';
import { defineConfig } from 'vitest/config';

// kept apart from vite.config.ts: the Laravel plugin refuses to start a dev server in CI
export default defineConfig({
    resolve: {
        alias: {
            '@': fileURLToPath(new URL('./resources/js', import.meta.url)),
        },
    },
    test: {
        environment: 'jsdom',
        include: ['resources/js/**/*.test.ts'],
    },
});
