<?php

namespace App\State\Storefront;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Dto\Storefront\MostOrderedProductOutput;
use App\Entity\Boutique;
use App\Entity\ProductImage;
use App\Repository\BoutiqueRepository;
use App\Repository\OrderRepository;
use App\Repository\ProductRepository;
use App\Security\BoutiqueContext;
use App\State\Common\BoutiqueAwareProviderTrait;

/** @implements ProviderInterface<MostOrderedProductOutput> */
final readonly class MostOrderedProductProvider implements ProviderInterface
{
    use BoutiqueAwareProviderTrait;

    public function __construct(
        private OrderRepository $orders,
        private BoutiqueRepository $boutiques,
        private ProductRepository $products,
        private BoutiqueContext $context,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): ?MostOrderedProductOutput
    {
        $boutique = $this->resolveBoutiqueFromRequest($context, $uriVariables);
        if (!$boutique instanceof Boutique || !$boutique->isVisiblePublicly()) {
            return null;
        }

        $output = new MostOrderedProductOutput();
        $output->boutiqueSlug = $boutique->getSlug();
        $periodEnd = new \DateTimeImmutable();
        $periodStart = $periodEnd->modify('-7 days');
        $result = $this->orders->findMostOrderedProductSince($boutique, $periodStart);
        if (null === $result) {
            return $output;
        }

        $product = $this->products->find($result['productId']);
        if (null === $product || (string) $product->getBoutique()->getId() !== (string) $boutique->getId()) {
            return $output;
        }
        $image = $product->getImages()->first();
        $output->id = (string) $product->getId();
        $output->name = $product->getName();
        $output->slug = $product->getSlug();
        $output->shortDescription = $product->getShortDescription();
        $output->priceCents = $product->getSellingPrice();
        $output->comparePriceCents = $product->getComparePrice();
        $output->currency = $product->getCurrency();
        $output->imageUrl = $image instanceof ProductImage
            ? ($image->getLargeUrl() ?: $image->getUrl())
            : null;
        $output->quantitySold = $result['quantitySold'];
        $output->periodStart = $periodStart->format(\DateTimeInterface::ATOM);
        $output->periodEnd = $periodEnd->format(\DateTimeInterface::ATOM);

        return $output;
    }
}
