<?php

namespace App\State\Subscription;

use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use ApiPlatform\State\Pagination\PaginatorInterface;
use App\Dto\Subscription\SubscriptionOutput;
use App\Entity\Boutique;
use App\Entity\Subscription;
use App\Repository\BoutiqueRepository;
use App\Repository\SubscriptionRepository;
use App\Security\BoutiqueContext;
use App\Service\Backoffice\BackofficeScopeResolver;
use App\State\Common\BoutiqueAwareProviderTrait;
use App\State\Common\BackofficePaginator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/** @implements ProviderInterface<SubscriptionOutput> */
final class SubscriptionProvider implements ProviderInterface
{
    use BoutiqueAwareProviderTrait;

    public function __construct(
        private readonly SubscriptionRepository $repository,
        private readonly BoutiqueRepository $boutiques,
        private readonly BoutiqueContext $context,
        private readonly BackofficeScopeResolver $scope,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): PaginatorInterface|SubscriptionOutput|null
    {
        $request = $context['request'] ?? null;
        $request = $request instanceof Request ? $request : null;
        $pagination = $this->scope->pagination($request);
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

        $result = $this->repository->findByBoutiquePaginated(
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

    private function toOutput(Subscription $entity): SubscriptionOutput
    {
        $output = new SubscriptionOutput();
        $output->id = (string) $entity->getId();
        $output->boutiqueId = (string) $entity->getBoutique()->getId();
        $output->boutiqueName = $entity->getBoutique()->getName();
        $output->plan = $entity->getPlan();
        $output->status = $entity->getStatus();
        $output->startDate = $entity->getStartDate()?->format('c');
        $output->endDate = $entity->getEndDate()?->format('c');
        $output->acceptedBy = $entity->getAcceptedBy();
        $output->acceptedAt = $entity->getAcceptedAt()?->format('c');
        $output->createdAt = $entity->getCreatedAt()->format('c');
        $output->priceCents = $entity->getPlan()->priceCents();

        return $output;
    }
}
