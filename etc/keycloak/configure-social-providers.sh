#!/usr/bin/env bash

set -Eeuo pipefail

KCADM="${KCADM:-/opt/keycloak/bin/kcadm.sh}"
KEYCLOAK_SERVER_URL="${KEYCLOAK_SERVER_URL:-http://localhost:8080}"
KEYCLOAK_REALM="${KEYCLOAK_REALM:-hanooti}"

: "${KEYCLOAK_ADMIN_USERNAME:?KEYCLOAK_ADMIN_USERNAME is required}"
: "${KEYCLOAK_ADMIN_PASSWORD:?KEYCLOAK_ADMIN_PASSWORD is required}"
: "${GOOGLE_CLIENT_ID:?GOOGLE_CLIENT_ID is required}"
: "${GOOGLE_CLIENT_SECRET:?GOOGLE_CLIENT_SECRET is required}"
: "${MICROSOFT_CLIENT_ID:?MICROSOFT_CLIENT_ID is required}"
: "${MICROSOFT_CLIENT_SECRET:?MICROSOFT_CLIENT_SECRET is required}"
: "${FACEBOOK_CLIENT_ID:?FACEBOOK_CLIENT_ID is required}"
: "${FACEBOOK_CLIENT_SECRET:?FACEBOOK_CLIENT_SECRET is required}"

"$KCADM" config credentials \
    --server "$KEYCLOAK_SERVER_URL" \
    --realm master \
    --user "$KEYCLOAK_ADMIN_USERNAME" \
    --password "$KEYCLOAK_ADMIN_PASSWORD" \
    >/dev/null

configure_provider() {
    local alias="$1"
    local provider_id="$2"
    local client_id="$3"
    local client_secret="$4"
    shift 4

    local endpoint="identity-provider/instances/${alias}"
    local common_args=(
        -r "$KEYCLOAK_REALM"
        -s "alias=${alias}"
        -s "providerId=${provider_id}"
        -s 'enabled=true'
        -s 'config.useJwksUrl=true'
        -s 'config.trustEmail=true'
        -s "config.clientId=${client_id}"
        -s "config.clientSecret=${client_secret}"
        "$@"
    )

    if "$KCADM" get "$endpoint" -r "$KEYCLOAK_REALM" >/dev/null 2>&1; then
        "$KCADM" update "$endpoint" "${common_args[@]}" >/dev/null
    else
        "$KCADM" create identity-provider/instances "${common_args[@]}" >/dev/null
    fi
}

configure_provider google google "$GOOGLE_CLIENT_ID" "$GOOGLE_CLIENT_SECRET"
configure_provider microsoft microsoft "$MICROSOFT_CLIENT_ID" "$MICROSOFT_CLIENT_SECRET" \
    -s "config.tenantId=${MICROSOFT_TENANT_ID:-common}"
configure_provider facebook facebook "$FACEBOOK_CLIENT_ID" "$FACEBOOK_CLIENT_SECRET"

# New brokered users are customers. Existing Hanooti users are resolved by
# email and keep the roles stored in the application database.
"$KCADM" add-roles \
    --rname "default-roles-${KEYCLOAK_REALM}" \
    --rolename CUSTOMER \
    -r "$KEYCLOAK_REALM" \
    >/dev/null

printf 'Configured Keycloak providers: google, microsoft, facebook\n'
