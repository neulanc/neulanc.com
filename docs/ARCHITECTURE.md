# Architecture

## Overview

neulanc is a static Astro site with a small PHP endpoint for optional SpendFlow synchronization. A single build produces directories used as separate Hostinger document roots.

```text
Astro source
├── main and legal pages ──────────────► neulanc.com
├── browser applications ─────────────► app.neulanc.com
└── profiles and portfolios ──────────► faces.neulanc.com
```

## Source layout

| Path | Purpose |
| --- | --- |
| `src/pages/` | File-based Astro routes |
| `src/components/` | Shared layouts, navigation, consent UI, and app implementations |
| `src/i18n/` | Locale detection and localized paths |
| `src/pages/faces/@neuhold-michael/assets/` | Assets owned by the Michael Neuhold profile |
| `public/` | Files copied unchanged into the build |
| `public/app/api/` | SpendFlow's PHP sync relay and Apache rules |

The main reusable shells are:

- `AppLayout.astro` for browser applications
- `LegalLayout.astro` for legal pages
- `SiteFooter.astro`, `LanguageSwitcher.astro`, and `CookieConsent.astro` across sites

## Routes and localization

German is the default locale and has no URL prefix. English uses `/en/`.

Most English route files are thin wrappers that render the corresponding shared page. Text selection happens inside the shared page or component through the `Locale` helpers. This keeps behavior and markup synchronized across both languages.

During local development, use path-based routes:

- `/app/` and `/app/en/`
- `/app/meldpdf/` and `/app/spendflow/`
- `/faces/` and `/faces/en/`
- `/faces/@neuhold-michael/`

On Hostinger, `/app` and `/faces` become the roots of their respective subdomains.

## Applications and data

### MeldPDF

`MeldPdfApp.astro` processes selected images and PDFs in the browser using `pdf-lib`. Files are not uploaded by the application.

### SpendFlow

`SpendFlowApp.astro` owns the interface and client-side application logic. Projects, categories, entries, device credentials, and encryption keys are stored in browser `localStorage`.

Synchronization is optional:

1. The browser encrypts a project with AES-GCM.
2. `/api/spendflow.php` receives only ciphertext and hashed device credentials.
3. The PHP relay stores private data outside the public document root.
4. Pairing invitations expire after 60 seconds and can only be used once.

The server cannot decrypt project content. Privacy behavior is documented publicly in the privacy page and must remain aligned with implementation changes.

## Build and hosting

Astro writes directory-style routes to `public_html/`. The build script then copies shared `_astro` assets and the custom 404 page into the app and faces roots.

Each document root includes its own `.htaccess` and `404.php`:

- HTTPS is enforced.
- directory-style Astro routes resolve to `index.html`.
- unknown paths use the branded page while retaining HTTP status `404`.

`public_html/` is generated and ignored by Git. Source-controlled deployment files belong in `public/`.

## Conventions

- Components use `PascalCase.astro`; route directories use lowercase URL names.
- Keep app-specific code in a clearly named component or component folder.
- Colocate page-owned images and documents with that page; place globally shared assets in `public/`.
- Preserve German and English feature parity.
- Avoid external processing for privacy-focused tools unless the user explicitly opts in.
- Never commit secrets, generated sync data, `.env` files, or `public_html/`.
