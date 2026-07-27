<?php

namespace App\State\Catalog;

use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use ApiPlatform\State\Pagination\PaginatorInterface;
use App\Dto\Catalog\CategoryOutput;
use App\Entity\Category;
use App\Repository\BoutiqueRepository;
use App\Repository\CategoryRepository;
use App\Security\BoutiqueContext;
use App\Service\Backoffice\BackofficeScopeResolver;
use App\State\Common\BoutiqueAwareProviderTrait;
use App\State\Common\BackofficePaginator;
use Symfony\Component\HttpFoundation\Request;

/** @implements ProviderInterface<CategoryOutput> */
final readonly class CategoryProvider implements ProviderInterface
{
    use BoutiqueAwareProviderTrait;

    public function __construct(
        private CategoryRepository $categories,
        private BoutiqueRepository $boutiques,
        private BoutiqueContext $context,
        private BackofficeScopeResolver $scope,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): PaginatorInterface|CategoryOutput|null
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
                $category = $this->categories->findBySlugOrId((string) ($uriVariables['id'] ?? ''));

                return $category instanceof Category ? $this->toOutput($category) : null;
            }

            $result = $this->categories->findForBackoffice(
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
            $category = $this->categories->findBySlugOrId((string) ($uriVariables['id'] ?? ''), $boutique);

            return $category instanceof Category
                ? $this->toOutput($category)
                : null;
        }

        $result = $this->categories->findForBackoffice(
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

    private function toOutput(Category $category): CategoryOutput
    {
        $output = new CategoryOutput();
        $output->id = (string) $category->getId();
        $output->boutiqueId = (string) $category->getBoutique()->getId();
        $output->name = $category->getName();
        $output->slug = $category->getSlug();
        $output->parentId = null !== $category->getParent()?->getId() ? (string) $category->getParent()->getId() : null;
        $output->description = $category->getDescription();
        $output->image = $category->getImage();
        $output->banner = $category->getBanner();
        $output->isActive = $category->isActive();
        $output->isFeatured = $category->isFeatured();
        $output->showInHeader = $category->getShowInHeader();
        $output->showOnHomepage = $category->getShowOnHomepage();
        $output->homepageDisplayType = $category->getHomepageDisplayType()?->value;
        $output->homepagePosition = $category->getHomepagePosition();
        $output->menuPosition = $category->getMenuPosition();
        $output->showCategoryPage = $category->getShowCategoryPage();
        $output->productsLimit = $category->getProductsLimit();
        $output->metaTitle = $category->getMetaTitle();
        $output->metaDescription = $category->getMetaDescription();
        $output->metaKeywords = $category->getMetaKeywords();
        $output->ogTitle = $category->getOgTitle();
        $output->ogDescription = $category->getOgDescription();
        $output->ogImage = $category->getOgImage();
        $output->productsCount = $category->getProductsCount();
        $output->children = array_map(
            fn (Category $child) => [
                'id' => (string) $child->getId(),
                'name' => $child->getName(),
                'slug' => $child->getSlug(),
                'productsCount' => $child->getProductsCount(),
            ],
            $category->getChildren()->toArray(),
        );
        $output->createdAt = $category->getCreatedAt();
        $output->updatedAt = $category->getUpdatedAt();

        return $output;
    }
}
