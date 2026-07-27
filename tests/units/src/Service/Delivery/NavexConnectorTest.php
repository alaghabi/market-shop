<?php

namespace App\Tests\Service\Delivery;

use App\Entity\Boutique;
use App\Entity\DeliveryCompany;
use App\Entity\Order;
use App\Enum\DeliveryAuthType;
use App\Enum\OrderChannel;
use App\Enum\OrderStatus;
use App\Service\Delivery\Connector\DeliveryConnectorContext;
use App\Service\Delivery\Connector\NavexConnector;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class NavexConnectorTest extends TestCase
{
    public function testCreateShipmentPostsFormWithBasicAuthToken(): void
    {
        $requests = [];
        $client = new MockHttpClient(function (string $method, string $url, array $options) use (&$requests): MockResponse {
            $requests[] = [$method, $url, $options];

            return new MockResponse(json_encode([
                'status' => 'ok',
                'status_message' => 'Product Added.',
                'barCode' => 'NVX-123',
            ], \JSON_THROW_ON_ERROR), ['http_code' => 201]);
        });

        $result = (new NavexConnector($client))->createShipment($this->context($this->order()));

        self::assertTrue($result->success);
        self::assertSame('NVX-123', $result->trackingNumber);
        self::assertStringContainsString('/api/v1/post.php', $requests[0][1]);
        $headers = $requests[0][2]['normalized_headers'] ?? [];
        self::assertSame('Authorization: Basic '.base64_encode('navex-token:'), $headers['authorization'][0] ?? null);
        parse_str((string) ($requests[0][2]['body'] ?? ''), $form);
        self::assertSame('Ala Client', $form['nom'] ?? null);
        self::assertSame('Tunis', $form['gouvernerat'] ?? null);
        self::assertSame('25.5', $form['prix'] ?? null);
    }

    public function testConnectionRejectsUnauthorized(): void
    {
        $client = new MockHttpClient(new MockResponse('{"status":"error"}', ['http_code' => 401]));

        $result = (new NavexConnector($client))->testConnection($this->context());

        self::assertFalse($result->success);
        self::assertStringContainsString('Authentification', (string) $result->errorMessage);
    }

    private function company(): DeliveryCompany
    {
        return new DeliveryCompany(
            name: 'Navex',
            slug: 'navex',
            baseUrl: 'https://app.navex.tn',
            provider: 'navex',
            authType: DeliveryAuthType::Basic,
        );
    }

    private function order(): Order
    {
        $boutique = new Boutique('Demo', 'demo');
        $order = new Order($boutique, null, OrderChannel::Online, OrderStatus::Paid, 2550, 0, 2550, 'TND');
        $order->setCustomerSnapshot('Ala Client', null, '22111222', '12 rue Test', 'Tunis', null, 'Tunisie', null, 'Tunis');

        return $order;
    }

    private function context(?Order $order = null): DeliveryConnectorContext
    {
        return new DeliveryConnectorContext(
            company: $this->company(),
            credential: null,
            decryptedCredentials: ['token' => 'navex-token'],
            mappedBody: [],
            order: $order,
        );
    }
}
