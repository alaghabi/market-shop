<?php

namespace App\Security;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Http\AccessToken\AccessTokenExtractorInterface;

/**
 * Routes asymmetric OIDC tokens to Symfony's OIDC handler while leaving the
 * legacy HMAC token authenticator available during the migration.
 */
final class KeycloakTokenExtractor implements AccessTokenExtractorInterface
{
    public function extractAccessToken(Request $request): ?string
    {
        $authorization = (string) $request->headers->get('Authorization');
        if (!str_starts_with($authorization, 'Bearer ')) {
            return null;
        }

        $token = trim(substr($authorization, 7));
        $parts = explode('.', $token);
        if (3 !== count($parts)) {
            return null;
        }

        $header = base64_decode(strtr($parts[0], '-_', '+/'), true);
        if (false === $header) {
            return null;
        }

        try {
            $decoded = json_decode($header, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }

        return is_array($decoded) && 'RS256' === ($decoded['alg'] ?? null) ? $token : null;
    }
}
