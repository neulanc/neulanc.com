# Development and deployment

## Requirements

- Node.js `20.19+` or `22.12+`
- npm
- PHP-capable Apache hosting for the custom 404 handlers and SpendFlow sync

## Local development

Install exactly the locked dependencies:

```bash
npm ci
```

Start Astro's development server:

```bash
npm run dev
```

Open [localhost:4321](http://localhost:4321). The production subdomains are represented locally by `/app` and `/faces` paths.

## Production build

```bash
npm run build
```

The command:

1. generates the static Astro site in `public_html/`;
2. copies shared `_astro` assets into the app and faces roots;
3. copies the branded 404 page into both subdomain roots; and
4. includes the profile's compatibility PDF path.

`public_html/` is disposable build output. Do not make source changes there because the next build replaces them.

Preview the completed build with:

```bash
npm run preview
```

## Hostinger layout

Upload each generated directory to its matching document root, including hidden `.htaccess` files and `404.php`.

| Domain | Hostinger document root | Local build source |
| --- | --- | --- |
| `neulanc.com` | `public_html/` | `public_html/` |
| `app.neulanc.com` | `public_html/app/` | `public_html/app/` |
| `faces.neulanc.com` | `public_html/faces/` | `public_html/faces/` |

The main-domain upload must not accidentally remove the `app/` and `faces/` subdirectories. Deploy the complete generated structure or upload each document root deliberately.

SpendFlow synchronization also requires:

- `public_html/app/api/spendflow.php`
- `public_html/app/api/.htaccess`
- permission for PHP to create a private `spendflow-private/` directory beside `public_html/`

No API secret needs to be configured: the relay creates its server secret in the private directory on first use.

## Release check

Before uploading:

- run `npm run build` without errors;
- test the main, app, faces, German, and English entry pages;
- test an unknown URL and confirm it returns the branded page with status `404`;
- verify MeldPDF with local sample files; and
- verify SpendFlow locally, then test encrypted sync over HTTPS after deployment.
