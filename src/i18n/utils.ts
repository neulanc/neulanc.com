export const locales = ['de', 'en'] as const;
export type Locale = (typeof locales)[number];
export const defaultLocale: Locale = 'de';

export function getLocaleFromUrl(url: URL): Locale
{
  const localePathPart = url.pathname
    .split('/')
    .filter(Boolean)
    .find(pathPart => locales.includes(pathPart as Locale));
  return localePathPart
    ? localePathPart as Locale
    : defaultLocale;
}

export function getLocalizedPath(path: string, locale: Locale): string
{
  const pathWithoutLocale = path.replace(/^\/(de|en)(?=\/|$)/, '') || '/';
  return locale === defaultLocale ? pathWithoutLocale : `/${locale}${pathWithoutLocale}`;
}
