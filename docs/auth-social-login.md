# Social Login

Hanooti keeps authentication in Keycloak. Google and Microsoft are
configured as Keycloak identity providers, so Symfony continues to validate
the same Keycloak JWT and the existing business rules remain unchanged.

## Provider callbacks

Register these callback URLs in each provider application:

```text
https://auth.hanooti.com/realms/hanooti/broker/google/endpoint
https://auth.hanooti.com/realms/hanooti/broker/microsoft/endpoint
```

For local development, replace the host with the Keycloak URL exposed by the
local Docker stack.

For the production `hanooti-web` client, allow both the platform and dynamic
boutique hosts:

```text
https://hanooti.shop/*
https://*.hanooti.shop/*
```

Use `https://hanooti.shop` and `https://*.hanooti.shop` as the corresponding
web origins according to the Keycloak version and deployment policy.

Use the scopes `openid email profile`. The provider must return a verified
email. Do not map Google or Microsoft roles to Hanooti admin roles.
New social identities are customers by default; existing verified local users
keep the roles stored in Hanooti.

## Configure Keycloak

The script is idempotent and never stores provider secrets in Git:

```bash
docker compose exec \
  -e GOOGLE_CLIENT_ID \
  -e GOOGLE_CLIENT_SECRET \
  -e MICROSOFT_CLIENT_ID \
  -e MICROSOFT_CLIENT_SECRET \
  -e MICROSOFT_TENANT_ID \
  keycloak bash /opt/keycloak/config/configure-social-providers.sh
```

The variables must be provided by the shell, a local `.env.local` file, or a
production secret manager. `MICROSOFT_TENANT_ID` defaults to `common`.

### Production

1. Create `.env.prod.local` on the production server with the four OAuth vars
   (it is gitignored — never commit provider secrets):

   ```bash
   # /app/.env.prod.local
   GOOGLE_CLIENT_ID=your-google-client-id
   GOOGLE_CLIENT_SECRET=your-google-client-secret
   MICROSOFT_CLIENT_ID=your-microsoft-client-id
   MICROSOFT_CLIENT_SECRET=your-microsoft-client-secret
   MICROSOFT_TENANT_ID=common
   ```

2. Run the configuration script:

   ```bash
   docker compose -f docker-compose.prod.yml exec keycloak bash /opt/keycloak/config/configure-social-providers.sh
   ```

> **Redirect URIs** to register in each provider:
> - `https://auth.hanooti.shop/realms/hanooti/broker/google/endpoint`
> - `https://auth.hanooti.shop/realms/hanooti/broker/microsoft/endpoint`

The frontend uses the aliases `google` and `microsoft` through
Keycloak's `idpHint`. No OAuth client library is needed in Symfony.

The setup script also adds the `CUSTOMER` realm role to Keycloak's default
roles. This only affects new brokered identities. Existing Hanooti users are
resolved by their verified email and keep their application roles.

## Verification

Verify each flow with an existing account before allowing automatic account
linking:

1. Login with email and password.
2. Login with Google and Microsoft.
3. Confirm `/api/auth/me` returns the same local roles and boutiques.
4. Confirm a suspended user is rejected.
5. Confirm a new social identity cannot become a boutique administrator.
6. Confirm customer login still enforces the `social_login` module and quota.
