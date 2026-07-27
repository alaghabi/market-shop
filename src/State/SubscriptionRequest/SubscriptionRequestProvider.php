<?php

namespace App\State\SubscriptionRequest;

use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use ApiPlatform\State\Pagination\PaginatorInterface;
use App\Dto\SubscriptionRequest\SubscriptionRequestOutput;
use App\Entity\Boutique;
use App\Entity\SubscriptionRequest;
use App\Repository\SubscriptionRequestRepository;
use App\Security\BoutiqueContext;
use App\Service\Backoffice\BackofficeScopeResolver;
use App\State\Common\BoutiqueAwareProviderTrait;
use App\State\Common\BackofficePaginator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/** @implements ProviderInterface<SubscriptionRequestOutput> */
final class SubscriptionRequestProvider implements ProviderInterface
{
    use BoutiqueAwareProviderTrait;

    public function __construct(
        private readonly SubscriptionRequestRepository $repository,
        private readonly BoutiqueContext $context,
        private readonly BackofficeScopeResolver $scope,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): PaginatorInterface|SubscriptionRequestOutput|null
    {
        $request = $context['request'] ?? null;
        $request = $request instanceof Request ? $request : null;
        $pagination = $this->scope->pagination($request);
        if ($this->context->isSuperAdmin() && str_starts_with($operation->getUriTemplate() ?? '', '/admin/')) {
            if (isset($uriVariables['id'])) {
                $entity = $this->repository->find($uriVariables['id']);

                return $entity ? $this->toOutput($entity) : null;
            }

            $result = $this->repository->findForBackoffice(
                null,
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

        $boutique = $this->resolveBoutiqueFromRequest($context);
        if (!$boutique instanceof Boutique) {
            if ($operation instanceof Get) {
                throw new NotFoundHttpException('Boutique not found');
            }

            return new BackofficePaginator([], $pagination['page'], $pagination['itemsPerPage'], 0);
        }

        if (!$this->context->canAccessBoutique($boutique)) {
            return $operation instanceof Get
                ? null
                : new BackofficePaginator([], $pagination['page'], $pagination['itemsPerPage'], 0);
        }

        if (isset($uriVariables['id'])) {
            $entity = $this->repository->find($uriVariables['id']);
            if (!$entity || (string) $entity->getBoutique()->getId() !== (string) $boutique->getId()) {
                return null;
            }

            return $this->toOutput($entity);
        }

        $result = $this->repository->findForBackoffice(
            $boutique,
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

    private function toOutput(SubscriptionRequest $entity): SubscriptionRequestOutput
    {
        $output = new SubscriptionRequestOutput();
        $output->id = (string) $entity->getId();
        $output->boutiqueId = (string) $entity->getBoutique()->getId();
        $output->boutiqueName = $entity->getBoutique()->getName();
        $output->subscriptionPlanId = (string) $entity->getSubscriptionPlan()->getId();
        $output->subscriptionPlanName = $entity->getSubscriptionPlan()->getName();
        $output->status = $entity->getStatus()->value;
        $output->requestedAt = $entity->getRequestedAt()->format('c');
        $output->approvedAt = $entity->getApprovedAt()?->format('c');
        $output->approvedBy = $entity->getApprovedBy();

        return $output;
    }
}
