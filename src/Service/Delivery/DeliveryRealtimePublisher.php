<?php

namespace App\Service\Delivery;

use App\Entity\Boutique;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;

final readonly class DeliveryRealtimePublisher
{
    public function __construct(
        private HubInterface $hub,
        private LoggerInterface $logger,
    ) {
    }

    /** @param array<string, mixed> $payload */
    public function publish(Boutique $boutique, array $payload): void
    {
        $payload['boutiqueId'] = (string) $boutique->getId();

        try {
            $data = json_encode($payload, JSON_THROW_ON_ERROR);
            foreach ([
                sprintf('backoffice/delivery/%s', (string) $boutique->getId()),
                'backoffice/delivery/platform',
            ] as $topic) {
                $this->hub->publish(new Update($topic, $data));
            }
        } catch (\Throwable $exception) {
            $this->logger->warning('Delivery realtime event publication failed.', [
                'exception' => $exception,
                'boutiqueId' => (string) $boutique->getId(),
            ]);
        }
    }
}
