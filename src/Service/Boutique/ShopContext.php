<?php

namespace App\Service\Boutique;

use App\Entity\Boutique;
use App\Factory\RedisFactory;
use App\Repository\BoutiqueRepository;
use App\Security\BoutiqueContext;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

final readonly class ShopContext
{
    public const CACHE_KEY_PREFIX = 'shop:slug';
    public const DEFAULT_TTL = 21600;

    public function __construct(
        private BoutiqueRepository $boutiques,
        private RedisFactory $redisFactory,
        private SubdomainResolver $resolver,
        private RequestStack $requestStack,
        private BoutiqueContext $boutiqueContext,
        private TokenStorageInterface $tokenStorage,
        private string $rootDomain,
        private int $cacheTtl = self::DEFAULT_TTL,
    ) {
    }

    public function getCurrentShop(): ?Boutique
    {
        $request = $this->requestStack->getCurrentRequest();

        if (null === $request) {
            return null;
        }

        if ($request->attributes->has('_boutique')) {
            return $request->attributes->get('_boutique');
        }

        $slug = $this->resolveSlugFromRequest($request);

        if (null === $slug) {
            if ($this->isAuthenticatedStaff()) {
                return $this->resolveFromQueryOrPath($request);
            }

            return null;
        }

        $boutique = $this->fetchBoutiqueBySlug($slug);

        if (null === $boutique) {
            return null;
        }

        if ($this->isAuthenticatedStaff() && !$this->boutiqueContext->canAccessBoutique($boutique)) {
            return null;
        }

        if (!$boutique->isVisiblePublicly() && !$this->isAuthenticatedStaff()) {
            return null;
        }

        $request->attributes->set('_boutique', $boutique);
        $request->attributes->set('_boutique_id', $boutique->getId());

        return $boutique;
    }

    public function getCurrentShopId(): ?string
    {
        $boutique = $this->getCurrentShop();

        return null !== $boutique ? (string) $boutique->getId() : null;
    }

    public function getCurrentSlug(): ?string
    {
        $request = $this->requestStack->getCurrentRequest();

        if (null === $request) {
            return null;
        }

        $slug = $this->resolveSlugFromRequest($request);

        if (null === $slug && $this->isAuthenticatedStaff()) {
            return $request->query->get('slug');
        }

        return $slug;
    }

    public function clearCache(?string $slug): void
    {
        $redis = $this->redisFactory->create();
        if (null === $redis) {
            return;
        }

        if (null !== $slug) {
            $redis->del(self::CACHE_KEY_PREFIX.'.'.$slug);
        }
    }

    public function clearAllCache(): void
    {
        $redis = $this->redisFactory->create();
        if (null === $redis) {
            return;
        }

        $iterator = null;
        do {
            $keys = $redis->scan($iterator, self::CACHE_KEY_PREFIX.'.*');
            if (false !== $keys && [] !== $keys) {
                $redis->del($keys);
            }
        } while (0 !== $iterator);
    }

    private function resolveSlugFromRequest($request): ?string
    {
        $host = $request->getHost();
        $slug = $this->resolver->extractSubdomain($host);

        if (null === $slug || '' === $slug) {
            return null;
        }

        return $slug;
    }

    private function fetchBoutiqueBySlug(string $slug): ?Boutique
    {
        $redis = $this->redisFactory->create();
        $cacheKey = self::CACHE_KEY_PREFIX.'.'.$slug;

        if (null !== $redis) {
            $cachedId = $redis->get($cacheKey);
            if (false !== $cachedId && '' !== $cachedId) {
                $boutique = $this->boutiques->find($cachedId);
                if (null !== $boutique) {
                    return $boutique;
                }
            }
        }

        $boutique = $this->boutiques->findBySlug($slug);

        if (null !== $boutique && null !== $redis) {
            $redis->setex($cacheKey, $this->cacheTtl, (string) $boutique->getId());
        }

        return $boutique;
    }

    private function isAuthenticatedStaff(): bool
    {
        if (null === $this->tokenStorage->getToken()) {
            return false;
        }

        return $this->boutiqueContext->isStaff();
    }

    private function resolveFromQueryOrPath($request): ?Boutique
    {
        $boutiqueId = $request->query->get('boutiqueId')
            ?? $request->query->get('boutiqueSlug')
            ?? $request->attributes->get('boutiqueId');

        if (null === $boutiqueId || '' === (string) $boutiqueId) {
            return null;
        }

        $boutique = $this->boutiques->findBySlugOrId((string) $boutiqueId);
        if (null === $boutique || !$this->boutiqueContext->canAccessBoutique($boutique)) {
            return null;
        }

        $request->attributes->set('_boutique', $boutique);
        $request->attributes->set('_boutique_id', $boutique->getId());

        return $boutique;
    }
}
