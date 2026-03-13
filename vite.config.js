import { defineConfig } from 'vite';
import { resolve } from 'path';

export default defineConfig({
    build: {
        outDir: 'dist',
        emptyOutDir: false,
        lib: {
            entry: resolve(__dirname, 'resources/js/authkit/authkit.js'),
            name: 'AuthKit',
            fileName: () => 'authkit.js',
            formats: ['iife'],
        },
        rollupOptions: {
            output: {
                entryFileNames: 'js/[name].js',
                assetFileNames: 'assets/[name][extname]',
            },
        },
    },
});