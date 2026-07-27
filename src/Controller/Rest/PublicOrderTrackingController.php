<?php

namespace App\Controller\Rest;

use App\Entity\Boutique;
use App\Repository\BoutiqueRepository;
use App\Repository\OrderRepository;
use App\Service\Boutique\ShopContext;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final readonly class PublicOrderTrackingController
{
    public function __construct(
        private OrderRepository $orders,
        private BoutiqueRepository $boutiques,
        private ShopContext $shopContext,
    ) {
    }

    #[Route('/api/public/order-tracking/{reference}', name: 'api_public_order_tracking', methods: ['GET'])]
    public function __invoke(string $reference, Request $request): JsonResponse
    {
        $boutique = $this->resolveBoutique($request);
        if (!$boutique instanceof Boutique || !$boutique->isVisiblePublicly()) {
            return $this->notFound();
        }

        $order = $this->orders->findForPublicTracking($boutique, $reference);
        if (null === $order) {
            return $this->notFound();
        }

        return new JsonResponse([
            'reference' => (string) $order->getId(),
            'status' => $order->getStatus()->value,
            'paymentStatus' => $order->getPaymentStatus()->value,
            'deliveryStatus' => $order->getDeliveryStatus(),
            'deliveryTracking' => $order->getDeliveryTracking(),
            'totalCents' => $order->getTotalCents(),
            'currency' => $order->getCurrency(),
            'createdAt' => $order->getCreatedAt()->format('c'),
            'items' => array_map(static fn ($item): array => [
                'name' => $item->getProductName(),
                'quantity' => $item->getQuantity(),
                'unitPriceCents' => $item->getUnitPriceCents(),
            ], $order->getItems()->toArray()),
        ]);
    }

    private function resolveBoutique(Request $request): ?Boutique
    {
        $boutique = $this->shopContext->getCurrentShop();
        if ($boutique instanceof Boutique) {
            return $boutique;
        }

        $slug = $request->query->get('boutiqueSlug') ?? $request->query->get('boutiqueId');
        if (!is_string($slug) || '' === trim($slug)) {
            return null;
        }

        return $this->boutiques->findBySlugOrId(trim($slug));
    }

    private function notFound(): JsonResponse
    {
        return new JsonResponse(['message' => 'Aucune commande trouvable.'], JsonResponse::HTTP_NOT_FOUND);
    }
}
