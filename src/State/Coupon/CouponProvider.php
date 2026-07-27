<?php

namespace App\State\Coupon;

use App\Dto\Coupon\CouponOutput;
use App\Entity\Coupon;
use App\Repository\CouponRepository;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use ApiPlatform\State\Pagination\PaginatorInterface;
use App\State\Common\BoutiqueAwareProviderTrait;
use App\Repository\BoutiqueRepository;
use App\Security\BoutiqueContext;
use App\Service\Backoffice\BackofficeScopeResolver;
use App\State\Common\BackofficePaginator;
use Symfony\Component\HttpFoundation\Request;
use App\Entity\Boutique;

final class CouponProvider implements ProviderInterface
{
    use BoutiqueAwareProviderTrait;

    public function __construct(
        private CouponRepository $coupons,
        private BoutiqueRepository $boutiques,
        private BoutiqueContext $context,
        private BackofficeScopeResolver $scope,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): PaginatorInterface|CouponOutput|null
    {
        if ($operation instanceof GetCollection) {
            return $this->getCollection($operation, $uriVariables, $context);
        }

        $coupon = $this->coupons->find($uriVariables['id'] ?? null);
        $boutique = $this->resolveBoutiqueFromRequest($context, $uriVariables);
        if (!$coupon instanceof Coupon || !$boutique instanceof Boutique || (string) $coupon->getBoutique()->getId() !== (string) $boutique->getId()) {
            return null;
        }

        return $this->toOutput($coupon);
    }

    public function getCollection(Operation $operation, array $uriVariables = [], array $context = []): PaginatorInterface
    {
        $request = $context['request'] ?? null;
        $request = $request instanceof Request ? $request : null;
        $pagination = $this->scope->pagination($request);
        $boutique = $this->resolveBoutiqueFromRequest($context);
        if (!$boutique) {
            return new BackofficePaginator([], $pagination['page'], $pagination['itemsPerPage'], 0);
        }

        $result = $this->coupons->findForBackoffice(
            (string) $boutique->getId(),
            $pagination['page'],
            $pagination['itemsPerPage'],
        );

        return new BackofficePaginator(
            array_map($this->toOutput(...), $result['items']),
            $pagination['page'],
            $pagination['itemsPerPage'],
            $result['total'],
        );
    }

    private function toOutput(Coupon $coupon): CouponOutput
    {
        return new CouponOutput(
            id: (string) $coupon->getId(),
            code: $coupon->getCode(),
            name: $coupon->getName(),
            type: $coupon->getType()->value,
            scope: $coupon->getScope()->value,
            value: $coupon->getValue(),
            maxDiscountCents: $coupon->getMaxDiscountCents(),
            minCartAmountCents: $coupon->getMinCartAmountCents(),
            maxCartAmountCents: $coupon->getMaxCartAmountCents(),
            usageLimit: $coupon->getUsageLimit(),
            usedCount: $coupon->getUsedCount(),
            perUserLimit: $coupon->getPerUserLimit(),
            combineWithPromotions: $coupon->isCombineWithPromotions(),
            isActive: $coupon->isActive(),
            startsAt: $coupon->getStartsAt()?->format('c'),
            expiresAt: $coupon->getExpiresAt()?->format('c'),
            buyXGetYConfig: $coupon->getBuyXGetYConfig(),
            createdAt: $coupon->getCreatedAt()->format('c'),
            updatedAt: $coupon->getUpdatedAt()?->format('c'),
        );
    }
}
