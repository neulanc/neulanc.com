import { defineConfig } from 'astro/config';

export default defineConfig({
    site: 'https://neulanc.com',
    outDir: 'public_html',
    build: {
        format: 'directory',
    }
});