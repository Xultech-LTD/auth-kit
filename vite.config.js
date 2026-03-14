import { defineConfig } from 'vite';
import { resolve } from 'path';

export default defineConfig({
    build: {
        outDir: 'dist',
        emptyOutDir: true,
        rollupOptions: {
            input: {
                authkit: resolve(__dirname, 'resources/js/authkit/authkit.js'),
                'tailwind-forest': resolve(__dirname, 'resources/css/authkit/builds/tailwind-forest.css'),
                'tailwind-red-beige': resolve(__dirname, 'resources/css/authkit/builds/tailwind-red-beige.css'),
                'bootstrap-forest': resolve(__dirname, 'resources/css/authkit/builds/bootstrap-forest.css'),
                'bootstrap-red-beige': resolve(__dirname, 'resources/css/authkit/builds/bootstrap-red-beige.css'),
            },
            output: {
                entryFileNames: (chunkInfo) => {
                    if (chunkInfo.name === 'authkit') {
                        return 'js/[name].js';
                    }

                    return 'assets/[name].js';
                },
                assetFileNames: (assetInfo) => {
                    const name = assetInfo.name ?? '';

                    if (
                        name === 'tailwind-forest.css' ||
                        name === 'tailwind-red-beige.css' ||
                        name === 'bootstrap-forest.css' ||
                        name === 'bootstrap-red-beige.css'
                    ) {
                        return 'css/themes/[name][extname]';
                    }

                    return 'assets/[name][extname]';
                },
            },
        },
    },
});