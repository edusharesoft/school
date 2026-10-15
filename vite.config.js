import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/scss/app.scss', 'resources/js/app.js'],
            refresh: true,
        }),
    ],
    css: {
        preprocessorOptions: {
            scss: {
                // Bootstrap 5.3 still uses @import; silence Dart Sass deprecation noise.
                silenceDeprecations: ['import', 'global-builtin', 'color-functions'],
                quietDeps: true,
            },
        },
    },
});
