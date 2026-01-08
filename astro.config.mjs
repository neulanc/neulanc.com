import { defineConfig } from 'astro/config';

export default defineConfig({
    site: 'https://faces.neulanc.com',
    base: '/',
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