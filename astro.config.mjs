import { defineConfig } from 'astro/config';
// import sitemap from '@astrojs/sitemap';

export default defineConfig({
    // Keep Vite's disposable cache outside node_modules (important on synced filesystems).
    vite: {
        cacheDir: '.astro/vite'
    },
    site: 'https://neulanc.com',
    base: '/',
    // integrations: [sitemap()],
    outDir: 'public_html',
    build: {
        format: 'directory'
    },
    i18n: {
        defaultLocale: 'de',
        locales: ['de', 'en'],
        routing: {
            prefixDefaultLocale: false
        }
    }
});
