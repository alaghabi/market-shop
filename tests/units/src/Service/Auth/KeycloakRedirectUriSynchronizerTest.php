<?php

namespace App\Tests\Service\Auth;

use App\Entity\Boutique;
use App\Service\Auth\KeycloakRedirectUriSynchronizer;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class KeycloakRedirectUriSynchronizerTest extends TestCase
{
    public function testItAddsPublishedBoutiqueRedirectUrisIdempotently(): void
    {
        $clientRepresentation = [
            'id' => 'client-id',
            'clientId' => 'hanooti-web',
            'name' => 'Hanooti Web',
            'enabled' => true,
            'protocol' => 'openid-connect',
            'publicClient' => true,
            'standardFlowEnabled' => true,
            'redirectUris' => ['http://localhost:8082/auth/login'],
            'webOrigins' => ['http://localhost:8082'],
        ];
        $responses = [
            new MockResponse(json_encode(['access_token' => 'token'], JSON_THROW_ON_ERROR)),
            new MockResponse(json_encode([$clientRepresentation], JSON_THROW_ON_ERROR)),
            new MockResponse('', ['http_code' => 204]),
            new MockResponse(json_encode([array_replace($clientRepresentation, [
                'redirectUris' => [
                    'http://localhost:8082/auth/login',
                    'http://localhost:8082/*',
                    'http://demo-beauty.localhost:8082/client/login',
                ],
                'webOrigins' => [
                    'http://localhost:8082',
                    'http://demo-beauty.localhost:8082',
                ],
            ])], JSON_THROW_ON_ERROR)),
        ];
        $requests = [];
        $httpClient = new MockHttpClient(function (string $method, string $url, array $options) use (&$requests, &$responses): MockResponse {
            $requests[] = ['method' => $method, 'url' => $url, 'options' => $options];

            return array_shift($responses);
        });
        $synchronizer = new KeycloakRedirectUriSynchronizer(
            httpClient: $httpClient,
            adminUrl: 'http://keycloak:8080',
            realm: 'hanooti',
            clientId: 'hanooti-web',
            adminUsername: 'admin',
            adminPassword: 'password',
            rootDomain: 'localhost',
            publicScheme: 'http',
            publicPort: '8082',
            enabled: true,
        );
        $boutique = new Boutique('Demo Beauty', 'demo-beauty');
        $boutique->publish();

        self::assertSame(2, $synchronizer->syncAll([$boutique]));
        self::assertCount(3, $requests);
        self::assertContains(
            'http://demo-beauty.localhost:8082/client/login',
            json_decode($requests[2]['options']['body'], true, 512, JSON_THROW_ON_ERROR)['redirectUris'],
        );
        self::assertSame(0, $synchronizer->syncAll([$boutique]));
        self::assertCount(4, $requests);
    }

    public function testItDoesNothingWhenDisabled(): void
    {
        $httpClient = new MockHttpClient(static function (): never {
            self::fail('The Keycloak API must not be called when synchronization is disabled.');
        });
        $synchronizer = new KeycloakRedirectUriSynchronizer(
            httpClient: $httpClient,
            adminUrl: 'http://keycloak:8080',
            realm: 'hanooti',
            clientId: 'hanooti-web',
            adminUsername: 'admin',
            adminPassword: 'password',
            rootDomain: 'localhost',
            publicScheme: 'http',
            publicPort: '8082',
            enabled: false,
        );

        self::assertSame(0, $synchronizer->syncAll([]));
    }
}
