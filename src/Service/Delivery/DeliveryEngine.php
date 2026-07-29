<?php

namespace App\Service\Delivery;

use App\Entity\Boutique;
use App\Entity\BoutiqueDeliveryAccount;
use App\Entity\DeliveryApiLog;
use App\Entity\DeliveryCompany;
use App\Entity\Order;
use App\Entity\Shipment;
use App\Enum\DeliveryEndpointType;
use App\Enum\ShipmentStatus;
use App\Factory\RedisFactory;
use App\Repository\BoutiqueDeliveryAccountRepository;
use App\Repository\ShipmentRepository;
use App\Service\Delivery\Connector\DeliveryConnectorContext;
use App\Service\Delivery\Connector\DeliveryConnectorRegistry;
use App\Service\Delivery\Connector\DeliveryResult;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Delivery Engine: the single orchestration point described by the module
 * specification (Commande -> Delivery Engine -> choix société -> config ->
 * credentials -> mapping -> body -> connector -> API -> mise a jour).
 */
final class DeliveryEngine
{
    public function __construct(
        private readonly EncryptionService $encryption,
        private readonly DeliveryMappingEngine $mapping,
        private readonly DeliveryVariableRegistry $variables,
        private readonly DeliveryConnectorRegistry $connectors,
        private readonly BoutiqueDeliveryAccountRepository $accounts,
        private readonly ShipmentRepository $shipments,
        private readonly EntityManagerInterface $em,
        private readonly RedisFactory $redisFactory,
    ) {
    }

    public function createShipmentForOrder(Order $order, ?BoutiqueDeliveryAccount $account = null): DeliveryResult
    {
        $boutique = $order->getBoutique();

        if ($order->isSubmittedToDelivery()) {
            return DeliveryResult::fail('Cette commande a déjà été envoyée à un transporteur.');
        }

        $account ??= $this->resolveDefaultAccount($boutique);

        if (null === $account) {
            $error = 'Aucun compte livraison actif et vérifié pour cette boutique.';
            $order->markDeliveryError($error);
            $this->em->flush();

            return DeliveryResult::fail($error);
        }

        $lockKey = 'delivery:lock:order:'.$order->getId();
        if (!$this->acquireLock($lockKey)) {
            return DeliveryResult::fail('Une expédition est déjà en cours de traitement pour cette commande.');
        }

        try {
            $company = $account->getDeliveryCompany();
            if (!$account->isActive()) {
                $error = 'Le compte livraison de cette boutique est désactivé.';
                $order->markDeliveryError($error);
                $this->em->flush();

                return DeliveryResult::fail($error);
            }

            $authentication = $this->testConnection($account);
            if (!$authentication->success) {
                $error = $authentication->errorMessage ?? 'Authentification transporteur refusée.';
                $order->markDeliveryError($error);
                $this->em->flush();

                return $authentication;
            }

            $context = $this->buildContext($account, $order);
            if (null !== $authentication->accessToken) {
                $context = $context->withCredentialValue('accessToken', $authentication->accessToken);
            }

            if ([] === ($company->getMappingConfig() ?? [])) {
                $error = 'Aucun mapping configuré pour ce transporteur.';
                $order->markDeliveryError($error);
                $this->em->flush();

                return DeliveryResult::fail($error);
            }

            $connector = $this->connectors->resolve($company->getProvider());
            $result = $connector->createShipment($context);

            $shipment = new Shipment(
                boutique: $boutique,
                order: $order,
                deliveryCompany: $company,
                credential: $account,
                status: $result->success ? ShipmentStatus::Sent : ShipmentStatus::Failed,
                trackingNumber: $result->trackingNumber,
                labelUrl: $result->labelUrl,
                requestPayload: $context->mappedBody,
                responsePayload: $this->normalizeForStorage($result->rawResponse),
                errorMessage: $result->errorMessage,
            );
            if ($result->success) {
                $shipment->markSent();
            }
            $this->em->persist($shipment);

            $this->logCall($company, $boutique, DeliveryEndpointType::CreateShipment, $result);

            if ($result->success) {
                $order->markAsShipped($result->trackingNumber ?? '');
                $order->markDeliverySubmitted();
                $order->setDeliveryAccount($account);
            } else {
                $order->markDeliveryError($result->errorMessage ?? 'Erreur inconnue');
            }

            $this->em->flush();

            return $result;
        } finally {
            $this->releaseLock($lockKey);
        }
    }

    public function cancelShipment(Shipment $shipment): DeliveryResult
    {
        if (null === $shipment->getTrackingNumber()) {
            return DeliveryResult::fail('Aucun numéro de suivi disponible pour annuler.');
        }

        $account = $shipment->getCredential();
        if (null === $account) {
            return DeliveryResult::fail('Compte livraison introuvable pour cette expédition.');
        }

        $context = $this->buildContext($account, $shipment->getOrder());
        $connector = $this->connectors->resolve($shipment->getDeliveryCompany()->getProvider());
        $result = $connector->cancelShipment($context, $shipment->getTrackingNumber());

        $this->logCall($shipment->getDeliveryCompany(), $shipment->getBoutique(), DeliveryEndpointType::CancelShipment, $result);

        if ($result->success) {
            $shipment->setStatus(ShipmentStatus::Cancelled);
        } else {
            $shipment->setErrorMessage($result->errorMessage);
        }
        $this->em->flush();

        return $result;
    }

    public function trackShipment(Shipment $shipment): DeliveryResult
    {
        if (null === $shipment->getTrackingNumber()) {
            return DeliveryResult::fail('Aucun numéro de suivi disponible.');
        }

        $account = $shipment->getCredential();
        if (null === $account) {
            return DeliveryResult::fail('Compte livraison introuvable pour cette expédition.');
        }

        $context = $this->buildContext($account, $shipment->getOrder());
        $connector = $this->connectors->resolve($shipment->getDeliveryCompany()->getProvider());
        $result = $connector->trackShipment($context, $shipment->getTrackingNumber());

        $this->logCall($shipment->getDeliveryCompany(), $shipment->getBoutique(), DeliveryEndpointType::TrackShipment, $result);

        if ($result->success) {
            $newStatus = $this->mapCarrierStatus($result->status);
            $shipment->setStatus($newStatus);
            $shipment->setResponsePayload($this->normalizeForStorage($result->rawResponse));

            $order = $shipment->getOrder();
            if (ShipmentStatus::Delivered === $newStatus) {
                $order->markAsDelivered();
            }
        } else {
            $shipment->setErrorMessage($result->errorMessage);
        }

        $this->em->flush();

        return $result;
    }

    public function getLabel(Shipment $shipment): DeliveryResult
    {
        $account = $shipment->getCredential();
        if (null === $account || null === $shipment->getTrackingNumber()) {
            return DeliveryResult::fail('Compte livraison ou numéro de suivi manquant.');
        }

        $context = $this->buildContext($account, $shipment->getOrder());
        $connector = $this->connectors->resolve($shipment->getDeliveryCompany()->getProvider());
        $result = $connector->getLabel($context, $shipment->getTrackingNumber());

        $this->logCall($shipment->getDeliveryCompany(), $shipment->getBoutique(), DeliveryEndpointType::GetLabel, $result);

        if ($result->success && null !== $result->labelUrl) {
            $shipment->setLabelUrl($result->labelUrl);
            $this->em->flush();
        }

        return $result;
    }

    public function calculateCost(Boutique $boutique, DeliveryCompany $company, array $params): DeliveryResult
    {
        $account = $this->accounts->findOneByBoutiqueAndCompany($boutique, $company);
        $context = new DeliveryConnectorContext(
            company: $company,
            credential: $account,
            decryptedCredentials: $account ? $this->decryptCredentials($account) : [],
            params: $params,
        );

        $connector = $this->connectors->resolve($company->getProvider());
        $result = $connector->calculateCost($context);
        $this->logCall($company, $boutique, DeliveryEndpointType::CalculateCost, $result);

        return $result;
    }

    public function getCities(Boutique $boutique, DeliveryCompany $company): DeliveryResult
    {
        $account = $this->accounts->findOneByBoutiqueAndCompany($boutique, $company);
        $context = new DeliveryConnectorContext(
            company: $company,
            credential: $account,
            decryptedCredentials: $account ? $this->decryptCredentials($account) : [],
        );

        $connector = $this->connectors->resolve($company->getProvider());
        $result = $connector->getCities($context);
        $this->logCall($company, $boutique, DeliveryEndpointType::GetCities, $result);

        return $result;
    }

    public function testConnection(BoutiqueDeliveryAccount $account): DeliveryResult
    {
        $context = $this->buildContext($account, null);
        $connector = $this->connectors->resolve($account->getDeliveryCompany()->getProvider());
        $result = $connector->testConnection($context);

        $this->logCall($account->getDeliveryCompany(), $account->getBoutique(), null, $result);

        return $result;
    }

    /**
     * Builds a create-shipment request body for preview/testing purposes,
     * without calling any carrier API.
     *
     * @return array<string, mixed>
     */
    public function previewMapping(DeliveryCompany $company, ?Boutique $boutique = null): array
    {
        $sample = $this->variables->sampleContext($boutique);

        return $this->mapping->resolveMapping($company->getMappingConfig(), $sample);
    }

    private function buildContext(BoutiqueDeliveryAccount $account, ?Order $order): DeliveryConnectorContext
    {
        $company = $account->getDeliveryCompany();
        $mappedBody = null !== $order
            ? $this->mapping->resolveMapping($company->getMappingConfig(), $this->variables->resolveContext($order))
            : [];

        return new DeliveryConnectorContext(
            company: $company,
            credential: $account,
            decryptedCredentials: $this->decryptCredentials($account),
            mappedBody: $mappedBody,
            order: $order,
        );
    }

    /** @return array<string, string|null> */
    private function decryptCredentials(BoutiqueDeliveryAccount $account): array
    {
        $decrypt = function (?string $value): ?string {
            if (null === $value || '' === $value) {
                return null;
            }
            try {
                return $this->encryption->decrypt($value);
            } catch (\RuntimeException) {
                return null;
            }
        };

        return [
            'login' => $decrypt($account->getEncryptedLogin()),
            'password' => $decrypt($account->getEncryptedPassword()),
            'apiKey' => $decrypt($account->getEncryptedApiKey()),
            'token' => $decrypt($account->getEncryptedToken()),
            'secret' => $decrypt($account->getEncryptedSecret()),
            'customBaseUrl' => $account->getCustomBaseUrl(),
        ];
    }

    private function resolveDefaultAccount(Boutique $boutique): ?BoutiqueDeliveryAccount
    {
        $default = $this->accounts->findDefaultForBoutique($boutique);
        if (null !== $default) {
            return $default;
        }

        foreach ($boutique->getDeliveryAccounts() as $account) {
            if ($account->isActive() && $account->isVerified()) {
                return $account;
            }
        }

        return null;
    }

    private function mapCarrierStatus(?string $raw): ShipmentStatus
    {
        $normalized = strtolower((string) $raw);

        return match (true) {
            str_contains($normalized, 'deliver') => ShipmentStatus::Delivered,
            str_contains($normalized, 'transit') || str_contains($normalized, 'route') => ShipmentStatus::InTransit,
            str_contains($normalized, 'prepar') => ShipmentStatus::InPreparation,
            str_contains($normalized, 'accept') || str_contains($normalized, 'pickup') || str_contains($normalized, 'pick_up') => ShipmentStatus::Accepted,
            str_contains($normalized, 'cancel') => ShipmentStatus::Cancelled,
            str_contains($normalized, 'return') => ShipmentStatus::Return,
            str_contains($normalized, 'fail') || str_contains($normalized, 'error') => ShipmentStatus::Failed,
            default => ShipmentStatus::Sent,
        };
    }

    private function logCall(DeliveryCompany $company, ?Boutique $boutique, ?DeliveryEndpointType $type, DeliveryResult $result): void
    {
        $log = new DeliveryApiLog(
            deliveryCompany: $company,
            boutique: $boutique,
            endpointType: $type,
            requestMethod: $result->requestMethod ?? 'POST',
            requestUrl: $result->requestUrl ?? $company->getBaseUrl(),
            requestBody: $this->redactSecrets($this->normalizeForStorage($result->requestBody)),
            responseStatus: $result->httpStatus,
            responseBody: $this->redactSecrets($this->normalizeForStorage($result->rawResponse)),
            success: $result->success,
            errorMessage: $result->errorMessage,
            durationMs: $result->durationMs,
        );
        $this->em->persist($log);
    }

    /** @param array<string, mixed>|null $payload */
    private function redactSecrets(?array $payload): ?array
    {
        if (null === $payload) {
            return null;
        }

        $sensitiveKeys = ['password', 'apiKey', 'api_key', 'token', 'secret', 'authorization', 'signature'];

        $redact = function (mixed $value) use (&$redact, $sensitiveKeys): mixed {
            if (!is_array($value)) {
                return $value;
            }

            $result = [];
            foreach ($value as $key => $item) {
                if (is_string($key) && in_array(strtolower($key), $sensitiveKeys, true)) {
                    $result[$key] = '***REDACTED***';
                    continue;
                }
                $result[$key] = is_array($item) ? $redact($item) : $item;
            }

            return $result;
        };

        return $redact($payload);
    }

    /** @return array<string, mixed>|null */
    private function normalizeForStorage(mixed $value): ?array
    {
        if (null === $value) {
            return null;
        }
        if (is_array($value)) {
            return $value;
        }

        return ['value' => is_scalar($value) ? $value : (string) json_encode($value)];
    }

    private function acquireLock(string $key, int $ttl = 30): bool
    {
        $redis = $this->redisFactory->create();
        if (!$redis) {
            return true;
        }

        return (bool) $redis->set($key, '1', ['nx', 'ex' => $ttl]);
    }

    private function releaseLock(string $key): void
    {
        $redis = $this->redisFactory->create();
        $redis?->del($key);
    }
}
