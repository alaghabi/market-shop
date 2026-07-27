<?php

namespace App\Tests\Functional\Api;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use App\Repository\OrderRepository;

final class OrderTrackingApiTest extends ApiTestCase
{
    protected static ?bool $alwaysBootKernel = true;

    public function testPublicEndpointReturnsOrderStatusForTheCurrentBoutique(): void
    {
        $order = static::getContainer()->get(OrderRepository::class)->findOneBy([], ['createdAt' => 'DESC']);
        self::assertNotNull($order);

        $response = static::createClient()->request(
            'GET',
            sprintf('http://%s.localhost/api/public/order-tracking/%s', $order->getBoutique()->getSlug(), $order->getId()),
        );

        self::assertResponseIsSuccessful();
        $payload = $response->toArray(false);

        self::assertSame((string) $order->getId(), $payload['reference'] ?? null);
        self::assertSame($order->getStatus()->value, $payload['status'] ?? null);
    }

    public function testPublicEndpointReturnsNotFoundForUnknownOrder(): void
    {
        static::createClient()->request(
            'GET',
            'http://demo-hanooti.localhost/api/public/order-tracking/00000000-0000-0000-0000-000000000000',
        );

        self::assertResponseStatusCodeSame(404);
    }
}
