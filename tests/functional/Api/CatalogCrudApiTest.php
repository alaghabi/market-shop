<?php

namespace App\Tests\Functional\Api;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use App\Entity\RolePermission;
use App\Repository\RolePermissionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

final class CatalogCrudApiTest extends ApiTestCase
{
    protected static ?bool $alwaysBootKernel = true;

    private const SHOP_HOST = 'demo-hanooti.localhost';

    public function testBoutiqueAdminCanCreateUpdateAndDeleteCategory(): void
    {
        $token = $this->loginAsBoutiqueAdmin();
        $name = 'API CRUD '.bin2hex(random_bytes(4));
        $headers = $this->authHeaders($token);

        $response = static::createClient()->request('POST', '/api/categories', [
            'headers' => $headers,
            'json' => ['name' => $name],
        ]);
        self::assertResponseStatusCodeSame(201);

        $categoryId = $this->extractId($response);

        static::createClient()->request('PATCH', '/api/categories/'.$categoryId, [
            'headers' => $headers + ['Content-Type' => 'application/merge-patch+json'],
            'json' => ['name' => $name.' updated'],
        ]);
        self::assertResponseIsSuccessful();

        $getResponse = static::createClient()->request('GET', '/api/categories/'.$categoryId, [
            'headers' => $headers,
        ]);
        self::assertResponseIsSuccessful();
        self::assertSame($name.' updated', $getResponse->toArray(false)['name']);

        static::createClient()->request('DELETE', '/api/categories/'.$categoryId, [
            'headers' => $headers,
        ]);
        self::assertResponseStatusCodeSame(204);

        static::createClient()->request('GET', '/api/categories/'.$categoryId, [
            'headers' => $headers,
        ]);
        self::assertResponseStatusCodeSame(404);
    }

    public function testBoutiqueAdminCanCreateUpdateAndDeleteProduct(): void
    {
        $token = $this->loginAsBoutiqueAdmin();
        $headers = $this->authHeaders($token);
        $categoryId = $this->createCategory($headers);
        $sku = 'API-'.strtoupper(bin2hex(random_bytes(4)));
        $productId = null;

        try {
            $response = static::createClient()->request('POST', '/api/products', [
                'headers' => $headers,
                'json' => [
                    'name' => 'API Product '.bin2hex(random_bytes(4)),
                    'sku' => $sku,
                    'status' => 'PUBLISHED',
                    'sellingPrice' => 1990,
                    'stockQuantity' => 10,
                    'categoryId' => $categoryId,
                ],
            ]);
            self::assertResponseStatusCodeSame(201);
            $productId = $this->extractId($response);

            static::createClient()->request('PATCH', '/api/products/'.$productId, [
                'headers' => $headers + ['Content-Type' => 'application/merge-patch+json'],
                'json' => [
                    'name' => 'API Product updated',
                    'sku' => $sku,
                    'status' => 'PUBLISHED',
                    'sellingPrice' => 1990,
                    'stockQuantity' => 10,
                    'categoryId' => $categoryId,
                ],
            ]);
            self::assertResponseIsSuccessful();

            $getResponse = static::createClient()->request('GET', '/api/products/'.$productId, [
                'headers' => $headers,
            ]);
            self::assertResponseIsSuccessful();
            self::assertSame('API Product updated', $getResponse->toArray(false)['name']);
        } finally {
            if (null !== $productId) {
                static::createClient()->request('DELETE', '/api/products/'.$productId, [
                    'headers' => $headers,
                ]);
                self::assertResponseStatusCodeSame(204);
            }

            static::createClient()->request('DELETE', '/api/categories/'.$categoryId, [
                'headers' => $headers,
            ]);
            self::assertResponseStatusCodeSame(204);
        }
    }

    public function testCatalogItemIsNotReadableThroughAnotherBoutique(): void
    {
        $token = $this->loginAsBoutiqueAdmin();
        $headers = $this->authHeaders($token);
        $categoryId = $this->createCategory($headers);

        try {
            try {
                $response = static::createClient()->request('GET', 'http://demo-beauty-lab.localhost/api/categories/'.$categoryId, [
                    'headers' => $headers,
                ]);
                self::assertContains($response->getStatusCode(), [403, 404]);
            } catch (AccessDeniedHttpException $exception) {
                self::assertSame('Accès à cette boutique refusé.', $exception->getMessage());
            }
        } finally {
            static::createClient()->request('DELETE', '/api/categories/'.$categoryId, [
                'headers' => $headers,
            ]);
            self::assertResponseStatusCodeSame(204);
        }
    }

    /** @return array<string, string> */
    private function authHeaders(string $token): array
    {
        return [
            'Authorization' => 'Bearer '.$token,
            'Host' => self::SHOP_HOST,
        ];
    }

    private function loginAsBoutiqueAdmin(): string
    {
        $this->ensureCatalogPermissions();

        $response = static::createClient()->request('POST', '/api/auth/login', [
            'headers' => ['Host' => self::SHOP_HOST],
            'json' => [
                'email' => $_SERVER['TEST_BOUTIQUE_ADMIN_EMAIL'] ?? 'owner.demo-hanooti@hanooti.local',
                'password' => $_SERVER['TEST_BOUTIQUE_ADMIN_PASSWORD'] ?? 'password123',
            ],
        ]);
        self::assertResponseStatusCodeSame(200);

        $payload = $response->toArray(false);
        self::assertArrayHasKey('accessToken', $payload);

        return $payload['accessToken'];
    }

    private function ensureCatalogPermissions(): void
    {
        $container = static::getContainer();
        $repository = $container->get(RolePermissionRepository::class);
        $entityManager = $container->get(EntityManagerInterface::class);

        foreach (['product.create', 'product.update', 'product.delete', 'product.category.manage'] as $permission) {
            if (null === $repository->findOneBy(['roleCode' => 'ROLE_BOUTIQUE_ADMIN', 'permission' => $permission])) {
                $entityManager->persist(new RolePermission('ROLE_BOUTIQUE_ADMIN', $permission));
            }
        }

        $entityManager->flush();
    }

    /** @param array<string, string> $headers */
    private function createCategory(array $headers): string
    {
        $response = static::createClient()->request('POST', '/api/categories', [
            'headers' => $headers,
            'json' => ['name' => 'API Product Category '.bin2hex(random_bytes(4))],
        ]);
        self::assertResponseStatusCodeSame(201);

        return $this->extractId($response);
    }

    private function extractId(ResponseInterface $response): string
    {
        $payload = $response->toArray(false);
        self::assertIsString($payload['id'] ?? null);

        return $payload['id'];
    }
}
