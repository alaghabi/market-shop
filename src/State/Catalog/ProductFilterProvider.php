<?php

namespace App\State\Catalog;

use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use ApiPlatform\State\Pagination\PaginatorInterface;
use App\ApiResource\Catalog\ProductFilterResource;
use App\Entity\ProductFilter;
use App\Repository\BoutiqueRepository;
use App\Repository\ProductFilterRepository;
use App\Security\BoutiqueContext;
use App\Service\Backoffice\BackofficeScopeResolver;
use App\State\Common\BoutiqueAwareProviderTrait;
use App\State\Common\BackofficePaginator;
use Symfony\Component\HttpFoundation\Request;

/** @implements ProviderInterface<ProductFilterResource> */
final readonly class ProductFilterProvider implements ProviderInterface
{
    use BoutiqueAwareProviderTrait;

    public function __construct(
        private ProductFilterRepository $filters,
        private BoutiqueRepository $boutiques,
        private BoutiqueContext $context,
        private BackofficeScopeResolver $scope,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): PaginatorInterface|ProductFilterResource|null
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
                $filter = $this->filters->find($uriVariables['id'] ?? '');

                return $filter instanceof ProductFilter ? $this->toResource($filter) : null;
            }

            $result = $this->filters->findForBackoffice(
                null,
                false,
                $pagination['page'],
                $pagination['itemsPerPage'],
            );

            return new BackofficePaginator(
                array_map([$this, 'toResource'], $result['items']),
                $pagination['page'],
                $pagination['itemsPerPage'],
                $result['total'],
            );
        }

        if ($operation instanceof Get) {
            $filter = $this->filters->find($uriVariables['id'] ?? '');

            return $filter instanceof ProductFilter
                && (string) $filter->getBoutique()->getId() === (string) $boutique->getId()
                ? $this->toResource($filter)
                : null;
        }

        $result = $this->filters->findForBackoffice(
            (string) $boutique->getId(),
            true,
            $pagination['page'],
            $pagination['itemsPerPage'],
        );

        return new BackofficePaginator(
            array_map([$this, 'toResource'], $result['items']),
            $pagination['page'],
            $pagination['itemsPerPage'],
            $result['total'],
        );
    }

    public function toResource(ProductFilter $filter): ProductFilterResource
    {
        $r = new ProductFilterResource();
        $r->id = (string) $filter->getId();
        $r->boutiqueId = (string) $filter->getBoutique()->getId();
        $r->name = $filter->getName();
        $r->slug = $filter->getSlug();
        $r->type = $filter->getType();
        $r->position = $filter->getPosition();
        $r->active = $filter->isActive();
        $values = [];
        foreach ($filter->getValues() as $value) {
            $key = strtolower($value->getValue());
            if (!isset($values[$key])) {
                $values[$key] = ['id' => (string) $value->getId(), 'value' => $value->getValue()];
            }
        }
        $r->values = array_values($values);

        return $r;
    }
}
