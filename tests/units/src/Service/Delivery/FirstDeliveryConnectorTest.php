<?php

namespace App\Tests\Service\Delivery;

use App\Entity\Boutique;
use App\Entity\DeliveryCompany;
use App\Entity\Order;
use App\Enum\DeliveryAuthType;
use App\Enum\OrderChannel;
use App\Enum\OrderStatus;
use App\Service\Delivery\Connector\DeliveryConnectorContext;
use App\Service\Delivery\Connector\FirstDeliveryConnector;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class FirstDeliveryConnectorTest extends TestCase
{
    public function testCreateShipmentSendsBearerTokenAndClientPayload(): void
    {
        $requests = [];
        $client = new MockHttpClient(function (string $method, string $url, array $options) use (&$requests): MockResponse {
            $requests[] = [$method, $url, $options];

            return new MockResponse(json_encode([
                'status' => 201,
                'isError' => false,
                'result' => ['barCode' => '683375045049', 'link' => 'https://www.firstdeliverygroup.com/api/v2/print?q=x'],
            ], \JSON_THROW_ON_ERROR), ['http_code' => 201]);
        });

        $order = $this->order();
        $result = (new FirstDeliveryConnector($client))->createShipment($this->context($order));

        self::assertTrue($result->success);
        self::assertSame('683375045049', $result->trackingNumber);
        self::assertSame('POST', $requests[0][0]);
        self::assertStringContainsString('/create', $requests[0][1]);
        $authHeader = $requests[0][2]['headers']['Authorization']
            ?? $requests[0][2]['normalized_headers']['authorization'][0]
            ?? null;
        self::assertTrue(
            'Bearer fd-token' === $authHeader || 'Authorization: Bearer fd-token' === $authHeader,
            'Expected Bearer fd-token header, got: '.var_export($authHeader, true),
        );
        $body = json_decode((string) ($requests[0][2]['body'] ?? ''), true);
        self::assertSame('Ala Client', $body['Client']['nom'] ?? null);
        self::assertSame('Tunis', $body['Client']['gouvernerat'] ?? null);
        self::assertSame(25.5, $body['Produit']['prix'] ?? null);
    }

    public function testTestConnectionUsesLocalitiesEndpoint(): void
    {
        $client = new MockHttpClient(new MockResponse(json_encode([
            'status' => 200,
            'isError' => false,
            'result' => [['locality_id' => 1, 'locality_name' => 'Tunis']],
        ], \JSON_THROW_ON_ERROR), ['http_code' => 200]));

        $result = (new FirstDeliveryConnector($client))->testConnection($this->context());

        self::assertTrue($result->success);
        self::assertCount(1, $result->cities ?? []);
    }

    public function testMissingTokenFailsFast(): void
    {
        $client = new MockHttpClient();
        $context = new DeliveryConnectorContext(
            company: $this->company(),
            credential: null,
            decryptedCredentials: [],
        );

        $result = (new FirstDeliveryConnector($client))->testConnection($context);

        self::assertFalse($result->success);
        self::assertStringContainsString('Jeton', (string) $result->errorMessage);
    }

    private function company(): DeliveryCompany
    {
        return new DeliveryCompany(
            name: 'First Delivery',
            slug: 'first-delivery',
            baseUrl: 'https://www.firstdeliverygroup.com/api/v2',
            provider: 'first_delivery',
            authType: DeliveryAuthType::Bearer,
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
            decryptedCredentials: ['token' => 'fd-token'],
            mappedBody: [],
            order: $order,
        );
    }

}
