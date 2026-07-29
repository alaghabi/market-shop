import Keycloak, { type KeycloakInstance } from 'keycloak-js';
import type { SocialProvider } from './socialProviders';

let client: KeycloakInstance | null = null;
let initialization: Promise<boolean> | null = null;

export function getKeycloakClient(): KeycloakInstance {
  client ??= new Keycloak({
    url: process.env.KEYCLOAK_PUBLIC_URL || 'http://auth.localhost:8082',
    realm: 'hanooti',
    clientId: process.env.KEYCLOAK_CLIENT_ID || 'hanooti-web',
  });

  return client;
}

export function initializeKeycloak(): Promise<boolean> {
  initialization ??= getKeycloakClient().init({
    onLoad: 'check-sso',
    pkceMethod: 'S256',
    checkLoginIframe: false,
  }).catch(() => false);

  return initialization;
}

export function initializeKeycloakForLogin(): Promise<boolean> {
  const keycloak = getKeycloakClient();
  if (keycloak.didInitialize) return Promise.resolve(keycloak.authenticated);

  initialization ??= keycloak.init({
    pkceMethod: 'S256',
    checkLoginIframe: false,
  });

  return initialization;
}

export function getKeycloakAccessToken(): string | null {
  return client?.token ?? null;
}

export function loginWithIdentityProvider(provider?: SocialProvider): Promise<void> {
  const options = {
    redirectUri: window.location.href,
    ...(provider ? { idpHint: provider } : {}),
  };

  return initializeKeycloakForLogin().then(() => getKeycloakClient().login(options));
}
