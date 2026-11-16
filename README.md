# neulanc.com

> One Astro codebase for the neulanc website, browser apps, and personal profiles.

[![Astro](https://img.shields.io/badge/Astro-7-BC52EE?logo=astro&logoColor=white)](https://astro.build/)
[![License](https://img.shields.io/badge/License-Apache--2.0-0B6E4F)](LICENSE)

[Website](https://neulanc.com) · [Apps](https://app.neulanc.com) · [Faces](https://faces.neulanc.com)

## What is inside

- **neulanc.com** — landing page, legal pages, and shared site navigation
- **app.neulanc.com** — small privacy-focused browser tools
  - **MeldPDF** merges images and PDFs locally in the browser
  - **SpendFlow** visualizes finances locally with optional end-to-end encrypted sync
- **faces.neulanc.com** — public profiles and portfolios
- **German and English** routes from one shared component system

## Quick start

Requires Node.js `20.19+` or `22.12+`.

```bash
npm ci
npm run dev
```

Open [localhost:4321](http://localhost:4321). Subdomain content is available locally below `/app` and `/faces`.

```bash
npm run build
npm run preview
```

The production build is written to `public_html/` and includes the three Hostinger document roots.

## Project map

```text
src/pages/       Routes for the main site, apps, faces, and legal pages
src/components/  Shared layouts and application components
src/i18n/        Locale types and localized-path helpers
public/          Static files, PHP handlers, and Apache configuration
docs/            Architecture and operational documentation
public_html/     Generated deployment output; never edit directly
```

## Documentation

- [Documentation overview](docs/README.md)
- [Architecture and conventions](docs/ARCHITECTURE.md)
- [Development, build, and deployment](docs/BUILD.md)

## License

Licensed under the [Apache License 2.0](LICENSE).
