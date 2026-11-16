# Documentation

The repository builds three related sites from one Astro project:

| Public site | Source routes | Build directory |
| --- | --- | --- |
| [neulanc.com](https://neulanc.com) | `src/pages/` | `public_html/` |
| [app.neulanc.com](https://app.neulanc.com) | `src/pages/app/` | `public_html/app/` |
| [faces.neulanc.com](https://faces.neulanc.com) | `src/pages/faces/` | `public_html/faces/` |

## Guides

- [Architecture](ARCHITECTURE.md) — domains, routes, components, localization, data, and security
- [Build and deployment](BUILD.md) — setup, commands, generated output, and Hostinger deployment

## Essential rules

1. Edit files in `src/` or `public/`, never generated files in `public_html/`.
2. Keep shared presentation in components and route files small where practical.
3. Add English routes as `/en/` wrappers when both languages share the same implementation.
4. Browser-only data stays local unless a feature explicitly enables synchronization.
5. Run `npm run build` before deployment.
