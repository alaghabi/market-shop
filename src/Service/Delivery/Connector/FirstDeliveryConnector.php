<?php

namespace App\Service\Delivery\Connector;

use App\Entity\Order;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Live connector for First Delivery Group (Tunisia).
 *
 * Auth: Bearer token. Docs: https://www.firstdeliverygroup.com/api/v2/documentation
 */
final class FirstDeliveryConnector implements DeliveryProviderInterface
{
    public function __construct(
        private readonly HttpClientInterface $client,
    ) {
    }

    public function supports(string $providerCode): bool
    {
        return 'first_delivery' === $providerCode;
    }

    public function createShipment(DeliveryConnectorContext $context): DeliveryResult
    {
        $order = $context->order;
        if (!$order instanceof Order) {
            return DeliveryResult::fail('Commande introuvable pour créer l\'expédition First Delivery.');
        }

        $payload = $this->buildCreatePayload($order, $context);
        $response = $this->request($context, 'POST', '/create', $payload);

        if (!$response['success']) {
            return DeliveryResult::fail(
                $this->errorMessage($response, 'Échec création colis First Delivery.'),
                $response['body'],
                $response['status'],
                ['requestUrl' => $response['url'], 'requestMethod' => 'POST', 'requestBody' => $payload, 'durationMs' => $response['durationMs']],
            );
        }

        $body = is_array($response['body']) ? $response['body'] : [];
        $result = is_array($body['result'] ?? null) ? $body['result'] : [];
        $barCode = isset($result['barCode']) ? (string) $result['barCode'] : null;
        $labelUrl = isset($result['link']) ? (string) $result['link'] : null;

        if (null === $barCode || '' === $barCode) {
            return DeliveryResult::fail(
                $this->errorMessage($response, 'First Delivery n\'a pas renvoyé de code-barres.'),
                $response['body'],
                $response['status'],
                ['requestUrl' => $response['url'], 'requestMethod' => 'POST', 'requestBody' => $payload],
            );
        }

        return DeliveryResult::ok([
            'trackingNumber' => $barCode,
            'labelUrl' => $labelUrl,
            'status' => 'pending',
            'rawResponse' => $response['body'],
            'httpStatus' => $response['status'],
            'requestUrl' => $response['url'],
            'requestMethod' => 'POST',
            'requestBody' => $payload,
            'durationMs' => $response['durationMs'],
        ]);
    }

    public function cancelShipment(DeliveryConnectorContext $context, string $trackingNumber): DeliveryResult
    {
        $payload = ['barCodes' => [$trackingNumber]];
        $response = $this->request($context, 'POST', '/cancel-orders', $payload);

        if (!$response['success']) {
            return DeliveryResult::fail(
                $this->errorMessage($response, 'Échec annulation First Delivery.'),
                $response['body'],
                $response['status'],
                ['requestUrl' => $response['url'], 'requestMethod' => 'POST', 'requestBody' => $payload],
            );
        }

        return DeliveryResult::ok([
            'status' => 'cancelled',
            'rawResponse' => $response['body'],
            'httpStatus' => $response['status'],
            'requestUrl' => $response['url'],
            'requestMethod' => 'POST',
            'requestBody' => $payload,
            'durationMs' => $response['durationMs'],
        ]);
    }

    public function trackShipment(DeliveryConnectorContext $context, string $trackingNumber): DeliveryResult
    {
        $payload = ['barCode' => $trackingNumber];
        $response = $this->request($context, 'POST', '/etat', $payload);

        if (!$response['success']) {
            return DeliveryResult::fail(
                $this->errorMessage($response, 'Échec suivi First Delivery.'),
                $response['body'],
                $response['status'],
            );
        }

        $body = is_array($response['body']) ? $response['body'] : [];
        $result = is_array($body['result'] ?? null) ? $body['result'] : [];
        $state = isset($result['state']) ? (string) $result['state'] : null;

        return DeliveryResult::ok([
            'status' => $state,
            'rawResponse' => $response['body'],
            'httpStatus' => $response['status'],
            'requestUrl' => $response['url'],
            'requestMethod' => 'POST',
            'requestBody' => $payload,
            'durationMs' => $response['durationMs'],
        ]);
    }

    public function getLabel(DeliveryConnectorContext $context, string $trackingNumber): DeliveryResult
    {
        $baseUrl = rtrim($this->baseUrl($context), '/');

        return DeliveryResult::ok([
            'labelUrl' => $baseUrl.'/print?q='.rawurlencode($trackingNumber),
            'status' => 'label',
        ]);
    }

    public function calculateCost(DeliveryConnectorContext $context): DeliveryResult
    {
        return DeliveryResult::fail('Le calcul de coût n\'est pas exposé par l\'API First Delivery.');
    }

    public function getCities(DeliveryConnectorContext $context): DeliveryResult
    {
        $response = $this->request($context, 'GET', '/localities');

        if (!$response['success']) {
            return DeliveryResult::fail(
                $this->errorMessage($response, 'Échec récupération localités First Delivery.'),
                $response['body'],
                $response['status'],
            );
        }

        $body = is_array($response['body']) ? $response['body'] : [];
        $cities = $body['result'] ?? [];

        return DeliveryResult::ok([
            'cities' => is_array($cities) ? $cities : [],
            'rawResponse' => $response['body'],
            'httpStatus' => $response['status'],
            'requestUrl' => $response['url'],
            'durationMs' => $response['durationMs'],
        ]);
    }

    public function testConnection(DeliveryConnectorContext $context): DeliveryResult
    {
        return $this->getCities($context);
    }

    /** @return array<string, mixed> */
    private function buildCreatePayload(Order $order, DeliveryConnectorContext $context): array
    {
        $itemCount = max(1, $order->getItems()->count());
        $designation = sprintf('Commande %s', (string) $order->getId());
        $firstItem = $order->getItems()->first();
        if (false !== $firstItem) {
            $designation = $firstItem->getProductName() ?: $designation;
        }

        $localityId = $order->getShippingLocalityId();
        $client = [
            'nom' => $order->getCustomerName() ?? 'Client',
            'gouvernerat' => $order->getShippingGovernorate() ?? '',
            'ville' => $order->getShippingCity() ?? $order->getShippingLocality() ?? '',
            'adresse' => $order->getShippingAddress() ?? '',
            'telephone' => $order->getCustomerPhone() ?? '',
            'telephone2' => '',
        ];
        if (null !== $localityId && '' !== $localityId && ctype_digit($localityId)) {
            $client['locality_id'] = (int) $localityId;
        }

        $mapped = $context->mappedBody;
        if (isset($mapped['Client']) && is_array($mapped['Client'])) {
            $client = array_merge($client, $mapped['Client']);
        }

        $product = [
            'prix' => round($order->getTotalCents() / 100, 2),
            'designation' => $designation,
            'nombreArticle' => $itemCount,
            'commentaire' => '',
            'article' => $designation,
            'nombreEchange' => 0,
            'estFragile' => 'non',
            'ouvrirColis' => 'non',
        ];
        if (isset($mapped['Produit']) && is_array($mapped['Produit'])) {
            $product = array_merge($product, $mapped['Produit']);
        }

        return ['Client' => $client, 'Produit' => $product];
    }

    /**
     * @param array<string, mixed>|null $body
     *
     * @return array{success: bool, status: ?int, body: mixed, error: ?string, url: string, durationMs: ?int}
     */
    private function request(DeliveryConnectorContext $context, string $method, string $path, ?array $body = null): array
    {
        $token = $context->credentialValue('token') ?? '';
        if ('' === $token) {
            return ['success' => false, 'status' => null, 'body' => null, 'error' => 'Jeton First Delivery manquant.', 'url' => '', 'durationMs' => null];
        }

        $url = rtrim($this->baseUrl($context), '/').'/'.ltrim($path, '/');
        $options = [
            'headers' => [
                'Authorization' => 'Bearer '.$token,
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ],
            'timeout' => (float) ($context->company->getParametersConfig()['timeout'] ?? 20),
        ];
        if (null !== $body) {
            $options['json'] = $body;
        }

        try {
            $startedAt = microtime(true);
            $response = $this->client->request($method, $url, $options);
            $status = $response->getStatusCode();
            $content = $response->getContent(false);
            $parsed = json_decode($content, true);
            $durationMs = (int) round((microtime(true) - $startedAt) * 1000);
            $isError = is_array($parsed) && true === ($parsed['isError'] ?? false);

            if ($status >= 200 && $status < 300 && !$isError) {
                return ['success' => true, 'status' => $status, 'body' => $parsed ?? $content, 'error' => null, 'url' => $url, 'durationMs' => $durationMs];
            }

            return [
                'success' => false,
                'status' => $status,
                'body' => $parsed ?? $content,
                'error' => sprintf('Erreur HTTP %d', $status),
                'url' => $url,
                'durationMs' => $durationMs,
            ];
        } catch (\Throwable $e) {
            return ['success' => false, 'status' => null, 'body' => null, 'error' => $e->getMessage(), 'url' => $url, 'durationMs' => null];
        }
    }

    private function baseUrl(DeliveryConnectorContext $context): string
    {
        return $context->credentialValue('customBaseUrl') ?: $context->company->getBaseUrl();
    }

    /** @param array{body: mixed, error: ?string} $response */
    private function errorMessage(array $response, string $fallback): string
    {
        $body = $response['body'] ?? null;
        if (is_array($body) && isset($body['message']) && is_string($body['message']) && '' !== $body['message']) {
            return $body['message'];
        }

        return $response['error'] ?? $fallback;
    }
}
