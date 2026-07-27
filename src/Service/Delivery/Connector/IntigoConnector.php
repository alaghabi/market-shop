<?php

namespace App\Service\Delivery\Connector;

/**
 * Dedicated Intigo connector.
 *
 * Public API docs are not openly published; this connector is config-driven:
 * endpoints/mapping live on DeliveryCompany, while auth uses apiKey or
 * login/password → access token via the Auth endpoint when present.
 */
final class IntigoConnector implements DeliveryProviderInterface
{
    public function __construct(
        private readonly GenericHttpConnector $generic,
    ) {
    }

    public function supports(string $providerCode): bool
    {
        return 'intigo' === $providerCode;
    }

    public function createShipment(DeliveryConnectorContext $context): DeliveryResult
    {
        return $this->generic->createShipment($this->withAuthCredentials($context));
    }

    public function cancelShipment(DeliveryConnectorContext $context, string $trackingNumber): DeliveryResult
    {
        return $this->generic->cancelShipment($this->withAuthCredentials($context), $trackingNumber);
    }

    public function trackShipment(DeliveryConnectorContext $context, string $trackingNumber): DeliveryResult
    {
        return $this->generic->trackShipment($this->withAuthCredentials($context), $trackingNumber);
    }

    public function getLabel(DeliveryConnectorContext $context, string $trackingNumber): DeliveryResult
    {
        return $this->generic->getLabel($this->withAuthCredentials($context), $trackingNumber);
    }

    public function calculateCost(DeliveryConnectorContext $context): DeliveryResult
    {
        return $this->generic->calculateCost($this->withAuthCredentials($context));
    }

    public function getCities(DeliveryConnectorContext $context): DeliveryResult
    {
        return $this->generic->getCities($this->withAuthCredentials($context));
    }

    public function testConnection(DeliveryConnectorContext $context): DeliveryResult
    {
        $context = $this->withAuthCredentials($context);
        $apiKey = $context->credentialValue('apiKey') ?? $context->credentialValue('token');
        if (null === $apiKey || '' === $apiKey) {
            return DeliveryResult::fail('Clé API Intigo manquante.');
        }

        // Prefer a real authenticated probe over "no auth endpoint" success.
        $cities = $this->generic->getCities($context);
        if ($cities->success || null !== $cities->httpStatus) {
            return $cities;
        }

        return $this->generic->testConnection($context);
    }

    private function withAuthCredentials(DeliveryConnectorContext $context): DeliveryConnectorContext
    {
        $apiKey = $context->credentialValue('apiKey');
        $token = $context->credentialValue('token');
        $extras = [];

        if ((null === $token || '' === $token) && null !== $apiKey && '' !== $apiKey) {
            $extras['token'] = $apiKey;
            $extras['apiKey'] = $apiKey;
        }

        if ([] === $extras) {
            return $context;
        }

        return new DeliveryConnectorContext(
            company: $context->company,
            credential: $context->credential,
            decryptedCredentials: $context->decryptedCredentials + $extras,
            mappedBody: $context->mappedBody,
            order: $context->order,
            params: $context->params,
        );
    }
}
