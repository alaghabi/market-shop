<?php

namespace App\State\BoutiquePublicationRequest;

use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\Pagination\PaginatorInterface;
use ApiPlatform\State\ProviderInterface;
use App\Dto\BoutiquePublicationRequest\BoutiquePublicationRequestOutput;
use App\Entity\Boutique;
use App\Entity\BoutiquePublicationRequest;
use App\Repository\BoutiquePublicationRequestRepository;
use App\Repository\BoutiqueRepository;
use App\Security\BoutiqueContext;
use App\Service\Backoffice\BackofficeScopeResolver;
use App\State\Common\BackofficePaginator;
use App\State\Common\BoutiqueAwareProviderTrait;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/** @implements ProviderInterface<BoutiquePublicationRequestOutput> */
final class BoutiquePublicationRequestProvider implements ProviderInterface
{
    use BoutiqueAwareProviderTrait;

    public function __construct(
        private readonly BoutiquePublicationRequestRepository $repository,
        private readonly BoutiqueRepository $boutiques,
        private readonly BoutiqueContext $context,
        private readonly BackofficeScopeResolver $scope,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): PaginatorInterface|BoutiquePublicationRequestOutput|null
    {
        $request = $context['request'] ?? null;
        $request = $request instanceof Request ? $request : null;
        $pagination = $this->scope->pagination($request);
        $isAdminCollection = str_starts_with($operation->getUriTemplate() ?? '', '/admin/');

        if ($this->context->isSuperAdmin() && $isAdminCollection) {
            $boutique = null;
            $boutiqueId = $request?->query->get('boutiqueId');
            if (is_string($boutiqueId) && '' !== $boutiqueId) {
                $boutique = $this->resolveBoutiqueFromRequest($context);
            }

            if (isset($uriVariables['id'])) {
                $entity = $this->repository->find($uriVariables['id']);

                return $entity instanceof BoutiquePublicationRequest ? $this->toOutput($entity) : null;
            }

            $result = $this->repository->findForBackoffice($boutique, $pagination['page'], $pagination['itemsPerPage']);

            return new BackofficePaginator(array_map([$this, 'toOutput'], $result['items']), $pagination['page'], $pagination['itemsPerPage'], $result['total']);
        }

        $boutique = $this->resolveBoutiqueFromRequest($context);
        if (!$boutique instanceof Boutique) {
            if ($operation instanceof Get) {
                throw new NotFoundHttpException('Boutique not found');
            }

            return new BackofficePaginator([], $pagination['page'], $pagination['itemsPerPage'], 0);
        }

        if (!$this->context->canAccessBoutique($boutique)) {
            return $operation instanceof Get ? null : new BackofficePaginator([], $pagination['page'], $pagination['itemsPerPage'], 0);
        }

        if (isset($uriVariables['id'])) {
            $entity = $this->repository->find($uriVariables['id']);
            if (!$entity instanceof BoutiquePublicationRequest || $entity->getBoutique() !== $boutique) {
                return null;
            }

            return $this->toOutput($entity);
        }

        $result = $this->repository->findForBackoffice($boutique, $pagination['page'], $pagination['itemsPerPage']);

        return new BackofficePaginator(array_map([$this, 'toOutput'], $result['items']), $pagination['page'], $pagination['itemsPerPage'], $result['total']);
    }

    private function toOutput(BoutiquePublicationRequest $entity): BoutiquePublicationRequestOutput
    {
        $output = new BoutiquePublicationRequestOutput();
        $output->id = (string) $entity->getId();
        $output->boutiqueId = (string) $entity->getBoutique()->getId();
        $output->boutiqueName = $entity->getBoutique()->getName();
        $output->status = $entity->getStatus()->value;
        $output->requestedAt = $entity->getRequestedAt()->format('c');
        $output->handledAt = $entity->getHandledAt()?->format('c');
        $output->handledBy = $entity->getHandledBy();
        $output->reason = $entity->getReason();

        return $output;
    }
}
