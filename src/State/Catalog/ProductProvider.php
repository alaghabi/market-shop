<?php

namespace App\State\Catalog;

use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use ApiPlatform\State\Pagination\PaginatorInterface;
use App\Dto\Catalog\ProductOutput;
use App\Entity\Product;
use App\Entity\ProductImage;
use App\Entity\ProductFilterValue;
use App\Repository\BoutiqueRepository;
use App\Repository\ProductRepository;
use App\Repository\ProductFavoriteRepository;
use App\Repository\ReviewRepository;
use App\Security\BoutiqueContext;
use App\Service\Backoffice\BackofficeScopeResolver;
use App\State\Common\BoutiqueAwareProviderTrait;
use App\State\Common\BackofficePaginator;
use Symfony\Component\HttpFoundation\Request;

/** @implements ProviderInterface<ProductOutput> */
final readonly class ProductProvider implements ProviderInterface
{
    use BoutiqueAwareProviderTrait;

    public function __construct(
        private ProductRepository $products,
        private BoutiqueRepository $boutiques,
        private ReviewRepository $reviews,
        private ProductFavoriteRepository $favorites,
        private BoutiqueContext $context,
        private BackofficeScopeResolver $scope,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): PaginatorInterface|ProductOutput|null
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
                $product = $this->products->findBySlugOrId($uriVariables['id'] ?? '');

                return $product instanceof Product ? $this->toOutput($product) : null;
            }

            $result = $this->products->findForBackoffice(
                null,
                $pagination['page'],
                $pagination['itemsPerPage'],
            );

            return new BackofficePaginator(
                array_map(
                    [$this, 'toOutput'],
                    $result['items'],
                ),
                $pagination['page'],
                $pagination['itemsPerPage'],
                $result['total'],
            );
        }

        if ($operation instanceof Get) {
            $product = $this->products->findBySlugOrId($uriVariables['id'] ?? '', $boutique);

            return $product instanceof Product
                ? $this->toOutput($product)
                : null;
        }

        $result = $this->products->findForBackoffice(
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

    private function toOutput(Product $product): ProductOutput
    {
        $stock = $product->getStock();
        $output = new ProductOutput();
        $output->id = (string) $product->getId();
        $output->boutiqueId = (string) $product->getBoutique()->getId();
        $output->name = $product->getName();
        $output->slug = $product->getSlug();
        $output->sku = $product->getSku();
        $output->barcode = $product->getBarcode();
        $output->shortDescription = $product->getShortDescription();
        $output->description = $product->getDescription();
        $output->status = $product->getStatus()->value;
        $output->costPrice = $product->getCostPrice();
        $output->sellingPrice = $product->getSellingPrice();
        $output->comparePrice = $product->getComparePrice();
        $output->taxRate = $product->getTaxRate();
        $output->weight = $product->getWeight();
        $output->length = $product->getLength();
        $output->width = $product->getWidth();
        $output->height = $product->getHeight();
        $output->manageStock = $product->getManageStock();
        $output->stockQuantity = $product->getStockQuantity();
        $output->lowStockThreshold = $product->getLowStockThreshold();
        $output->isFeatured = $product->isFeatured();
        $output->isBestSeller = $product->isBestSeller();
        $output->isNew = $product->isNew();
        $output->isVirtual = $product->isVirtual();
        $output->metaTitle = $product->getMetaTitle();
        $output->metaDescription = $product->getMetaDescription();
        $output->metaKeywords = $product->getMetaKeywords();
        $output->ogTitle = $product->getOgTitle();
        $output->ogDescription = $product->getOgDescription();
        $output->ogImage = $product->getOgImage();
        $output->publishedAt = $product->getPublishedAt()?->format('c');
        $output->brandId = $product->getBrand() ? (string) $product->getBrand()->getId() : null;
        $output->brandName = $product->getBrand()?->getName();
        $output->currency = $product->getCurrency();
        $output->categoryId = $product->getCategory() ? (string) $product->getCategory()?->getId() : null;
        $output->categoryName = $product->getCategory()?->getName();
        $output->categorySlug = $product->getCategory()?->getSlug();
        $output->categoryIds = array_map(
            fn ($pc) => (string) $pc->getCategory()->getId(),
            $product->getProductCategories()->toArray(),
        );
        $output->images = array_map(static fn (ProductImage $image): array => [
            'url' => $image->getUrl(),
            'smallUrl' => $image->getSmallUrl(),
            'largeUrl' => $image->getLargeUrl(),
            'alt' => $image->getAlt(),
            'isDefault' => 0 === $image->getPosition(),
        ], $product->getImages()->toArray());
        $output->media = array_map(fn ($medium) => [
            'type' => $medium->getType(),
            'filePath' => $medium->getFilePath(),
            'position' => $medium->getPosition(),
            'altText' => $medium->getAltText(),
            'isPrimary' => $medium->isPrimary(),
        ], $product->getMedia()->toArray());
        $output->variants = array_map(fn ($variant) => [
            'id' => (string) $variant->getId(),
            'sku' => $variant->getSku(),
            'sellingPrice' => $variant->getSellingPrice(),
            'comparePrice' => $variant->getComparePrice(),
            'quantity' => $variant->getQuantity(),
            'image' => $variant->getImage(),
            'isDefault' => $variant->isDefault(),
            'isActive' => $variant->isActive(),
            'attributes' => array_map(fn ($attr) => [
                'name' => $attr->getAttributeName(),
                'value' => $attr->getAttributeValue(),
            ], $variant->getAttributes()->toArray()),
        ], $product->getVariants()->toArray());
        $output->properties = array_map(fn ($prop) => [
            'name' => $prop->getName(),
            'value' => $prop->getValue(),
        ], $product->getProperties()->toArray());
        $output->filterValues = array_map(static fn (ProductFilterValue $fv): array => [
            'filterId' => (string) $fv->getFilter()->getId(),
            'filterName' => $fv->getFilter()->getName(),
            'filterSlug' => $fv->getFilter()->getSlug(),
            'value' => $fv->getValue(),
        ], $product->getFilterValues()->toArray());
        $output->createdAt = $product->getCreatedAt();
        $output->updatedAt = $product->getUpdatedAt();
        $output->viewsCount = $product->getViewsCount();
        $output->reviewsCount = $this->reviews->countByProduct($product);
        $output->rating = $this->reviews->getAverageRatingByProduct($product);
        $output->favoritesCount = $this->favorites->countByProduct($product);

        return $output;
    }
}
