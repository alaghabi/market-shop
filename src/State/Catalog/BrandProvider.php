<?php

namespace App\State\Catalog;

use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use ApiPlatform\State\Pagination\PaginatorInterface;
use App\Dto\Catalog\BrandOutput;
use App\Entity\Brand;
use App\Repository\BoutiqueRepository;
use App\Repository\BrandRepository;
use App\Security\BoutiqueContext;
use App\Service\Backoffice\BackofficeScopeResolver;
use App\State\Common\BoutiqueAwareProviderTrait;
use App\State\Common\BackofficePaginator;
use Symfony\Component\HttpFoundation\Request;

/** @implements ProviderInterface<BrandOutput> */
final readonly class BrandProvider implements ProviderInterface
{
    use BoutiqueAwareProviderTrait;

    public function __construct(
        private BrandRepository $brands,
        private BoutiqueRepository $boutiques,
        private BoutiqueContext $context,
        private BackofficeScopeResolver $scope,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): PaginatorInterface|BrandOutput|null
    {
        $request = $context['request'] ?? null;
        $request = $request instanceof Request ? $request : null;
        $pagination = $this->scope->pagination($request);
        $boutique = $this->resolveBoutiqueFromRequest($context, $uriVariables);
        if (!$boutique) {
            if (!$this->context->isSuperAdmin()) {
                return $operation instanceof Get
                    ? null
                    : new BackofficePaginator([], $pagination['page'], $pagination['itemsPerPage'], 0);
            }

            if ($operation instanceof Get) {
                $brand = $this->brands->find((string) ($uriVariables['id'] ?? ''));

                return $brand instanceof Brand ? $this->toOutput($brand) : null;
            }

            $result = $this->brands->findForBackoffice(
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

        if ($operation instanceof Get) {
            $brand = $this->brands->find((string) ($uriVariables['id'] ?? ''));

            return $brand instanceof Brand && (string) $brand->getBoutique()->getId() === (string) $boutique->getId()
                ? $this->toOutput($brand)
                : null;
        }

        $result = $this->brands->findForBackoffice(
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

    private function toOutput(Brand $brand): BrandOutput
    {
        $output = new BrandOutput();
        $output->id = (string) $brand->getId();
        $output->boutiqueId = (string) $brand->getBoutique()->getId();
        $output->name = $brand->getName();
        $output->slug = $brand->getSlug();
        $output->logo = $brand->getLogo();
        $output->description = $brand->getDescription();
        $output->website = $brand->getWebsite();
        $output->isActive = $brand->isActive();
        $output->productsCount = $brand->getProductsCount();
        $output->createdAt = $brand->getCreatedAt();
        $output->updatedAt = $brand->getUpdatedAt();

        return $output;
    }
}
