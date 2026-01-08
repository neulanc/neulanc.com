import type { Locale } from '../i18n/translations';

export function getLocaleFromUrl(url: URL): Locale {
  const pathname = url.pathname;
  if (pathname.startsWith('/en')) {
    return 'en';
  }
  return 'de';
}

export function getLocalizedPath(path: string, locale: Locale): string {
  if (locale === 'de') {
    return path.replace(/^\/en/, '') || '/';
  }
  return `/en${path}`;
}
