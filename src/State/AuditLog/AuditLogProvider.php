<?php

namespace App\State\AuditLog;

use App\Dto\AuditLog\AuditLogOutput;
use App\Entity\AuditLog;
use App\Repository\AuditLogRepository;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use ApiPlatform\State\Pagination\PaginatorInterface;
use App\Service\Backoffice\BackofficeScopeResolver;
use App\State\Common\BackofficePaginator;
use Symfony\Component\HttpFoundation\Request;

final class AuditLogProvider implements ProviderInterface
{
    public function __construct(
        private AuditLogRepository $logs,
        private BackofficeScopeResolver $scope,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): PaginatorInterface|AuditLogOutput|null
    {
        if (!isset($uriVariables['id'])) {
            $request = $context['request'] ?? null;
            $request = $request instanceof Request ? $request : null;
            $pagination = $this->scope->pagination($request);
            $result = $this->logs->findForBackoffice(null, $pagination['page'], $pagination['itemsPerPage']);

            return new BackofficePaginator(
                array_map($this->toOutput(...), $result['items']),
                $pagination['page'],
                $pagination['itemsPerPage'],
                $result['total'],
            );
        }

        $log = $this->logs->find($uriVariables['id'] ?? null);
        if (!$log instanceof AuditLog) {
            return null;
        }

        return $this->toOutput($log);
    }

    private function toOutput(AuditLog $log): AuditLogOutput
    {
        return new AuditLogOutput(
            id: (string) $log->getId(),
            actorEmail: $log->getActorEmail(),
            actorRole: $log->getActorRole(),
            boutiqueId: $log->getBoutique() ? (string) $log->getBoutique()->getId() : null,
            action: $log->getAction(),
            resourceType: $log->getResourceType(),
            resourceId: $log->getResourceId(),
            details: $log->getDetails(),
            ipAddress: $log->getIpAddress(),
            createdAt: $log->getCreatedAt()->format('c'),
        );
    }
}
