import { createContext, useCallback, useEffect, useMemo, useState, type ReactNode } from 'react';
import { getKeycloakClient, initializeKeycloak, loginWithIdentityProvider } from './keycloakClient';
import { clearStoredAccessToken, getStoredAccessToken, persistAccessToken, setCurrentAccessToken } from './getStoredAccessToken';
import type { SocialProvider } from './socialProviders';
import { isBoutiqueSubdomain } from '../screens/public/boutiqueRouting';

type AuthenticatedUser = {
  accessToken: string;
  profile: {
    email: string;
    displayName?: string | null;
    sub: string;
    roles: string[];
    boutiques: Array<{ id: string; name: string; slug: string; status: string }>;
  };
};

export type RegistrationResult = {
  message: string;
  verificationRequired?: boolean;
};

export type RegisterPayload = {
  email: string;
  password: string;
  displayName: string;
  boutiqueName: string;
  boutiqueSlug: string;
};

type AuthContextValue = {
  user: AuthenticatedUser | null;
  isLoading: boolean;
  signIn: (email: string, password: string) => Promise<void>;
  signInWithProvider: (provider?: SocialProvider) => Promise<void>;
  signUp: (payload: RegisterPayload) => Promise<RegistrationResult>;
  signOut: () => Promise<void>;
  getAccessToken: () => string | null;
};

export const AuthContext = createContext<AuthContextValue | null>(null);

export function AuthProvider({ children }: { children: ReactNode }) {
  const [user, setUser] = useState<AuthenticatedUser | null>(null);
  const [isLoading, setIsLoading] = useState(true);
  const [keycloakToken, setKeycloakToken] = useState<string | null>(null);

  const syncProfile = useCallback(async (token: string) => {
    const response = await fetch('/api/auth/me', {
      headers: { Authorization: `Bearer ${token}` },
    });
    if (!response.ok) {
      throw new Error('Profil Keycloak indisponible.');
    }

    const payload = await response.json() as {
      user: {
        email: string;
        displayName?: string | null;
        firstname?: string | null;
        lastname?: string | null;
        roles?: string[];
        boutiques?: Array<{ id: string; name: string; slug: string; status: string }>;
      };
    };
    const profile = payload.user;

    setUser({
      accessToken: token,
      profile: {
        email: profile.email,
        displayName: profile.displayName,
        sub: profile.email,
        roles: profile.roles ?? [],
        boutiques: profile.boutiques ?? [],
      },
    });
  }, []);

  useEffect(() => {
    if (isBoutiqueSubdomain()) {
      setCurrentAccessToken(null);
      setKeycloakToken(null);
      setUser(null);
      setIsLoading(false);
      return;
    }

    let cancelled = false;
    const keycloak = getKeycloakClient();
    const localToken = getStoredAccessToken();

    initializeKeycloak()
      .then(async (authenticated) => {
        if (authenticated && keycloak.token && !cancelled) {
          clearStoredAccessToken();
          setKeycloakToken(keycloak.token);
          setCurrentAccessToken(keycloak.token);
          await syncProfile(keycloak.token);
          return;
        }

        if (localToken && !cancelled) {
          setKeycloakToken(null);
          setCurrentAccessToken(localToken);
          await syncProfile(localToken);
        }
      })
      .catch(() => {
        if (!cancelled) {
          clearStoredAccessToken();
          setKeycloakToken(null);
          setCurrentAccessToken(null);
          setUser(null);
        }
      })
      .finally(() => {
        if (!cancelled) setIsLoading(false);
      });

    keycloak.onTokenExpired = () => {
      void keycloak.updateToken(30)
        .then((refreshed) => {
          if (refreshed && keycloak.token) {
            setKeycloakToken(keycloak.token);
            setCurrentAccessToken(keycloak.token);
            return syncProfile(keycloak.token);
          }

          return undefined;
        })
        .catch(() => {
          clearStoredAccessToken();
          setKeycloakToken(null);
          setCurrentAccessToken(null);
          setUser(null);
        });
    };

    return () => {
      cancelled = true;
      keycloak.onTokenExpired = undefined;
    };
  }, [syncProfile]);

  const authenticate = useCallback(async (url: string, body?: unknown) => {
    const response = await fetch(url, {
      method: 'POST',
      headers: body ? { 'Content-Type': 'application/json' } : undefined,
      body: body ? JSON.stringify(body) : undefined,
    });

    if (!response.ok) {
      const payload = await response.json().catch(() => ({ message: 'Authentification impossible.' })) as { message?: string };
      throw new Error(payload.message ?? 'Authentification impossible.');
    }

    const payload = await response.json() as {
      accessToken: string;
      user: {
        email: string;
        displayName?: string | null;
        roles: string[];
        boutiques?: Array<{ id: string; name: string; slug: string; status: string }>;
      };
    };
    const authenticatedUser: AuthenticatedUser = {
      accessToken: payload.accessToken,
      profile: {
        email: payload.user.email,
        displayName: payload.user.displayName,
        sub: payload.user.email,
        roles: payload.user.roles,
        boutiques: payload.user.boutiques ?? [],
      },
    };

    setKeycloakToken(null);
    persistAccessToken(authenticatedUser.accessToken);
    setUser(authenticatedUser);
  }, []);

  const signIn = useCallback(async (email: string, password: string) => {
    await authenticate('/api/auth/login', { email, password });
  }, [authenticate]);

  const signInWithProvider = useCallback(async (provider?: SocialProvider) => {
    await loginWithIdentityProvider(provider);
  }, []);

  const signUp = useCallback(async (payload: RegisterPayload) => {
    const response = await fetch('/api/auth/register', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload),
    });
    const result = await response.json().catch(() => ({})) as RegistrationResult;
    if (!response.ok) {
      throw new Error(result.message ?? 'Inscription impossible.');
    }

    return result;
  }, []);

  const signOut = useCallback(async () => {
    const keycloak = getKeycloakClient();
    if (keycloak.authenticated) {
      clearStoredAccessToken();
      await keycloak.logout({ redirectUri: window.location.origin });
      return;
    }

    setUser(null);
    setKeycloakToken(null);
    clearStoredAccessToken();
    setCurrentAccessToken(null);
  }, []);

  const getAccessToken = useCallback(() => {
    return keycloakToken ?? user?.accessToken ?? null;
  }, [keycloakToken, user]);

  const value = useMemo(
    () => ({ user, isLoading, signIn, signInWithProvider, signUp, signOut, getAccessToken }),
    [user, isLoading, signIn, signInWithProvider, signUp, signOut, getAccessToken],
  );

  return <AuthContext value={value}>{children}</AuthContext>;
}
