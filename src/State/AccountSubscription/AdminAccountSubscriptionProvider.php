<?php

namespace App\State\AccountSubscription;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\Pagination\PaginatorInterface;
use ApiPlatform\State\ProviderInterface;
use App\Dto\AccountSubscription\AdminAccountSubscriptionOutput;
use App\Entity\AccountSubscription;
use App\Repository\AccountSubscriptionRepository;
use App\Service\Backoffice\BackofficeScopeResolver;
use App\State\Common\BackofficePaginator;
use Symfony\Component\HttpFoundation\Request;

/** @implements ProviderInterface<AdminAccountSubscriptionOutput> */
final readonly class AdminAccountSubscriptionProvider implements ProviderInterface
{
    public function __construct(
        private AccountSubscriptionRepository $subscriptions,
        private BackofficeScopeResolver $scope,
    ) {
    }

    /**
     * @return PaginatorInterface<AdminAccountSubscriptionOutput>
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): PaginatorInterface
    {
        $request = $context['request'] ?? null;
        $request = $request instanceof Request ? $request : null;
        $pagination = $this->scope->pagination($request);

        $result = $this->subscriptions->findAllPaginated(
            $pagination['page'],
            $pagination['itemsPerPage'],
        );

        return new BackofficePaginator(
            array_map([$this, 'toOutput'], $result['items']),
            $pagination['page'],
            $pagination['itemsPerPage'],
            $result['total'],
        );
    }

    private function toOutput(AccountSubscription $entity): AdminAccountSubscriptionOutput
    {
        $user = $entity->getUser();
        $plan = $entity->getSubscriptionPlan();
        $output = new AdminAccountSubscriptionOutput();
        $output->id = (string) $entity->getId();
        $output->userId = (string) $user->getId();
        $output->userEmail = $user->getUserIdentifier();
        $output->userDisplayName = $user->getDisplayName();
        $output->planId = null !== $plan ? (string) $plan->getId() : null;
        $output->planName = $plan?->getName();
        $output->status = $entity->getStatus()->value;
        $output->startDate = $entity->getStartDate()?->format('c');
        $output->endDate = $entity->getEndDate()?->format('c');
        $output->createdAt = $entity->getCreatedAt()->format('c');
        $output->extensions = [];
        foreach ($entity->getExtensions() as $grant) {
            if (!$grant->isActive()) {
                continue;
            }
            $extension = $grant->getExtension();
            $output->extensions[] = [
                'code' => $extension->getCode(),
                'name' => $extension->getName(),
                'expiresAt' => $grant->getExpiresAt()?->format('c'),
            ];
        }

        return $output;
    }
}
