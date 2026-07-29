<?php

namespace App\Service\Auth;

use App\Entity\Boutique;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class KeycloakRedirectUriSynchronizer
{
    private ?string $adminToken = null;

    private int $adminTokenExpiresAt = 0;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $adminUrl,
        private readonly string $realm,
        private readonly string $clientId,
        private readonly string $adminUsername,
        private readonly string $adminPassword,
        private readonly string $rootDomain,
        private readonly string $publicScheme,
        private readonly string $publicPort,
        private readonly bool $enabled,
    ) {
    }

    /** @param iterable<Boutique> $boutiques */
    public function syncAll(iterable $boutiques): int
    {
        if (!$this->enabled) {
            return 0;
        }

        $platformOrigin = $this->originForHost(trim($this->rootDomain, '.'));
        $redirectUris = [$platformOrigin.'/*', $platformOrigin.'/auth/login'];
        $webOrigins = [$platformOrigin];

        foreach ($boutiques as $boutique) {
            if ($boutique->isDeleted() || !$boutique->isPublished()) {
                continue;
            }

            $origin = $this->originForBoutique($boutique);
            $redirectUris[] = $origin.'/client/login';
            $webOrigins[] = $origin;
        }

        return $this->mergeClientSettings($redirectUris, $webOrigins);
    }

    public function syncBoutique(Boutique $boutique): int
    {
        return $this->syncAll([$boutique]);
    }

    /** @param list<string> $redirectUris @param list<string> $webOrigins */
    private function mergeClientSettings(array $redirectUris, array $webOrigins): int
    {
        $client = $this->getClientRepresentation();
        $currentRedirectUris = array_values(array_unique(array_filter(
            $client['redirectUris'] ?? [],
            fn (mixed $uri): bool => is_string($uri) && !$this->hasStaleLocalPort($uri),
        )));
        $currentWebOrigins = array_values(array_unique(array_filter(
            $client['webOrigins'] ?? [],
            fn (mixed $origin): bool => is_string($origin) && !$this->hasSubdomainWildcard($origin) && !$this->hasStaleLocalPort($origin),
        )));

        $nextRedirectUris = array_values(array_unique([...$currentRedirectUris, ...$redirectUris]));
        $nextWebOrigins = array_values(array_unique([...$currentWebOrigins, ...$webOrigins]));

        $redirectChanged = $this->sorted($currentRedirectUris) !== $this->sorted($nextRedirectUris);
        $originsChanged = $this->sorted($currentWebOrigins) !== $this->sorted($nextWebOrigins);

        if (!$redirectChanged && !$originsChanged) {
            return 0;
        }

        $clientUpdate = [
            'id' => $client['id'],
            'clientId' => $client['clientId'] ?? $this->clientId,
            'name' => $client['name'] ?? $this->clientId,
            'enabled' => $client['enabled'] ?? true,
            'protocol' => $client['protocol'] ?? 'openid-connect',
            'publicClient' => $client['publicClient'] ?? true,
            'standardFlowEnabled' => $client['standardFlowEnabled'] ?? true,
            'directAccessGrantsEnabled' => $client['directAccessGrantsEnabled'] ?? false,
            'redirectUris' => $nextRedirectUris,
            'webOrigins' => $nextWebOrigins,
        ];

        $requestBody = json_encode($clientUpdate, JSON_THROW_ON_ERROR);
        $response = $this->httpClient->request(
            'PUT',
            sprintf('%s/admin/realms/%s/clients/%s', rtrim($this->adminUrl, '/'), rawurlencode($this->realm), rawurlencode((string) $client['id'])),
            [
                'auth_bearer' => $this->getAdminToken(),
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Content-Length' => (string) strlen($requestBody),
                ],
                'body' => $requestBody,
            ],
        );

        if ($response->getStatusCode() >= 300) {
            throw new \RuntimeException(sprintf('Keycloak client update failed with HTTP %d: %s', $response->getStatusCode(), $response->getContent(false)));
        }

        return count($nextRedirectUris) - count($currentRedirectUris);
    }

    /** @return array<string, mixed> */
    private function getClientRepresentation(): array
    {
        $response = $this->httpClient->request(
            'GET',
            sprintf('%s/admin/realms/%s/clients?clientId=%s', rtrim($this->adminUrl, '/'), rawurlencode($this->realm), rawurlencode($this->clientId)),
            ['auth_bearer' => $this->getAdminToken()],
        );
        $clients = $response->toArray();

        if (1 !== count($clients) || !is_array($clients[0]) || !isset($clients[0]['id'])) {
            throw new \RuntimeException(sprintf('Keycloak client "%s" was not found in realm "%s".', $this->clientId, $this->realm));
        }

        return $clients[0];
    }

    private function getAdminToken(): string
    {
        if (null !== $this->adminToken && time() < $this->adminTokenExpiresAt) {
            return $this->adminToken;
        }

        $response = $this->httpClient->request(
            'POST',
            sprintf('%s/realms/master/protocol/openid-connect/token', rtrim($this->adminUrl, '/')),
            [
                'body' => [
                    'grant_type' => 'password',
                    'client_id' => 'admin-cli',
                    'username' => $this->adminUsername,
                    'password' => $this->adminPassword,
                ],
            ],
        );
        $payload = $response->toArray();
        $token = $payload['access_token'] ?? null;

        if (!is_string($token) || '' === $token) {
            throw new \RuntimeException('Keycloak admin token was not returned.');
        }

        $expiresIn = is_numeric($payload['expires_in'] ?? null) ? (int) $payload['expires_in'] : 60;
        $this->adminToken = $token;
        $this->adminTokenExpiresAt = time() + max(1, $expiresIn - 10);

        return $this->adminToken;
    }

    private function originForBoutique(Boutique $boutique): string
    {
        $domain = trim((string) $boutique->getCustomDomain());
        if ('' === $domain) {
            return $this->originForHost(sprintf('%s.%s', $boutique->getSlug(), trim($this->rootDomain, '.')));
        }

        $domain = preg_replace('#^https?://#i', '', $domain) ?? $domain;
        $domain = trim(explode('/', $domain, 2)[0]);

        return $this->originForHost($domain, false);
    }

    private function originForHost(string $host, bool $includePublicPort = true): string
    {
        $port = $includePublicPort && '' !== trim($this->publicPort) ? ':'.trim($this->publicPort, ':') : '';

        return sprintf('%s://%s%s', rtrim($this->publicScheme, ':/'), $host, $port);
    }

    private function hasStaleLocalPort(string $uri): bool
    {
        if ('' === trim($this->publicPort)) {
            return false;
        }

        $scheme = preg_quote(rtrim($this->publicScheme, ':/'), '#');
        $root = preg_quote(trim($this->rootDomain, '.'), '#');

        return 1 === preg_match(sprintf('#^%s://(?:[^./]+\.)?%s(?:/auth/login|/client/login)?$#', $scheme, $root), $uri);
    }

    private function hasSubdomainWildcard(string $uri): bool
    {
        return 1 === preg_match('#^https?://\*\.[^/]+(?:/|$)#i', $uri);
    }

    /** @param list<string> $values @return list<string> */
    private function sorted(array $values): array
    {
        sort($values);

        return $values;
    }
}
