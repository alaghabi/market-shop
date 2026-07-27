<?php

namespace App\State\Catalog;

use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\Catalog\ProductFilterResource;
use App\Entity\ProductFilter;
use App\Entity\ProductFilterValue;
use App\Repository\BoutiqueRepository;
use App\Repository\ProductFilterRepository;
use App\Security\BoutiqueContext;
use App\State\Common\BoutiqueWriteResolverTrait;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/** @implements ProcessorInterface<ProductFilterResource> */
final readonly class ProductFilterProcessor implements ProcessorInterface
{
    use BoutiqueWriteResolverTrait;

    public function __construct(
        private BoutiqueRepository $boutiques,
        private ProductFilterRepository $filters,
        private EntityManagerInterface $em,
        private BoutiqueContext $context,
        private ProductFilterProvider $provider,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ?ProductFilterResource
    {
        $boutique = $this->resolveBoutiqueForWrite($data, $uriVariables, $context);
        $boutiqueId = (string) $boutique->getId();

        if ($operation instanceof Delete) {
            $filter = $this->filters->find($uriVariables['id'] ?? '');
            if (!$filter instanceof ProductFilter || (string) $filter->getBoutique()->getId() !== $boutiqueId) {
                throw new NotFoundHttpException('Product filter not found');
            }

            $this->em->remove($filter);
            $this->em->flush();

            return null;
        }

        $filterId = $uriVariables['id'] ?? null;
        $filter = $filterId ? $this->filters->find($filterId) : null;

        if ($filterId && (!$filter instanceof ProductFilter || (string) $filter->getBoutique()->getId() !== $boutiqueId)) {
            throw new NotFoundHttpException('Product filter not found');
        }

        if (!$filter instanceof ProductFilter) {
            $slug = $data->slug ?: trim(preg_replace('/[^a-z0-9-]+/', '-', strtolower($data->name)), '-');
            if ($this->filters->findOneBy(['boutique' => $boutique, 'slug' => $slug])) {
                throw new ConflictHttpException(sprintf('Un filtre avec le nom "%s" existe déjà pour cette boutique.', $data->name));
            }

            $filter = new ProductFilter($boutique, $data->name, $slug, $data->type);
            $this->em->persist($filter);
        }

        $filter->setName($data->name);
        $filter->setType($data->type);
        $filter->setPosition($data->position);
        $filter->setActive($data->active);
        $this->syncFilterOptions($filter, $data->values);

        try {
            $this->em->flush();
        } catch (UniqueConstraintViolationException $exception) {
            throw new ConflictHttpException(sprintf('Un filtre avec le nom "%s" existe déjà pour cette boutique.', $data->name), $exception);
        }

        return $this->provider->toResource($filter);
    }

    /** @param array<int, mixed> $values */
    private function syncFilterOptions(ProductFilter $filter, array $values): void
    {
        $options = [];
        foreach ($values as $value) {
            $rawValue = is_array($value) ? ($value['value'] ?? null) : $value;
            if (!is_string($rawValue)) {
                continue;
            }

            $normalized = trim($rawValue);
            $key = strtolower($normalized);
            if ('' !== $normalized && !isset($options[$key])) {
                $options[$key] = $normalized;
            }
        }

        foreach ($filter->getValues()->toArray() as $existing) {
            $key = strtolower(trim($existing->getValue()));
            if (null === $existing->getProduct() || !isset($options[$key])) {
                $filter->removeValue($existing);
                $this->em->remove($existing);
            }
        }

        foreach ($options as $value) {
            $option = new ProductFilterValue($filter, null, $value);
            $filter->addValue($option);
            $this->em->persist($option);
        }
    }
}
