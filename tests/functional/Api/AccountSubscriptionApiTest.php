<?php

namespace App\Tests\Functional\Api;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use App\Entity\SubscriptionPlan;
use App\Repository\SubscriptionPlanRepository;
use Doctrine\ORM\EntityManagerInterface;

final class AccountSubscriptionApiTest extends ApiTestCase
{
    protected static ?bool $alwaysBootKernel = true;

    private ?string $subscriptionId = null;

    protected function tearDown(): void
    {
        if (is_string($this->subscriptionId)) {
            static::getContainer()->get(EntityManagerInterface::class)->getConnection()->executeStatement(
                'DELETE FROM account_subscription WHERE id = :id',
                ['id' => $this->subscriptionId],
            );
        }

        parent::tearDown();
    }

    public function testBoutiqueAdminCanReadItsAccountSubscription(): void
    {
        $client = static::createClient();
        $headers = $this->boutiqueAdminHeaders($client);

        $response = $client->request('GET', 'http://demo-hanooti.localhost/api/account/subscription', [
            'headers' => $headers,
        ]);

        self::assertResponseStatusCodeSame(200);
        $payload = $response->toArray(false);
        self::assertArrayHasKey('isActive', $payload);
        self::assertArrayHasKey('publishedBoutiques', $payload);
        self::assertArrayHasKey('isUnlimited', $payload);
        self::assertTrue(\array_key_exists('maxBoutiques', $payload) || true === $payload['isUnlimited']);
        self::assertArrayHasKey('renewalPriceTnd', $payload);
    }

    public function testBoutiqueAdminCanSubscribeToAPlan(): void
    {
        $client = static::createClient();
        $headers = $this->boutiqueAdminHeaders($client);
        $plan = $this->plan();

        try {
            $response = $client->request('POST', 'http://demo-hanooti.localhost/api/account/subscription', [
                'headers' => $headers,
                'json' => ['planId' => (string) $plan->getId(), 'extensionIds' => []],
            ]);

            self::assertResponseStatusCodeSame(200);
            $payload = $response->toArray(false);
            self::assertTrue($payload['isActive']);
            self::assertSame((string) $plan->getId(), $payload['planId']);
            self::assertSame($plan->getName(), $payload['planName']);
            $this->subscriptionId = $payload['id'];
        } finally {
            $this->cleanup();
        }
    }

    public function testBoutiqueAdminCanRenewSubscription(): void
    {
        $client = static::createClient();
        $headers = $this->boutiqueAdminHeaders($client);
        $plan = $this->plan();

        try {
            $client->request('POST', 'http://demo-hanooti.localhost/api/account/subscription', [
                'headers' => $headers,
                'json' => ['planId' => (string) $plan->getId(), 'extensionIds' => []],
            ]);
            self::assertResponseStatusCodeSame(200);
            $this->subscriptionId = $client->getResponse()->toArray(false)['id'] ?? null;

            $response = $client->request('POST', 'http://demo-hanooti.localhost/api/account/subscription/renew', [
                'headers' => $headers,
            ]);

            self::assertResponseStatusCodeSame(200);
            $payload = $response->toArray(false);
            self::assertTrue($payload['isActive']);
            self::assertSame((string) $plan->getId(), $payload['planId']);
        } finally {
            $this->cleanup();
        }
    }

    public function testChangePreviewIsReachable(): void
    {
        $client = static::createClient();
        $headers = $this->boutiqueAdminHeaders($client);
        $plan = $this->plan();

        $response = $client->request('GET', 'http://demo-hanooti.localhost/api/account/subscription/change-preview?planId='.$plan->getId(), [
            'headers' => $headers,
        ]);

        self::assertResponseStatusCodeSame(200);
        $payload = $response->toArray(false);
        self::assertSame($plan->getName(), $payload['newPlanName']);
        self::assertSame((string) $plan->getId(), $payload['newPlanId']);
    }

    private function plan(): SubscriptionPlan
    {
        $plan = static::getContainer()->get(SubscriptionPlanRepository::class)->findOneBy(['name' => 'Demo Premium']);
        self::assertInstanceOf(SubscriptionPlan::class, $plan);

        return $plan;
    }

    private function cleanup(): void
    {
        if (is_string($this->subscriptionId)) {
            static::getContainer()->get(EntityManagerInterface::class)->getConnection()->executeStatement(
                'DELETE FROM account_subscription WHERE id = :id',
                ['id' => $this->subscriptionId],
            );
            $this->subscriptionId = null;
        }
    }

    /** @return array<string, string> */
    private function boutiqueAdminHeaders($client): array
    {
        $response = $client->request('POST', 'http://localhost/api/auth/login', [
            'json' => ['email' => 'owner.demo-hanooti@hanooti.local', 'password' => 'password123'],
        ]);
        self::assertResponseStatusCodeSame(200);

        return [
            'Authorization' => 'Bearer '.$response->toArray(false)['accessToken'],
            'Host' => 'demo-hanooti.localhost',
        ];
    }
}
