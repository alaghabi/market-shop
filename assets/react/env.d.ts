declare namespace NodeJS {
  interface ProcessEnv {
    KEYCLOAK_PUBLIC_URL?: string;
    KEYCLOAK_CLIENT_ID?: string;
  }
}

interface ImportMetaEnv {
  readonly VITE_MERCURE_PUBLIC_URL?: string;
}

interface ImportMeta {
  readonly env?: ImportMetaEnv;
}

interface Window {
  __boutiqueSlug__?: string;
}
