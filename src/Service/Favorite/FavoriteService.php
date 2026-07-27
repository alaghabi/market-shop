<?php

namespace App\Service\Favorite;

use App\Dto\Favorite\ProductFavoriteOutput;
use App\Dto\Favorite\ShopFavoriteOutput;
use App\Entity\Boutique;
use App\Entity\Product;
use App\Entity\ProductFavorite;
use App\Entity\ShopFavorite;
use App\Entity\User;
use App\Repository\BoutiqueRepository;
use App\Repository\ProductFavoriteRepository;
use App\Repository\ProductRepository;
use App\Repository\ShopFavoriteRepository;
use App\Service\Module\ModuleAccessService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Uid\Uuid;

final readonly class FavoriteService
{
    public function __construct(
        private ProductFavoriteRepository $productFavorites,
        private ShopFavoriteRepository $shopFavorites,
        private ProductRepository $products,
        private BoutiqueRepository $boutiques,
        private EntityManagerInterface $em,
        private Security $security,
        private RequestStack $requestStack,
        private FavoriteCacheService $cache,
        private ModuleAccessService $moduleAccess,
    ) {
    }

    /** @return list<ProductFavoriteOutput> */
    public function listProductFavorites(): array
    {
        [$user, $sessionId] = $this->context(false);
        if (!$user && !$sessionId) {
            return [];
        }

        $cacheKey = $user ? $this->cache->productUserKey($user->getId()->toRfc4122()) : $this->cache->productSessionKey((string) $sessionId);

        return $this->cache->get($cacheKey, fn (): array => array_map([$this, 'toProductOutput'], $this->ownerProductFavorites($user, $sessionId)));
    }

    public function addProductFavorite(string $productId): ProductFavoriteOutput
    {
        [$user, $sessionId] = $this->context(true);
        $product = $this->findFavoriteableProduct($productId);

        $favorite = $user
            ? $this->productFavorites->findOneByUserAndProduct($user, $product)
            : $this->productFavorites->findOneBySessionAndProduct((string) $sessionId, $product);

        if (!$favorite instanceof ProductFavorite) {
            $favorite = new ProductFavorite($user, $user ? null : $sessionId, $product->getBoutique(), $product);
            $this->em->persist($favorite);
            $this->em->flush();
        }

        $this->invalidate($user, $sessionId);

        return $this->toProductOutput($favorite);
    }

    public function removeProductFavorite(string $productId): void
    {
        [$user, $sessionId] = $this->context(false);
        $product = $this->products->find($productId);
        if (!$product instanceof Product) {
            return;
        }

        $favorite = $user
            ? $this->productFavorites->findOneByUserAndProduct($user, $product)
            : ($sessionId ? $this->productFavorites->findOneBySessionAndProduct($sessionId, $product) : null);

        if ($favorite instanceof ProductFavorite) {
            $this->em->remove($favorite);
            $this->em->flush();
            $this->invalidate($user, $sessionId);
        }
    }

    /** @return list<ShopFavoriteOutput> */
    public function listShopFavorites(): array
    {
        [$user, $sessionId] = $this->context(false);
        if (!$user && !$sessionId) {
            return [];
        }

        $cacheKey = $user ? $this->cache->shopUserKey($user->getId()->toRfc4122()) : $this->cache->shopSessionKey((string) $sessionId);

        return $this->cache->get($cacheKey, fn (): array => array_map([$this, 'toShopOutput'], $this->ownerShopFavorites($user, $sessionId)));
    }

    public function addShopFavorite(string $shopId): ShopFavoriteOutput
    {
        [$user, $sessionId] = $this->context(true);
        $boutique = $this->findFavoriteableBoutique($shopId);

        $favorite = $user
            ? $this->shopFavorites->findOneByUserAndBoutique($user, $boutique)
            : $this->shopFavorites->findOneBySessionAndBoutique((string) $sessionId, $boutique);

        if (!$favorite instanceof ShopFavorite) {
            $favorite = new ShopFavorite($user, $user ? null : $sessionId, $boutique);
            $this->em->persist($favorite);
            $this->em->flush();
        }

        $this->invalidate($user, $sessionId);

        return $this->toShopOutput($favorite);
    }

    public function removeShopFavorite(string $shopId): void
    {
        [$user, $sessionId] = $this->context(false);
        $boutique = $this->boutiques->findBySlugOrId($shopId);
        if (!$boutique instanceof Boutique) {
            return;
        }

        $favorite = $user
            ? $this->shopFavorites->findOneByUserAndBoutique($user, $boutique)
            : ($sessionId ? $this->shopFavorites->findOneBySessionAndBoutique($sessionId, $boutique) : null);

        if ($favorite instanceof ShopFavorite) {
            $this->em->remove($favorite);
            $this->em->flush();
            $this->invalidate($user, $sessionId);
        }
    }

    /** @return array{0:?User,1:?string} */
    private function context(bool $createSession): array
    {
        $user = $this->security->getUser();
        $user = $user instanceof User ? $user : null;
        $sessionId = $this->sessionId($createSession);

        if ($user && $sessionId) {
            $this->mergeSessionFavorites($user, $sessionId);
            $sessionId = null;
            $this->clearSessionCookie();
        }

        return [$user, $sessionId];
    }

    private function mergeSessionFavorites(User $user, string $sessionId): void
    {
        $changed = false;

        foreach ($this->productFavorites->findBySession($sessionId) as $favorite) {
            if (!$this->productFavorites->findOneByUserAndProduct($user, $favorite->getProduct())) {
                $favorite->setUser($user);
                $favorite->setSessionId(null);
                $changed = true;
            }
        }

        foreach ($this->shopFavorites->findBySession($sessionId) as $favorite) {
            if (!$this->shopFavorites->findOneByUserAndBoutique($user, $favorite->getBoutique())) {
                $favorite->setUser($user);
                $favorite->setSessionId(null);
                $changed = true;
            }
        }

        if ($changed) {
            $this->em->flush();
        }

        $this->productFavorites->deleteBySession($sessionId);
        $this->shopFavorites->deleteBySession($sessionId);
        $this->invalidate($user, $sessionId);
    }

    private function sessionId(bool $create): ?string
    {
        $request = $this->requestStack->getCurrentRequest();
        if (null === $request) {
            return null;
        }

        $cookieName = $this->cookieName();
        $sessionId = $request->cookies->get($cookieName);
        if (is_string($sessionId) && '' !== $sessionId) {
            return $sessionId;
        }

        if (!$create) {
            return null;
        }

        $sessionId = Uuid::v7()->toRfc4122();
        $request->attributes->set(FavoriteCookieSubscriber::COOKIE_NAME_ATTRIBUTE, $cookieName);
        $request->attributes->set(FavoriteCookieSubscriber::COOKIE_VALUE_ATTRIBUTE, $sessionId);

        return $sessionId;
    }

    private function clearSessionCookie(): void
    {
        $request = $this->requestStack->getCurrentRequest();
        if (null === $request) {
            return;
        }

        $request->attributes->set(FavoriteCookieSubscriber::COOKIE_NAME_ATTRIBUTE, $this->cookieName());
        $request->attributes->set(FavoriteCookieSubscriber::COOKIE_CLEAR_ATTRIBUTE, true);
    }

    private function cookieName(): string
    {
        return 'market_shop_favorites';
    }

    private function findFavoriteableProduct(string $productId): Product
    {
        $product = $this->products->find($productId);
        if (!$product instanceof Product || !$product->isActive() || !$product->getBoutique()->isVisiblePublicly()) {
            throw new NotFoundHttpException('Product not found.');
        }

        if (!$this->moduleAccess->isModuleEnabled('wishlist', $product->getBoutique())) {
            throw new AccessDeniedHttpException('Les favoris ne sont pas activés pour cette boutique.');
        }

        return $product;
    }

    private function findFavoriteableBoutique(string $shopId): Boutique
    {
        $boutique = $this->boutiques->findBySlugOrId($shopId);
        if (!$boutique instanceof Boutique || !$boutique->isVisiblePublicly()) {
            throw new NotFoundHttpException('Shop not found.');
        }

        return $boutique;
    }

    /** @return list<ProductFavorite> */
    private function ownerProductFavorites(?User $user, ?string $sessionId): array
    {
        if ($user) {
            return $this->productFavorites->findByUser($user);
        }
        if ($sessionId) {
            return $this->productFavorites->findBySession($sessionId);
        }

        return [];
    }

    /** @return list<ShopFavorite> */
    private function ownerShopFavorites(?User $user, ?string $sessionId): array
    {
        if ($user) {
            return $this->shopFavorites->findByUser($user);
        }
        if ($sessionId) {
            return $this->shopFavorites->findBySession($sessionId);
        }

        return [];
    }

    private function invalidate(?User $user, ?string $sessionId): void
    {
        if ($user) {
            $userId = $user->getId()->toRfc4122();
            $this->cache->delete($this->cache->productUserKey($userId));
            $this->cache->delete($this->cache->shopUserKey($userId));
        }
        if ($sessionId) {
            $this->cache->delete($this->cache->productSessionKey($sessionId));
            $this->cache->delete($this->cache->shopSessionKey($sessionId));
        }
    }

    private function toProductOutput(ProductFavorite $favorite): ProductFavoriteOutput
    {
        $product = $favorite->getProduct();
        $output = new ProductFavoriteOutput();
        $output->id = (string) $favorite->getId();
        $output->shopId = (string) $favorite->getBoutique()->getId();
        $output->shopName = $favorite->getBoutique()->getName();
        $output->productId = (string) $product->getId();
        $output->productName = $product->getName();
        $output->productSlug = $product->getSlug();
        $output->sku = $product->getSku();
        $output->image = $product->getImages()->first() ? $product->getImages()->first()->getUrl() : null;
        $output->createdAt = $favorite->getCreatedAt();

        return $output;
    }

    private function toShopOutput(ShopFavorite $favorite): ShopFavoriteOutput
    {
        $boutique = $favorite->getBoutique();
        $output = new ShopFavoriteOutput();
        $output->id = (string) $favorite->getId();
        $output->shopId = (string) $boutique->getId();
        $output->shopName = $boutique->getName();
        $output->shopSlug = $boutique->getSlug();
        $output->coverImage = $boutique->getCoverImage();
        $output->createdAt = $favorite->getCreatedAt();

        return $output;
    }
}
