let currentAccessToken: string | null = null;
const storageKey = 'market-shop.auth.access-token';

export function getStoredAccessToken(): string | null {
  if (currentAccessToken) return currentAccessToken;

  try {
    return window.sessionStorage.getItem(storageKey);
  } catch {
    return null;
  }
}

export function setCurrentAccessToken(token: string | null): void {
  currentAccessToken = token;
}

export function persistAccessToken(token: string): void {
  currentAccessToken = token;

  try {
    window.sessionStorage.setItem(storageKey, token);
  } catch {
    // The in-memory token remains usable when storage is unavailable.
  }
}

export function clearStoredAccessToken(): void {
  try {
    window.sessionStorage.removeItem(storageKey);
  } catch {
    // Storage cleanup is best effort.
  }
}

export function isAccessTokenExpired(token: string): boolean {
  const [, payload] = token.split('.');
  if (!payload) return false;

  try {
    const normalized = payload.replace(/-/g, '+').replace(/_/g, '/');
    const padded = normalized.padEnd(normalized.length + ((4 - normalized.length % 4) % 4), '=');
    const decoded = JSON.parse(window.atob(padded)) as { exp?: number };

    return typeof decoded.exp === 'number' && decoded.exp * 1000 <= Date.now();
  } catch {
    return false;
  }
}
