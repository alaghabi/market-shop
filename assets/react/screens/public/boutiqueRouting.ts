import { getStoredAccessToken } from '../../auth/getStoredAccessToken';
import { getKeycloakAccessToken } from '../../auth/keycloakClient';

const EXCLUDED_SUBDOMAINS = ['www', 'api', 'admin', 'auth', 'backoffice', 'app', 'mail', 'staging', 'dev'];

export function resolveBoutiqueSlug(pathPattern: RegExp): string {
  const pathSlug = window.location.pathname.match(pathPattern)?.[1];
  if (pathSlug) return pathSlug;

  const querySlug = new URLSearchParams(window.location.search).get('boutique');
  if (querySlug) return querySlug;

  const hostname = window.location.hostname;
  if (hostname === 'localhost' || /^\d+\.\d+\.\d+\.\d+$/.test(hostname)) return '';

  const labels = hostname.split('.');

  if (labels.length >= 3) {
    const [firstLabel] = labels;
    if (!firstLabel || EXCLUDED_SUBDOMAINS.includes(firstLabel)) return '';
    return firstLabel;
  }

  if (labels.length === 2 && hostname.endsWith('.localhost')) {
    const [firstLabel] = labels;
    if (!firstLabel || EXCLUDED_SUBDOMAINS.includes(firstLabel)) return '';
    return firstLabel;
  }

  return '';
}

export function isBoutiqueSubdomain(): boolean {
  const slug = resolveBoutiqueSlug(/^\/$/);
  return slug.length > 0;
}

export function boutiqueLink(path: string): string {
  const slug = isBoutiqueSubdomain() ? null
    : (resolveBoutiqueSlug(/^\/boutiques\/([^/]+)/) || window.__boutiqueSlug__);
  return slug ? `/boutiques/${slug}${path}` : path;
}

export function boutiqueQuery(slug: string): string {
  return slug ? `?boutiqueSlug=${encodeURIComponent(slug)}` : '';
}

export function authHeaders(): HeadersInit | undefined {
  const token = getKeycloakAccessToken() ?? getStoredAccessToken();

  return token ? { Authorization: `Bearer ${token}` } : undefined;
}
