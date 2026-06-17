import { defineConfig } from 'astro/config';
// import sitemap from '@astrojs/sitemap';

export default defineConfig({
    site: 'https://neulanc.com',
    base: '/',
    // integrations: [sitemap()],
    outDir: 'public_html',
    build: {
        format: 'directory',
        assetsPrefix: 'https://neulanc.com'
    },
    i18n: {
        defaultLocale: 'de',
        locales: ['de', 'en'],
        routing: {
            prefixDefaultLocale: false
        }
    }
});