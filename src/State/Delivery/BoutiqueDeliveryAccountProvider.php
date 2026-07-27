<?php

namespace App\State\Delivery;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use ApiPlatform\State\Pagination\PaginatorInterface;
use App\Dto\Delivery\BoutiqueDeliveryAccountOutput;
use App\Entity\Boutique;
use App\Repository\BoutiqueDeliveryAccountRepository;
use App\Security\BoutiqueContext;
use App\Service\Backoffice\BackofficeScopeResolver;
use App\State\Common\BackofficePaginator;
use App\State\Common\BoutiqueAwareProviderTrait;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class BoutiqueDeliveryAccountProvider implements ProviderInterface
{
    use BoutiqueAwareProviderTrait;

    public function __construct(
        private readonly BoutiqueDeliveryAccountRepository $repository,
        private readonly BoutiqueContext $context,
        private readonly BackofficeScopeResolver $scope,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): PaginatorInterface|BoutiqueDeliveryAccountOutput|null
    {
        $request = $context['request'] ?? null;
        $request = $request instanceof Request ? $request : null;
        $pagination = $this->scope->pagination($request);
        $boutique = $this->resolveBoutiqueFromRequest($context);
        if (!$boutique instanceof Boutique) {
            throw new NotFoundHttpException('Boutique not found');
        }

        if (!$this->context->canAccessBoutique($boutique)) {
            return isset($uriVariables['id'])
                ? null
                : new BackofficePaginator([], $pagination['page'], $pagination['itemsPerPage'], 0);
        }

        if (isset($uriVariables['id'])) {
            $entity = $this->repository->find($uriVariables['id']);
            if (!$entity || $entity->getBoutique()->getId() !== $boutique->getId()) {
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

    private function toOutput(object $entity): BoutiqueDeliveryAccountOutput
    {
        $output = new BoutiqueDeliveryAccountOutput();
        $output->id = (string) $entity->getId();
        $output->deliveryCompanyId = (string) $entity->getDeliveryCompany()->getId();
        $output->deliveryCompanyName = $entity->getDeliveryCompany()->getName();
        $output->isVerified = $entity->isVerified();
        $output->verifiedAt = $entity->getVerifiedAt()?->format('c');
        $output->lastError = $entity->getLastError();
        $output->isActive = $entity->isActive();
        $output->isDefault = $entity->isDefault();
        $output->hasApiKey = null !== $entity->getEncryptedApiKey();
        $output->hasToken = null !== $entity->getEncryptedToken();
        $output->hasSecret = null !== $entity->getEncryptedSecret();
        $output->customBaseUrl = $entity->getCustomBaseUrl();
        $output->createdAt = $entity->getCreatedAt()->format('c');

        return $output;
    }
}
