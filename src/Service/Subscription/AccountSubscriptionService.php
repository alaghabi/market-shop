<?php

namespace App\Service\Subscription;

use App\Entity\AccountSubscription;
use App\Entity\AccountSubscriptionExtension;
use App\Entity\Extension;
use App\Entity\SubscriptionPlan;
use App\Entity\User;
use App\Enum\ExtensionType;
use App\Factory\RedisFactory;
use App\Repository\AccountSubscriptionExtensionRepository;
use App\Repository\AccountSubscriptionRepository;
use App\Repository\BoutiqueRepository;
use App\Repository\PlanQuotaRepository;
use App\Service\AppConfigService;

final readonly class AccountSubscriptionService
{
    public const MAX_BOUTIQUES_QUOTA_CODE = 'max_boutiques';
    public const CACHE_KEY_PREFIX = 'account:subscription';
    public const DEFAULT_TTL = 21600;
    private const UNLIMITED_SENTINEL = -1;
    private const DEFAULT_OWNED_HARD_CAP = 50;

    public function __construct(
        private AccountSubscriptionRepository $accountSubscriptions,
        private AccountSubscriptionExtensionRepository $accountExtensions,
        private BoutiqueRepository $boutiques,
        private PlanQuotaRepository $planQuotas,
        private AppConfigService $appConfig,
        private RedisFactory $redisFactory,
        private int $cacheTtl = self::DEFAULT_TTL,
    ) {
    }

    public function getActiveSubscription(User $user): ?AccountSubscription
    {
        $subscription = $this->accountSubscriptions->findActiveByUser($user);

        if (null === $subscription) {
            return null;
        }

        $now = new \DateTimeImmutable();
        if (null !== $subscription->getEndDate() && $subscription->getEndDate() <= $now) {
            // Do not mutate on read — expiry is persisted by app:account-subscription-expiry.
            return null;
        }

        return $subscription;
    }

    /**
     * Persist overdue Active → Expired transitions. Intended for cron/CLI only.
     *
     * @return int number of subscriptions expired
     */
    public function expireOverdueSubscriptions(\DateTimeImmutable $now = new \DateTimeImmutable()): int
    {
        $expired = $this->accountSubscriptions->findExpiredButStillActive($now);
        foreach ($expired as $subscription) {
            $subscription->markAsExpired();
            $this->invalidate($subscription->getUser());
        }

        return \count($expired);
    }

    /**
     * Max boutiques the account may publish. Null means unlimited.
     *
     * Combines the active account subscription's plan quota with any active
     * QuotaBoost extensions targeting the max_boutiques quota. Falls back to the
     * platform default when the account has no active subscription.
     */
    public function getMaxBoutiques(User $user): ?int
    {
        [$hit, $cached] = $this->getCachedMaxBoutiques($user);
        if ($hit) {
            return $cached;
        }

        $subscription = $this->getActiveSubscription($user);

        if (null === $subscription) {
            $fallback = (int) ($this->appConfig->get()['boutiques']['max_boutiques_per_admin'] ?? 1);

            return $this->cacheMaxBoutiques($user, $fallback);
        }

        $limit = $this->resolveMaxBoutiques($subscription);

        return $this->cacheMaxBoutiques($user, $limit);
    }

    /**
     * Soft ceiling on boutique creation (owned, non-archived). Prevents unbounded
     * Pending drafts while keeping the hard quota gate on publication.
     */
    public function getBoutiqueCreationCap(User $user): int
    {
        $maxPublished = $this->getMaxBoutiques($user);
        if (null === $maxPublished) {
            return (int) ($this->appConfig->get()['boutiques']['max_owned_boutiques_per_admin'] ?? self::DEFAULT_OWNED_HARD_CAP);
        }

        return max($maxPublished * 3, $maxPublished + 5);
    }

    public function getPublishedBoutiqueCount(User $user): int
    {
        return $this->boutiques->countPublishedByOwner($user);
    }

    public function getOwnedBoutiqueCount(User $user): int
    {
        return $this->boutiques->countOwnedByOwner($user);
    }

    /** @return AccountSubscriptionExtension[] */
    public function getActiveExtensions(AccountSubscription $subscription): array
    {
        $now = new \DateTimeImmutable();
        $grants = $this->accountExtensions->findActiveByAccountSubscription($subscription);

        return array_values(array_filter(
            $grants,
            static fn (AccountSubscriptionExtension $grant): bool => !$grant->isExpired($now),
        ));
    }

    /**
     * Renewal total = effective plan renewal price + sum of active extension prices.
     * Effective plan renewal = renewalPriceTnd if set, otherwise priceTnd (free allowed = 0).
     */
    public function getRenewalPrice(AccountSubscription $subscription): int
    {
        return $this->getPlanRenewalBasePrice($subscription->getSubscriptionPlan())
            + $this->getActiveExtensionsPrice($subscription);
    }

    public function getPlanRenewalBasePrice(?SubscriptionPlan $plan): int
    {
        return $plan?->getEffectiveRenewalPriceTnd() ?? 0;
    }

    public function getActiveExtensionsPrice(AccountSubscription $subscription): int
    {
        $extensionsPrice = 0;
        foreach ($this->getActiveExtensions($subscription) as $grant) {
            $extensionsPrice += $grant->getExtension()->getPriceTnd();
        }

        return $extensionsPrice;
    }

    /**
     * Estimated renewal total for a target plan keeping the given extensions.
     *
     * @param iterable<AccountSubscriptionExtension|Extension> $extensions
     */
    public function estimateRenewalPrice(SubscriptionPlan $plan, iterable $extensions): int
    {
        $now = new \DateTimeImmutable();
        $extensionsPrice = 0;
        foreach ($extensions as $extensionOrGrant) {
            if ($extensionOrGrant instanceof AccountSubscriptionExtension) {
                if (!$extensionOrGrant->isActive() || $extensionOrGrant->isExpired($now)) {
                    continue;
                }
                $extensionsPrice += $extensionOrGrant->getExtension()->getPriceTnd();
                continue;
            }
            $extensionsPrice += $extensionOrGrant->getPriceTnd();
        }

        return $plan->getEffectiveRenewalPriceTnd() + $extensionsPrice;
    }

    /**
     * Compute the resulting max boutiques for a given plan combined with an
     * arbitrary set of extensions (used for change preview).
     *
     * @param iterable<AccountSubscriptionExtension|Extension> $extensions
     */
    public function getMaxBoutiquesForPlanAndExtensions(?SubscriptionPlan $plan, iterable $extensions): ?int
    {
        $now = new \DateTimeImmutable();

        if (null === $plan) {
            $baseLimit = 0;
            $unlimited = true;
        } else {
            $limitMap = $this->planQuotas->findLimitMapByPlan($plan);
            $baseLimit = 0;
            $unlimited = false;
            if (!\array_key_exists(self::MAX_BOUTIQUES_QUOTA_CODE, $limitMap)) {
                // Explicit null rows encode unlimited; missing row on a plan that
                // intentionally seeds none (e.g. Premium) is also unlimited.
                $unlimited = true;
            } elseif (null === $limitMap[self::MAX_BOUTIQUES_QUOTA_CODE]) {
                $unlimited = true;
            } else {
                $baseLimit = $limitMap[self::MAX_BOUTIQUES_QUOTA_CODE];
            }
        }

        $boost = 0;
        foreach ($extensions as $extensionOrGrant) {
            if ($extensionOrGrant instanceof AccountSubscriptionExtension) {
                if (!$extensionOrGrant->isActive() || $extensionOrGrant->isExpired($now)) {
                    continue;
                }
                $extension = $extensionOrGrant->getExtension();
            } else {
                $extension = $extensionOrGrant;
            }

            if (ExtensionType::QuotaBoost !== $extension->getType()
                || self::MAX_BOUTIQUES_QUOTA_CODE !== $extension->getTargetCode()
            ) {
                continue;
            }
            $boost += $extension->getValue() ?? 0;
        }

        if ($unlimited) {
            return null;
        }

        return $baseLimit + $boost;
    }

    public function invalidate(User $user): void
    {
        $redis = $this->redisFactory->create();
        if (null === $redis) {
            return;
        }

        $redis->del(self::CACHE_KEY_PREFIX.'.max-boutiques.'.(string) $user->getId());
    }

    public function clearAllCache(): void
    {
        $redis = $this->redisFactory->create();
        if (null === $redis) {
            return;
        }

        $iterator = null;
        do {
            $keys = $redis->scan($iterator, self::CACHE_KEY_PREFIX.'.max-boutiques.*');
            if (false !== $keys && [] !== $keys) {
                $redis->del($keys);
            }
        } while (0 !== $iterator);
    }

    private function resolveMaxBoutiques(AccountSubscription $subscription): ?int
    {
        return $this->getMaxBoutiquesForPlanAndExtensions(
            $subscription->getSubscriptionPlan(),
            $subscription->getExtensions(),
        );
    }

    /**
     * @return array{0: bool, 1: ?int} [hit, value] — value null means unlimited when hit
     */
    private function getCachedMaxBoutiques(User $user): array
    {
        $redis = $this->redisFactory->create();
        if (null === $redis) {
            return [false, null];
        }

        $cached = $redis->get(self::CACHE_KEY_PREFIX.'.max-boutiques.'.(string) $user->getId());
        if (false === $cached || '' === $cached) {
            return [false, null];
        }

        $value = (int) $cached;

        return [true, self::UNLIMITED_SENTINEL === $value ? null : $value];
    }

    private function cacheMaxBoutiques(User $user, ?int $limit): ?int
    {
        $redis = $this->redisFactory->create();
        if (null === $redis) {
            return $limit;
        }

        $redis->setex(
            self::CACHE_KEY_PREFIX.'.max-boutiques.'.(string) $user->getId(),
            $this->cacheTtl,
            (string) (null === $limit ? self::UNLIMITED_SENTINEL : $limit),
        );

        return $limit;
    }
}
