<?php

namespace App\Controller\Rest;

use App\Entity\Boutique;
use App\Repository\BoutiqueRepository;
use App\Repository\ProductRepository;
use App\Repository\ProductViewDailyRepository;
use App\Security\BoutiqueContext;
use App\Service\Dashboard\DashboardCacheService;
use App\Service\Module\ModuleAccessService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

final readonly class ProductViewController
{
    private const VIEW_COOKIE_PREFIX = 'hanooti_product_view_';
    private const VIEW_COOKIE_TTL = 86400;

    public function __construct(
        private ProductRepository $products,
        private BoutiqueRepository $boutiques,
        private BoutiqueContext $context,
        private EntityManagerInterface $em,
        private ProductViewDailyRepository $dailyViews,
        private DashboardCacheService $dashboardCache,
        private ModuleAccessService $moduleAccess,
    ) {
    }

    #[Route('/api/products/{productId}/view', name: 'api_product_view', methods: ['POST'])]
    public function record(string $productId, Request $request): JsonResponse
    {
        $boutique = $request->attributes->get('_boutique');
        if (!$boutique instanceof Boutique) {
            $boutiqueId = $request->query->get('boutiqueId');
            $boutiqueSlug = $request->query->get('boutiqueSlug');
            if (is_string($boutiqueId) && '' !== $boutiqueId && $this->context->isSuperAdmin()) {
                $boutique = $this->boutiques->findBySlugOrId($boutiqueId);
            } elseif (is_string($boutiqueSlug) && '' !== $boutiqueSlug) {
                $boutique = $this->boutiques->findBySlug($boutiqueSlug);
            }
        }

        $product = $this->products->findBySlugOrId($productId, $boutique instanceof Boutique ? $boutique : null);
        if (!$product) {
            throw new NotFoundHttpException('Product not found');
        }

        if (!$this->moduleAccess->isModuleEnabled('analytics', $product->getBoutique())) {
            return new JsonResponse([
                'productId' => (string) $product->getId(),
                'viewsEnabled' => false,
                'viewsCount' => 0,
                'counted' => false,
            ]);
        }

        $cookieName = self::VIEW_COOKIE_PREFIX.(string) $product->getId();
        if ($request->cookies->has($cookieName)) {
            return new JsonResponse([
                'productId' => (string) $product->getId(),
                'viewsCount' => $product->getViewsCount(),
                'counted' => false,
            ]);
        }

        $this->em->wrapInTransaction(function () use ($product): void {
            $product->incrementViews();
            $this->dailyViews->incrementForProduct($product, new \DateTimeImmutable('today'));
            $this->em->flush();
        });
        $this->dashboardCache->clearPlatform();
        $this->dashboardCache->clearBoutique((string) $product->getBoutique()->getId());

        $response = new JsonResponse([
            'productId' => (string) $product->getId(),
            'viewsCount' => $product->getViewsCount(),
            'counted' => true,
        ]);
        $response->headers->setCookie(Cookie::create(
            $cookieName,
            '1',
            new \DateTimeImmutable(sprintf('+%d seconds', self::VIEW_COOKIE_TTL)),
            '/',
            null,
            $request->isSecure(),
            true,
            false,
            Cookie::SAMESITE_LAX,
        ));

        return $response;
    }
}
