import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';
import tailwindcss from '@tailwindcss/vite';
import { fileURLToPath, URL } from 'node:url';

function isNodeModule(id) {
    return id.includes('/node_modules/') || id.includes('\\node_modules\\');
}

function isTiptapPackage(id) {
    return id.includes('/@tiptap/') || id.includes('\\@tiptap\\');
}

function isProseMirrorPackage(id) {
    return id.includes('prosemirror')
        || id.includes('/@tiptap/pm/')
        || id.includes('\\@tiptap\\pm\\');
}

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/js/app.js',
                'resources/css/app.css'
            ],
            refresh: true,
        }),
        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
        tailwindcss(),
    ],
    resolve: {
        alias: {
            '@': fileURLToPath(new URL('./resources/js', import.meta.url)),
        },
    },
    build: {
        rollupOptions: {
            output: {
                manualChunks(id) {
                    if (!isNodeModule(id)) {
                        return undefined;
                    }

                    if (
                        isTiptapPackage(id)
                        || isProseMirrorPackage(id)
                        || id.includes('/@tiptap/vue-3')
                        || id.includes('\\@tiptap\\vue-3')
                    ) {
                        return 'editor';
                    }

                    return undefined;
                },
            },
        },
    },
});
