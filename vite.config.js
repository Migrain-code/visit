import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/scss/app.scss',
                // Font Awesome AYRI dosya: sayfanın ilk çizimini beklemesin diye
                // engellemeyen biçimde yüklenir (layouts/app.blade.php).
                'resources/scss/icons.scss',
                'resources/js/app.js',
                'resources/css/filament/admin/theme.css',
            ],
            refresh: true,
        }),
        /*
         * Tailwind YALNIZ yönetim paneli teması için gerekli
         * (resources/css/filament/admin/theme.css). Sitenin ön yüzü Bootstrap 5
         * kullanır ve resources/scss/app.scss bu eklentiden etkilenmez; çünkü
         * içinde "@import 'tailwindcss'" yoktur.
         */
        tailwindcss(),
    ],
    css: {
        preprocessorOptions: {
            scss: {
                quietDeps: true,
                silenceDeprecations: ['import', 'global-builtin', 'color-functions', 'mixed-decls', 'if-function'],
            },
        },
    },
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
