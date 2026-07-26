<?php

namespace App\Tests\Service\Delivery;

use App\Entity\DeliveryCompany;
use App\Entity\DeliveryEndpoint;
use App\Enum\DeliveryAuthType;
use App\Enum\DeliveryEndpointType;
use App\Enum\DeliveryHttpMethod;
use App\Enum\DeliveryResponseType;
use App\Service\Delivery\Connector\DeliveryConnectorContext;
use App\Service\Delivery\Connector\GenericHttpConnector;
use App\Service\Delivery\Connector\IntigoConnector;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class IntigoConnectorTest extends TestCase
{
    public function testCreateShipmentUsesApiKeyHeader(): void
    {
        $requests = [];
        $client = new MockHttpClient(function (string $method, string $url, array $options) use (&$requests): MockResponse {
            $requests[] = [$method, $url, $options];

            return new MockResponse('{"tracking_number":"INT-9","status":"created"}', ['http_code' => 201]);
        });

        $company = new DeliveryCompany(
            name: 'Intigo',
            slug: 'intigo',
            baseUrl: 'https://api.intigo.tn',
            provider: 'intigo',
            authType: DeliveryAuthType::ApiKey,
            authConfig: ['headerName' => 'X-Api-Key'],
            mappingConfig: ['reference' => 'ORD-1'],
        );
        $company->addEndpoint(new DeliveryEndpoint(
            company: $company,
            type: DeliveryEndpointType::CreateShipment,
            name: 'Create',
            url: '/v1/shipments',
            httpMethod: DeliveryHttpMethod::Post,
            responseType: DeliveryResponseType::Json,
        ));

        $context = new DeliveryConnectorContext(
            company: $company,
            credential: null,
            decryptedCredentials: ['apiKey' => 'intigo-key'],
            mappedBody: ['reference' => 'ORD-1'],
        );

        $result = (new IntigoConnector(new GenericHttpConnector($client)))->createShipment($context);

        self::assertTrue($result->success);
        self::assertSame('INT-9', $result->trackingNumber);
        $headers = $requests[0][2]['normalized_headers'] ?? [];
        self::assertSame('X-Api-Key: intigo-key', $headers['x-api-key'][0] ?? null);
    }

    public function testSupportsProviderCode(): void
    {
        $connector = new IntigoConnector(new GenericHttpConnector(new MockHttpClient()));

        self::assertTrue($connector->supports('intigo'));
        self::assertFalse($connector->supports('navex'));
    }
}
