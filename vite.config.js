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
                'tailwind-noir-grid': resolve(__dirname, 'resources/css/authkit/builds/tailwind-noir-grid.css'),
                'tailwind-ivory-gold': resolve(__dirname, 'resources/css/authkit/builds/tailwind-ivory-gold.css'),
                'tailwind-aurora': resolve(__dirname, 'resources/css/authkit/builds/tailwind-aurora.css'),
                'tailwind-amber-silk': resolve(__dirname, 'resources/css/authkit/builds/tailwind-amber-silk.css'),
                'tailwind-neutral': resolve(__dirname, 'resources/css/authkit/builds/tailwind-neutral.css'),
                'tailwind-rose-ash': resolve(__dirname, 'resources/css/authkit/builds/tailwind-rose-ash.css'),
                'tailwind-slate-gold': resolve(__dirname, 'resources/css/authkit/builds/tailwind-slate-gold.css'),
                'tailwind-paper-ink': resolve(__dirname, 'resources/css/authkit/builds/tailwind-paper-ink.css'),
                'tailwind-midnight-blue': resolve(__dirname, 'resources/css/authkit/builds/tailwind-midnight-blue.css'),
                'tailwind-ocean-mist': resolve(__dirname, 'resources/css/authkit/builds/tailwind-ocean-mist.css'),
                'tailwind-imperial-gold': resolve(__dirname, 'resources/css/authkit/builds/tailwind-imperial-gold.css'),


                'bootstrap-forest': resolve(__dirname, 'resources/css/authkit/builds/bootstrap-forest.css'),
                'bootstrap-red-beige': resolve(__dirname, 'resources/css/authkit/builds/bootstrap-red-beige.css'),
                'bootstrap-noir-grid': resolve(__dirname, 'resources/css/authkit/builds/bootstrap-noir-grid.css'),
                'bootstrap-ivory-gold': resolve(__dirname, 'resources/css/authkit/builds/bootstrap-ivory-gold.css'),
                'bootstrap-aurora': resolve(__dirname, 'resources/css/authkit/builds/bootstrap-aurora.css'),
                'bootstrap-amber-silk': resolve(__dirname, 'resources/css/authkit/builds/bootstrap-amber-silk.css'),
                'bootstrap-neutral': resolve(__dirname, 'resources/css/authkit/builds/bootstrap-neutral.css'),
                'bootstrap-rose-ash': resolve(__dirname, 'resources/css/authkit/builds/bootstrap-rose-ash.css'),
                'bootstrap-slate-gold': resolve(__dirname, 'resources/css/authkit/builds/bootstrap-slate-gold.css'),
                'bootstrap-paper-ink': resolve(__dirname, 'resources/css/authkit/builds/bootstrap-paper-ink.css'),
                'bootstrap-midnight-blue': resolve(__dirname, 'resources/css/authkit/builds/bootstrap-midnight-blue.css'),
                'bootstrap-ocean-mist': resolve(__dirname, 'resources/css/authkit/builds/bootstrap-ocean-mist.css'),
                'bootstrap-imperial-gold': resolve(__dirname, 'resources/css/authkit/builds/bootstrap-imperial-gold.css'),
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
                        name === 'tailwind-noir-grid.css' ||
                        name === 'tailwind-ivory-gold.css' ||
                        name === 'tailwind-rose-ash.css' ||
                        name === 'tailwind-aurora.css' ||
                        name === 'tailwind-amber-silk.css' ||
                        name === 'tailwind-neutral.css' ||
                        name === 'tailwind-slate-gold.css' ||
                        name === 'tailwind-paper-ink.css' ||
                        name === 'tailwind-midnight-blue.css' ||
                        name === 'tailwind-ocean-mist.css' ||
                        name === 'tailwind-imperial-gold.css' ||

                        name === 'bootstrap-noir-grid.css' ||
                        name === 'bootstrap-ivory-gold.css' ||
                        name === 'bootstrap-rose-ash.css' ||
                        name === 'bootstrap-aurora.css' ||
                        name === 'bootstrap-neutral.css' ||
                        name === 'bootstrap-slate-gold.css' ||
                        name === 'bootstrap-paper-ink.css' ||
                        name === 'bootstrap-midnight-blue.css' ||
                        name === 'bootstrap-forest.css' ||
                        name === 'bootstrap-amber-silk.css' ||
                        name === 'bootstrap-ocean-mist.css' ||
                        name === 'bootstrap-imperial-gold.css' ||
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