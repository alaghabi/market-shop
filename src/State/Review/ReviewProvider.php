<?php

namespace App\State\Review;

use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use ApiPlatform\State\Pagination\PaginatorInterface;
use App\Dto\Review\ReviewOutput;
use App\Entity\Review;
use App\Repository\ProductRepository;
use App\Repository\ReviewRepository;
use App\Security\BoutiqueContext;
use App\Service\Backoffice\BackofficeScopeResolver;
use App\Service\Module\ModuleAccessService;
use App\State\Common\BoutiqueAwareProviderTrait;
use App\State\Common\BackofficePaginator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/** @implements ProviderInterface<ReviewOutput> */
final readonly class ReviewProvider implements ProviderInterface
{
    use BoutiqueAwareProviderTrait;

    public function __construct(
        private ReviewRepository $reviews,
        private ProductRepository $products,
        private BoutiqueContext $context,
        private TokenStorageInterface $tokenStorage,
        private Security $security,
        private ModuleAccessService $moduleAccess,
        private BackofficeScopeResolver $scope,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): PaginatorInterface|ReviewOutput|null
    {
        $request = $context['request'] ?? null;
        $request = $request instanceof Request ? $request : null;
        $pagination = $this->scope->pagination($request);
        $reviewId = $uriVariables['id'] ?? null;

        if (null !== $reviewId) {
            $review = $this->reviews->find($reviewId);
            if (!$review) {
                return null;
            }

            $boutique = $review->getBoutique() ?? $review->getProduct()?->getBoutique();
            if ($boutique && !$this->context->canAccessBoutique($boutique)) {
                return null;
            }

            return $this->toOutput($review);
        }

        $boutiqueId = $uriVariables['boutiqueId'] ?? null;
        $productId = $uriVariables['productId'] ?? null;

        if (null === $boutiqueId && 'platform_reviews' === $operation->getName()) {
            $result = $this->security->isGranted('ROLE_SUPER_ADMIN')
                ? $this->reviews->findPlatformReviewsForAdminPaginated($pagination['page'], $pagination['itemsPerPage'])
                : $this->reviews->findApprovedPlatformReviewsPaginated($pagination['page'], $pagination['itemsPerPage']);

            return new BackofficePaginator(
                array_map([$this, 'toOutput'], $result['items']),
                $pagination['page'],
                $pagination['itemsPerPage'],
                $result['total'],
            );
        }

        $boutique = $this->resolveBoutiqueFromRequest($context, $uriVariables);

        if (!$boutique) {
            if ($this->security->isGranted('ROLE_SUPER_ADMIN') && $operation instanceof GetCollection) {
                $result = $this->reviews->findAllForAdminPaginated(
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

            return $operation instanceof GetCollection
                ? new BackofficePaginator([], $pagination['page'], $pagination['itemsPerPage'], 0)
                : null;
        }

        $token = $this->tokenStorage->getToken();
        $isAuthenticated = null !== $token && $token->getUser();
        $isAdmin = $isAuthenticated && $this->context->canAccessBoutique($boutique);

        if (!$isAdmin && !$this->moduleAccess->isModuleEnabled('reviews', $boutique)) {
            return new BackofficePaginator([], $pagination['page'], $pagination['itemsPerPage'], 0);
        }

        if (null !== $productId) {
            $product = $this->products->findBySlugOrId($productId, $boutique);
            if (!$product) {
                return new BackofficePaginator([], $pagination['page'], $pagination['itemsPerPage'], 0);
            }

            $result = $isAdmin
                ? $this->reviews->findByProductForAdminPaginated($product, $pagination['page'], $pagination['itemsPerPage'])
                : $this->reviews->findApprovedByProductPaginated($product, $pagination['page'], $pagination['itemsPerPage']);
        } else {
            $result = $isAdmin
                ? $this->reviews->findByBoutiqueForAdminPaginated($boutique, $pagination['page'], $pagination['itemsPerPage'])
                : $this->reviews->findApprovedByBoutiquePaginated($boutique, $pagination['page'], $pagination['itemsPerPage']);
        }

        return new BackofficePaginator(
            array_map([$this, 'toOutput'], $result['items']),
            $pagination['page'],
            $pagination['itemsPerPage'],
            $result['total'],
        );
    }

    private function toOutput(Review $entity): ReviewOutput
    {
        $output = new ReviewOutput();
        $boutique = $entity->getBoutique() ?? $entity->getProduct()?->getBoutique();
        $output->id = (string) $entity->getId();
        $output->boutiqueId = $boutique ? (string) $boutique->getId() : null;
        $output->boutiqueName = $boutique?->getName();
        $output->productId = null !== $entity->getProduct() ? (string) $entity->getProduct()->getId() : null;
        $output->productName = $entity->getProduct()?->getName();
        $category = $entity->getProduct()?->getCategory();
        $output->categoryId = $category ? (string) $category->getId() : null;
        $output->categoryName = $category?->getName();
        $output->targetType = $entity->getProduct() ? 'product' : ($category ? 'category' : ($entity->getBoutique() ? 'boutique' : 'general'));
        $output->userId = null !== $entity->getUser() ? (string) $entity->getUser()->getId() : null;
        $output->authorName = $entity->getAuthorName();
        $output->authorEmail = $entity->getAuthorEmail();
        $output->authorPhone = $entity->getAuthorPhone();
        $output->rating = $entity->getRating();
        $output->title = $entity->getTitle();
        $output->comment = $entity->getComment();
        $output->images = $entity->getImages();
        $output->isVerifiedPurchase = $entity->isVerifiedPurchase();
        $output->status = $entity->getStatus()->value;
        $output->createdAt = $entity->getCreatedAt();

        return $output;
    }
}
