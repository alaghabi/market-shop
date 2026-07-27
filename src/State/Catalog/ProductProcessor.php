<?php

namespace App\State\Catalog;

use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\Catalog\ProductInput;
use App\Dto\Catalog\ProductOutput;
use App\Entity\Brand;
use App\Entity\Category;
use App\Entity\Product;
use App\Entity\ProductCategory;
use App\Entity\ProductFilterValue;
use App\Entity\ProductImage;
use App\Entity\ProductMedia;
use App\Entity\ProductProperty;
use App\Entity\ProductStock;
use App\Entity\ProductVariant;
use App\Entity\ProductVariantAttribute;
use App\Enum\ProductStatus;
use App\Repository\BoutiqueRepository;
use App\Repository\BrandRepository;
use App\Repository\CategoryRepository;
use App\Repository\ProductFilterRepository;
use App\Repository\ProductRepository;
use App\Security\BoutiqueContext;
use App\Service\FrontOfficeCacheService;
use App\Service\SeoService;
use App\Service\Subscription\SubscriptionManager;
use App\State\Common\BoutiqueWriteResolverTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\String\Slugger\AsciiSlugger;

/** @implements ProcessorInterface<ProductInput, ProductOutput|null> */
final readonly class ProductProcessor implements ProcessorInterface
{
    use BoutiqueWriteResolverTrait;

    public function __construct(
        private BoutiqueRepository $boutiques,
        private ProductRepository $products,
        private CategoryRepository $categories,
        private BrandRepository $brands,
        private ProductFilterRepository $filters,
        private EntityManagerInterface $em,
        private BoutiqueContext $context,
        private ProductProvider $provider,
        private SeoService $seo,
        private FrontOfficeCacheService $cache,
        private SubscriptionManager $subscriptionManager,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ?ProductOutput
    {
        $boutique = $this->resolveBoutiqueForWrite($data, $uriVariables, $context);

        if ($operation instanceof Delete) {
            $product = $this->products->find((string) ($uriVariables['id'] ?? ''));
            if (!$product instanceof Product || (string) $product->getBoutique()->getId() !== (string) $boutique->getId()) {
                throw new NotFoundHttpException('Product not found');
            }

            if ($product instanceof Product) {
                $this->em->remove($product);
                $this->em->flush();
                $this->cache->invalidateSeo((string) $boutique->getId());
            }

            return null;
        }

        if (!$data instanceof ProductInput) {
            throw new \InvalidArgumentException('Expected ProductInput');
        }

        $category = $data->categoryId ? $this->categories->find($data->categoryId) : null;
        if ($category instanceof Category && (string) $category->getBoutique()->getId() !== (string) $boutique->getId()) {
            throw new AccessDeniedHttpException('Invalid category');
        }

        $brand = $data->brandId ? $this->brands->find($data->brandId) : null;
        if ($brand instanceof Brand && (string) $brand->getBoutique()->getId() !== (string) $boutique->getId()) {
            throw new AccessDeniedHttpException('Invalid brand');
        }

        $slug = $data->slug ?: $this->generateSlug($data->name);
        $slug = $this->resolveUniqueSlug($slug, $boutique->getId(), $uriVariables['id'] ?? null);
        $metaTitle = $data->metaTitle ?: $this->seo->defaultMetaTitle($data->name, $boutique->getName());
        $metaDescription = $data->metaDescription ?: $this->seo->defaultMetaDescription($data->shortDescription ?? $data->description, $data->name);
        $ogImage = $data->ogImage ?: $this->seo->defaultOgImage($data->defaultImageUrl ?: ($data->images[0] ?? null));

        $status = ProductStatus::tryFrom($data->status) ?? ProductStatus::Draft;
        $publishedAt = $data->publishedAt ? new \DateTimeImmutable($data->publishedAt) : null;

        $product = isset($uriVariables['id']) ? $this->products->find((string) $uriVariables['id']) : null;
        if (isset($uriVariables['id']) && !$product instanceof Product) {
            throw new NotFoundHttpException('Product not found');
        }

        if ($product instanceof Product && (string) $product->getBoutique()->getId() !== (string) $boutique->getId()) {
            throw new NotFoundHttpException('Product not found');
        }

        if (!$product instanceof Product) {
            if (ProductStatus::Active === $status && !$this->subscriptionManager->canActivateProduct($boutique)) {
                throw new BadRequestHttpException('Quota produits actifs atteint. Souscrivez à une extension ou changez de plan.');
            }

            $product = new Product(
                boutique: $boutique,
                name: $data->name,
                slug: $slug,
                sku: $data->sku,
                barcode: $data->barcode,
                shortDescription: $data->shortDescription,
                description: $data->description,
                status: $status,
                costPrice: $data->costPrice,
                sellingPrice: $data->sellingPrice,
                comparePrice: $data->comparePrice,
                taxRate: $data->taxRate,
                weight: $data->weight,
                length: $data->length,
                width: $data->width,
                height: $data->height,
                manageStock: $data->manageStock,
                stockQuantity: $data->stockQuantity,
                lowStockThreshold: $data->lowStockThreshold,
                isFeatured: $data->isFeatured,
                isBestSeller: $data->isBestSeller,
                isNew: $data->isNew,
                isVirtual: $data->isVirtual,
                metaTitle: $metaTitle,
                metaDescription: $metaDescription,
                metaKeywords: $data->metaKeywords,
                ogTitle: $data->ogTitle ?: $metaTitle,
                ogDescription: $data->ogDescription ?: $metaDescription,
                ogImage: $ogImage,
                publishedAt: $publishedAt,
                brand: $brand,
                currency: $data->currency,
                category: $category,
            );
            $this->em->persist($product);
        } else {
            if (ProductStatus::Active === $status && ProductStatus::Active !== $product->getStatus() && !$this->subscriptionManager->canActivateProduct($boutique)) {
                throw new BadRequestHttpException('Quota produits actifs atteint. Souscrivez à une extension ou changez de plan.');
            }
            $product->setName($data->name);
            $product->setSlug($slug);
            $product->setSku($data->sku);
            $product->setBarcode($data->barcode);
            $product->setShortDescription($data->shortDescription);
            $product->setDescription($data->description);
            $product->setStatus($status);
            $product->setCostPrice($data->costPrice);
            $product->setSellingPrice($data->sellingPrice);
            $product->setComparePrice($data->comparePrice);
            $product->setTaxRate($data->taxRate);
            $product->setWeight($data->weight);
            $product->setLength($data->length);
            $product->setWidth($data->width);
            $product->setHeight($data->height);
            $product->setManageStock($data->manageStock);
            $product->setStockQuantity($data->stockQuantity);
            $product->setLowStockThreshold($data->lowStockThreshold);
            $product->setIsFeatured($data->isFeatured);
            $product->setIsBestSeller($data->isBestSeller);
            $product->setIsNew($data->isNew);
            $product->setIsVirtual($data->isVirtual);
            $product->setMetaTitle($metaTitle);
            $product->setMetaDescription($metaDescription);
            $product->setMetaKeywords($data->metaKeywords);
            $product->setOgTitle($data->ogTitle ?: $metaTitle);
            $product->setOgDescription($data->ogDescription ?: $metaDescription);
            $product->setOgImage($ogImage);
            $product->setPublishedAt($publishedAt);
            $product->setBrand($brand);
            $product->setCurrency($data->currency);
            $product->setCategory($category);
        }

        $stock = $product->getStock() ?? new ProductStock($product);
        $stock->update($data->stockQuantity, $data->lowStockThreshold);
        $this->em->persist($stock);

        $this->syncFilterValues($product, $data, $boutique);
        $images = $this->orderedImages($data);
        $this->syncImages($product, $images);
        $this->syncMedia($product, $images);
        $this->syncVariants($product, $data);
        $this->syncProperties($product, $data);
        $this->syncCategories($product, $data);

        $this->em->flush();
        $this->cache->invalidateSeo((string) $boutique->getId());

        return $this->provider->provide(new Get(), ['boutiqueId' => (string) $boutique->getId(), 'id' => (string) $product->getId()]);
    }

    private function syncFilterValues(Product $product, ProductInput $data, \App\Entity\Boutique $boutique): void
    {
        $hadFilterValues = $product->getFilterValues()->count() > 0;
        foreach ($product->getFilterValues()->toArray() as $fv) {
            $product->removeFilterValue($fv);
            $this->em->remove($fv);
        }

        // Delete old rows before inserting replacement values protected by a unique index.
        if ($hadFilterValues) {
            $this->em->flush();
        }

        foreach ($data->filterValues as $filterId => $value) {
            $filter = $this->filters->find((string) $filterId);
            if (!$filter instanceof \App\Entity\ProductFilter) {
                continue;
            }
            if ((string) $filter->getBoutique()->getId() !== (string) $boutique->getId()) {
                continue;
            }

            $values = is_array($value) ? $value : [$value];
            foreach ($values as $filterValue) {
                $normalizedValue = trim((string) $filterValue);
                if ('' === $normalizedValue) {
                    continue;
                }
                $fv = new ProductFilterValue($filter, $product, $normalizedValue);
                $product->addFilterValue($fv);
                $this->em->persist($fv);
            }
        }
    }

    /** @param list<string> $images */
    private function syncImages(Product $product, array $images): void
    {
        foreach ($product->getImages()->toArray() as $image) {
            $this->em->remove($image);
        }

        foreach ($images as $i => $url) {
            $image = new ProductImage($product, $url, $i);
            $product->getImages()->add($image);
            $this->em->persist($image);
        }
    }

    /** @param list<string> $images */
    private function syncMedia(Product $product, array $images): void
    {
        foreach ($product->getMedia()->toArray() as $medium) {
            $this->em->remove($medium);
        }

        if ([] !== $images) {
            $primary = new ProductMedia($product, 'IMAGE', $images[0], 0, null, true);
            $product->addMedium($primary);
            $this->em->persist($primary);

            foreach (\array_slice($images, 1) as $i => $url) {
                $medium = new ProductMedia($product, 'IMAGE', $url, $i + 1);
                $product->addMedium($medium);
                $this->em->persist($medium);
            }
        }
    }

    /** @return list<string> */
    private function orderedImages(ProductInput $data): array
    {
        $images = array_values(array_filter(
            array_map(static fn (mixed $url): string => trim((string) $url), $data->images),
            static fn (string $url): bool => '' !== $url,
        ));
        if (null === $data->defaultImageUrl || '' === trim($data->defaultImageUrl)) {
            return $images;
        }

        $defaultIndex = array_search(trim($data->defaultImageUrl), $images, true);
        if (false === $defaultIndex || 0 === $defaultIndex) {
            return $images;
        }

        $default = $images[$defaultIndex];
        unset($images[$defaultIndex]);

        return array_values([$default, ...$images]);
    }

    private function syncVariants(Product $product, ProductInput $data): void
    {
        foreach ($product->getVariants()->toArray() as $variant) {
            $this->em->remove($variant);
        }

        foreach ($data->variants as $v) {
            $variant = new ProductVariant(
                product: $product,
                sku: $v['sku'] ?? null,
                barcode: $v['barcode'] ?? null,
                sellingPrice: $v['sellingPrice'] ?? 0,
                comparePrice: $v['comparePrice'] ?? 0,
                quantity: $v['quantity'] ?? 0,
                image: $v['image'] ?? null,
                isDefault: $v['isDefault'] ?? false,
                isActive: true,
            );
            $product->addVariant($variant);
            $this->em->persist($variant);

            foreach ($v['attributes'] ?? [] as $attr) {
                $attribute = new ProductVariantAttribute($variant, $attr['name'], $attr['value']);
                $variant->addAttribute($attribute);
                $this->em->persist($attribute);
            }
        }
    }

    private function syncProperties(Product $product, ProductInput $data): void
    {
        foreach ($product->getProperties()->toArray() as $prop) {
            $this->em->remove($prop);
        }

        foreach ($data->properties as $p) {
            $prop = new ProductProperty($product, $p['name'], $p['value']);
            $product->addProperty($prop);
            $this->em->persist($prop);
        }
    }

    private function syncCategories(Product $product, ProductInput $data): void
    {
        $hadCategories = $product->getProductCategories()->count() > 0;
        foreach ($product->getProductCategories()->toArray() as $pc) {
            $this->em->remove($pc);
        }

        if ($hadCategories) {
            $this->em->flush();
        }

        foreach ($data->categoryIds as $categoryId) {
            $cat = $this->categories->find($categoryId);
            if (!$cat instanceof Category) {
                continue;
            }
            $pc = new ProductCategory($product, $cat);
            $product->addProductCategory($pc);
            $this->em->persist($pc);
        }
    }

    private function generateSlug(string $name): string
    {
        return (new AsciiSlugger())->slug($name)->lower()->toString();
    }

    private function resolveUniqueSlug(string $slug, string $boutiqueId, ?string $productId): string
    {
        $existing = $this->products->findOneBy(['slug' => $slug, 'boutique' => $boutiqueId]);
        if (!$existing instanceof Product || (null !== $productId && (string) $existing->getId() === $productId)) {
            return $slug;
        }
        $suffix = 2;
        while ($this->products->findOneBy(['slug' => $slug.'-'.$suffix, 'boutique' => $boutiqueId])) {
            ++$suffix;
        }

        return $slug.'-'.$suffix;
    }
}
