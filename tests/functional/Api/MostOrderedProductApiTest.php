<?php

namespace App\Tests\Functional\Api;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;

final class MostOrderedProductApiTest extends ApiTestCase
{
    protected static ?bool $alwaysBootKernel = true;

    public function testPublicEndpointReturnsTheMostOrderedProductForTheCurrentBoutique(): void
    {
        $response = static::createClient()->request(
            'GET',
            'http://demo-hanooti.localhost/api/public/most-ordered-product',
        );

        self::assertResponseIsSuccessful();

        $payload = $response->toArray(false);
        self::assertIsString($payload['id'] ?? null);
        self::assertNotEmpty($payload['name'] ?? null);
        self::assertNotEmpty($payload['slug'] ?? null);
        self::assertSame(2, $payload['quantitySold'] ?? null);
    }

    public function testPublicEndpointDoesNotExposeAnotherBoutiqueProduct(): void
    {
        $client = static::createClient();
        $first = $client->request('GET', 'http://demo-hanooti.localhost/api/public/most-ordered-product')->toArray(false);
        $second = $client->request('GET', 'http://demo-beauty-lab.localhost/api/public/most-ordered-product')->toArray(false);

        self::assertResponseIsSuccessful();
        self::assertNotSame($first['id'] ?? null, $second['id'] ?? null);
    }

    public function testPublicEndpointResolvesBoutiqueFromQueryForPathBasedFrontOffice(): void
    {
        $response = static::createClient()->request(
            'GET',
            'http://localhost/api/public/most-ordered-product?boutiqueSlug=demo-hanooti',
        );

        self::assertResponseIsSuccessful();
        self::assertSame('demo-hanooti', $response->toArray(false)['boutiqueSlug'] ?? null);
    }
}
