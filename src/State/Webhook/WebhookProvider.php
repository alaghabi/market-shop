<?php

namespace App\State\Webhook;

use App\Dto\Webhook\WebhookOutput;
use App\Entity\Webhook;
use App\Repository\WebhookRepository;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use ApiPlatform\State\Pagination\PaginatorInterface;
use App\Service\Backoffice\BackofficeScopeResolver;
use App\State\Common\BackofficePaginator;
use Symfony\Component\HttpFoundation\Request;

final class WebhookProvider implements ProviderInterface
{
    public function __construct(
        private WebhookRepository $webhooks,
        private BackofficeScopeResolver $scope,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): PaginatorInterface|WebhookOutput|null
    {
        if (!isset($uriVariables['id'])) {
            $request = $context['request'] ?? null;
            $request = $request instanceof Request ? $request : null;
            $pagination = $this->scope->pagination($request);
            $result = $this->webhooks->findForBackoffice($pagination['page'], $pagination['itemsPerPage']);

            return new BackofficePaginator(
                array_map($this->toOutput(...), $result['items']),
                $pagination['page'],
                $pagination['itemsPerPage'],
                $result['total'],
            );
        }

        $webhook = $this->webhooks->find($uriVariables['id'] ?? null);
        if (!$webhook instanceof Webhook) {
            return null;
        }

        return $this->toOutput($webhook);
    }

    private function toOutput(Webhook $webhook): WebhookOutput
    {
        return new WebhookOutput(
            id: (string) $webhook->getId(),
            boutiqueId: $webhook->getBoutique() ? (string) $webhook->getBoutique()->getId() : null,
            url: $webhook->getUrl(),
            events: $webhook->getEvents(),
            secret: $webhook->getSecret() ? '***' : null,
            status: $webhook->getStatus(),
            lastTriggeredAt: $webhook->getLastTriggeredAt()?->format('c'),
            failureCount: $webhook->getFailureCount(),
            createdAt: $webhook->getCreatedAt()->format('c'),
            updatedAt: $webhook->getUpdatedAt()?->format('c'),
        );
    }
}
