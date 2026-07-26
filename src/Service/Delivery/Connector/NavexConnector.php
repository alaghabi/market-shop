<?php

namespace App\Service\Delivery\Connector;

use App\Entity\Order;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Live connector for Navex Delivery (Tunisia).
 *
 * Auth: HTTP Basic with boutique token. Create: form-urlencoded POST /api/v1/post.php
 * Docs: https://app.navex.tn/api/documentation.php
 */
final class NavexConnector implements DeliveryProviderInterface
{
    public function __construct(
        private readonly HttpClientInterface $client,
    ) {
    }

    public function supports(string $providerCode): bool
    {
        return 'navex' === $providerCode;
    }

    public function createShipment(DeliveryConnectorContext $context): DeliveryResult
    {
        $order = $context->order;
        if (!$order instanceof Order) {
            return DeliveryResult::fail('Commande introuvable pour créer l\'expédition Navex.');
        }

        $payload = $this->buildCreatePayload($order, $context);
        $response = $this->request($context, 'POST', '/api/v1/post.php', $payload, true);

        if (!$response['success']) {
            return DeliveryResult::fail(
                $this->errorMessage($response, 'Échec création colis Navex.'),
                $response['body'],
                $response['status'],
                ['requestUrl' => $response['url'], 'requestMethod' => 'POST', 'requestBody' => $payload, 'durationMs' => $response['durationMs']],
            );
        }

        $body = is_array($response['body']) ? $response['body'] : [];
        $tracking = $this->extractTracking($body);

        return DeliveryResult::ok([
            'trackingNumber' => $tracking,
            'status' => isset($body['status']) ? (string) $body['status'] : 'created',
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
        return DeliveryResult::fail('L\'annulation n\'est pas documentée dans l\'API Navex publique.');
    }

    public function trackShipment(DeliveryConnectorContext $context, string $trackingNumber): DeliveryResult
    {
        return DeliveryResult::fail('Le suivi API n\'est pas documenté dans l\'API Navex publique.');
    }

    public function getLabel(DeliveryConnectorContext $context, string $trackingNumber): DeliveryResult
    {
        return DeliveryResult::fail('La récupération d\'étiquette n\'est pas documentée dans l\'API Navex publique.');
    }

    public function calculateCost(DeliveryConnectorContext $context): DeliveryResult
    {
        return DeliveryResult::fail('Le calcul de coût n\'est pas exposé par l\'API Navex.');
    }

    public function getCities(DeliveryConnectorContext $context): DeliveryResult
    {
        return DeliveryResult::ok([
            'cities' => [
                'Ariana', 'Béja', 'Ben Arous', 'Bizerte', 'Gabès', 'Gafsa', 'Jendouba', 'Kairouan',
                'Kasserine', 'Kébili', 'La Manouba', 'Le Kef', 'Mahdia', 'Médenine', 'Monastir',
                'Nabeul', 'Sfax', 'Sidi Bouzid', 'Siliana', 'Sousse', 'Tataouine', 'Tozeur', 'Tunis', 'Zaghouan',
            ],
        ]);
    }

    public function testConnection(DeliveryConnectorContext $context): DeliveryResult
    {
        $token = $this->token($context);
        if ('' === $token) {
            return DeliveryResult::fail('Jeton Navex manquant.');
        }

        // Authenticated probe against the create endpoint with empty required fields
        // should return 400/401/403 rather than a network/DNS failure.
        $response = $this->request($context, 'POST', '/api/v1/post.php', ['nom' => ''], true);
        if (null === $response['status']) {
            return DeliveryResult::fail($response['error'] ?? 'Impossible de joindre l\'API Navex.');
        }

        if (in_array($response['status'], [401, 403], true)) {
            return DeliveryResult::fail('Authentification Navex refusée. Vérifiez le jeton.');
        }

        return DeliveryResult::ok([
            'status' => 'ok',
            'rawResponse' => $response['body'],
            'httpStatus' => $response['status'],
            'requestUrl' => $response['url'],
            'durationMs' => $response['durationMs'],
        ]);
    }

    /** @return array<string, string> */
    private function buildCreatePayload(Order $order, DeliveryConnectorContext $context): array
    {
        $itemCount = max(1, $order->getItems()->count());
        $designation = sprintf('Commande %s', (string) $order->getId());
        $boutique = $order->getBoutique();

        $payload = [
            'prix' => (string) round($order->getTotalCents() / 100, 2),
            'nom' => (string) ($order->getCustomerName() ?? 'Client'),
            'gouvernerat' => (string) ($order->getShippingGovernorate() ?? ''),
            'ville' => (string) ($order->getShippingCity() ?? $order->getShippingLocality() ?? ''),
            'adresse' => (string) ($order->getShippingAddress() ?? ''),
            'tel' => (string) ($order->getCustomerPhone() ?? ''),
            'tel2' => '',
            'designation' => $designation,
            'nb_article' => (string) $itemCount,
            'msg' => '',
            'echange' => '0',
            'article' => $designation,
            'nb_echange' => '0',
            'ouvrir' => 'Non',
            'sender_name' => $boutique->getName(),
            'sender_location' => (string) ($boutique->getAddress() ?? ''),
            'sender_gouvernorat' => '',
        ];

        foreach ($context->mappedBody as $key => $value) {
            if (is_scalar($value)) {
                $payload[(string) $key] = (string) $value;
            }
        }

        return $payload;
    }

    /**
     * @param array<string, string>|null $formBody
     *
     * @return array{success: bool, status: ?int, body: mixed, error: ?string, url: string, durationMs: ?int}
     */
    private function request(DeliveryConnectorContext $context, string $method, string $path, ?array $formBody = null, bool $asForm = false): array
    {
        $token = $this->token($context);
        if ('' === $token) {
            return ['success' => false, 'status' => null, 'body' => null, 'error' => 'Jeton Navex manquant.', 'url' => '', 'durationMs' => null];
        }

        $url = rtrim($this->baseUrl($context), '/').'/'.ltrim($path, '/');
        // Navex public docs use HTTP Basic; token is the username.
        $options = [
            'auth_basic' => [$token, ''],
            'headers' => ['Accept' => 'application/json'],
            'timeout' => (float) ($context->company->getParametersConfig()['timeout'] ?? 20),
        ];
        if (null !== $formBody) {
            if ($asForm) {
                $options['body'] = http_build_query($formBody);
                $options['headers']['Content-Type'] = 'application/x-www-form-urlencoded';
            } else {
                $options['json'] = $formBody;
            }
        }

        try {
            $startedAt = microtime(true);
            $response = $this->client->request($method, $url, $options);
            $status = $response->getStatusCode();
            $content = $response->getContent(false);
            $parsed = json_decode($content, true);
            $durationMs = (int) round((microtime(true) - $startedAt) * 1000);

            if ($status >= 200 && $status < 300) {
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

    private function token(DeliveryConnectorContext $context): string
    {
        return (string) ($context->credentialValue('token')
            ?? $context->credentialValue('apiKey')
            ?? $context->credentialValue('login')
            ?? '');
    }

    private function baseUrl(DeliveryConnectorContext $context): string
    {
        return $context->credentialValue('customBaseUrl') ?: $context->company->getBaseUrl();
    }

    /** @param array<string, mixed> $body */
    private function extractTracking(array $body): ?string
    {
        foreach (['barCode', 'barcode', 'tracking', 'tracking_number', 'colis', 'id'] as $key) {
            if (isset($body[$key]) && is_scalar($body[$key]) && '' !== (string) $body[$key]) {
                return (string) $body[$key];
            }
        }
        if (isset($body['colis']) && is_array($body['colis'])) {
            foreach (['barCode', 'barcode', 'id'] as $key) {
                if (isset($body['colis'][$key]) && is_scalar($body['colis'][$key])) {
                    return (string) $body['colis'][$key];
                }
            }
        }

        return null;
    }

    /** @param array{body: mixed, error: ?string} $response */
    private function errorMessage(array $response, string $fallback): string
    {
        $body = $response['body'] ?? null;
        if (is_array($body)) {
            foreach (['status_message', 'message', 'error'] as $key) {
                if (isset($body[$key]) && is_string($body[$key]) && '' !== $body[$key]) {
                    return $body[$key];
                }
            }
        }

        return $response['error'] ?? $fallback;
    }
}
