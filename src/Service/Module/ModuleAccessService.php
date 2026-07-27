<?php

namespace App\Service\Module;

use App\Entity\Boutique;
use App\Entity\SubscriptionPlan;
use App\Entity\SubscriptionPlanModule;
use App\Enum\ExtensionType;
use App\Enum\SubscriptionStatus;
use App\Repository\BoutiqueExtensionRepository;
use App\Repository\PlatformModuleRepository;
use App\Repository\ShopModuleRepository;
use App\Repository\SubscriptionModuleRepository;
use App\Service\AppConfigService;
use Doctrine\ORM\EntityManagerInterface;

final readonly class ModuleAccessService
{
    public function __construct(
        private PlatformModuleRepository $platformModules,
        private ShopModuleRepository $shopModules,
        private SubscriptionModuleRepository $subscriptionModules,
        private BoutiqueExtensionRepository $boutiqueExtensions,
        private EntityManagerInterface $em,
        private ModuleCacheService $cache,
        private AppConfigService $appConfig,
        private array $modules = [],
    ) {
    }

    public function isModuleEnabled(string $moduleCode, Boutique $boutique): bool
    {
        $module = $this->findModule($moduleCode);
        if (null === $module) {
            return false;
        }

        if (!$this->isGloballyEnabled($module, $boutique)) {
            return false;
        }

        if (!$this->isAllowedBySubscription($module, $boutique)) {
            return false;
        }

        if (!$this->isEnabledInBoutique($module, $boutique)) {
            return false;
        }

        return true;
    }

    public function isGloballyEnabled(SubscriptionPlanModule $module, ?Boutique $boutique = null): bool
    {
        if (isset($this->modules[$module->getCode()]) && !$this->modules[$module->getCode()]) {
            return false;
        }

        $configKey = $this->moduleConfigKey($module->getCode());
        if (null !== $configKey && !$this->appConfig->isModuleEnabled($configKey)) {
            return false;
        }

        $cached = $this->cache->getPlatformModules();
        if (null !== $cached) {
            return !isset($cached[$module->getCode()]) || $cached[$module->getCode()];
        }

        $platformModule = $this->platformModules->findOneByModule($module);
        $enabled = null === $platformModule || $platformModule->isEnabled();

        $this->cache->setPlatformModules($this->buildPlatformCache());

        return $enabled;
    }

    public function isAllowedBySubscription(SubscriptionPlanModule $module, Boutique $boutique): bool
    {
        $currentSubscription = $boutique->getCurrentSubscription();

        if (null === $currentSubscription || SubscriptionStatus::Active !== $currentSubscription->getStatus()) {
            return false;
        }

        if ($module->isCore()) {
            return true;
        }

        $plan = $currentSubscription->getSubscriptionPlan();
        if (null === $plan) {
            return false;
        }

        $planId = (string) $plan->getId();

        $cached = $this->cache->getPlanModules($planId);
        if (null !== $cached) {
            return (isset($cached[$module->getCode()]) && $cached[$module->getCode()])
                || $this->hasActiveModuleExtension($boutique, $module->getCode());
        }

        $allowedCodes = $this->subscriptionModules->findAllowedModuleCodes($plan);
        $allowedMap = array_fill_keys($allowedCodes, true);

        if ([] === $allowedCodes) {
            $planModules = $plan->getModules();
            if (null === $planModules) {
                return true;
            }

            $allowedMap = array_fill_keys($planModules, true);
        }

        $this->cache->setPlanModules($planId, $allowedMap);

        return isset($allowedMap[$module->getCode()])
            || $this->hasActiveModuleExtension($boutique, $module->getCode());
    }

    public function isEnabledInBoutique(SubscriptionPlanModule $module, Boutique $boutique): bool
    {
        $shopId = (string) $boutique->getId();

        $cached = $this->cache->getShopModules($shopId);
        if (null !== $cached) {
            return !isset($cached[$module->getCode()]) || $cached[$module->getCode()];
        }

        $shopModule = $this->shopModules->findOneByBoutiqueAndModule($boutique, $module);
        $enabled = null === $shopModule || $shopModule->isEnabled();

        $this->cache->setShopModules($shopId, $this->buildShopCache($boutique));

        return $enabled;
    }

    /** @return SubscriptionPlanModule[] */
    public function getAllowedModulesForPlan(SubscriptionPlan $plan): array
    {
        $allowedCodes = $this->subscriptionModules->findAllowedModuleCodes($plan);

        if ([] === $allowedCodes) {
            return $this->findAllModules();
        }

        return array_filter(
            $this->findAllModules(),
            fn (SubscriptionPlanModule $m) => in_array($m->getCode(), $allowedCodes, true),
        );
    }

    public function getAccessibleModules(Boutique $boutique): array
    {
        return array_filter(
            $this->findAllModules(),
            fn (SubscriptionPlanModule $m) => $this->isModuleEnabled($m->getCode(), $boutique),
        );
    }

    public function getAvailableModules(Boutique $boutique): array
    {
        $allModules = $this->findAllModules();

        $result = [];
        foreach ($allModules as $module) {
            $result[] = [
                'module' => $module,
                'globallyEnabled' => $this->isGloballyEnabled($module, $boutique),
                'allowedBySubscription' => $this->isAllowedBySubscription($module, $boutique),
                'enabledInBoutique' => $this->isEnabledInBoutique($module, $boutique),
                'accessible' => $this->isModuleEnabled($module->getCode(), $boutique),
            ];
        }

        return $result;
    }

    public function invalidatePlatformCache(): void
    {
        $this->cache->deletePlatformModules();
    }

    public function invalidatePlanCache(string $planId): void
    {
        $this->cache->deletePlanModules($planId);
    }

    public function invalidateShopCache(string $shopId): void
    {
        $this->cache->deleteShopModules($shopId);
    }

    private function buildPlatformCache(): array
    {
        $all = $this->platformModules->findAll();
        $map = [];
        foreach ($all as $pm) {
            $map[$pm->getModule()->getCode()] = $pm->isEnabled();
        }

        return $map;
    }

    private function buildShopCache(Boutique $boutique): array
    {
        $all = $this->shopModules->findByBoutique($boutique);
        $map = [];
        foreach ($all as $sm) {
            $map[$sm->getModule()->getCode()] = $sm->isEnabled();
        }

        return $map;
    }

    private function findModule(string $code): ?SubscriptionPlanModule
    {
        return $this->em->getRepository(SubscriptionPlanModule::class)->findOneBy(['code' => $code]);
    }

    /** @return SubscriptionPlanModule[] */
    private function findAllModules(): array
    {
        return $this->em->getRepository(SubscriptionPlanModule::class)->findBy([], ['category' => 'ASC', 'name' => 'ASC']);
    }

    private function hasActiveModuleExtension(Boutique $boutique, string $moduleCode): bool
    {
        $now = new \DateTimeImmutable();

        foreach ($this->boutiqueExtensions->findActiveByBoutique($boutique) as $grant) {
            $extension = $grant->getExtension();
            if (!$extension->isActive()
                || ExtensionType::Module !== $extension->getType()
                || $moduleCode !== $extension->getTargetCode()
                || $grant->isExpired($now)) {
                continue;
            }

            return true;
        }

        return false;
    }

    private function moduleConfigKey(string $moduleCode): ?string
    {
        return match ($moduleCode) {
            'coupons' => 'coupons',
            'promotions' => 'promotions',
            'reviews' => 'avis',
            'blog' => 'blog',
            'seo_advanced' => 'seo',
            'analytics' => 'notifications',
            default => null,
        };
    }
}
