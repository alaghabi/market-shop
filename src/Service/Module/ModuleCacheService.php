<?php

namespace App\Service\Module;

use App\Factory\RedisFactory;

final readonly class ModuleCacheService
{
    private const TTL = 600;
    private const KEY_PLATFORM = 'platform:modules';
    private const KEY_PLAN_PREFIX = 'plan:';
    private const KEY_PLAN_SUFFIX = ':modules';
    private const KEY_SHOP_PREFIX = 'shop:';
    private const KEY_SHOP_SUFFIX = ':modules';

    public function __construct(
        private RedisFactory $redisFactory,
    ) {
    }

    public function getPlatformModules(): ?array
    {
        return $this->get(self::KEY_PLATFORM);
    }

    public function setPlatformModules(array $data): void
    {
        $this->set(self::KEY_PLATFORM, $data);
    }

    public function deletePlatformModules(): void
    {
        $this->del(self::KEY_PLATFORM);
    }

    public function getPlanModules(string $planId): ?array
    {
        return $this->get(self::KEY_PLAN_PREFIX.$planId.self::KEY_PLAN_SUFFIX);
    }

    public function setPlanModules(string $planId, array $data): void
    {
        $this->set(self::KEY_PLAN_PREFIX.$planId.self::KEY_PLAN_SUFFIX, $data);
    }

    public function deletePlanModules(string $planId): void
    {
        $this->del(self::KEY_PLAN_PREFIX.$planId.self::KEY_PLAN_SUFFIX);
    }

    public function getShopModules(string $shopId): ?array
    {
        return $this->get(self::KEY_SHOP_PREFIX.$shopId.self::KEY_SHOP_SUFFIX);
    }

    public function setShopModules(string $shopId, array $data): void
    {
        $this->set(self::KEY_SHOP_PREFIX.$shopId.self::KEY_SHOP_SUFFIX, $data);
    }

    public function deleteShopModules(string $shopId): void
    {
        $this->del(self::KEY_SHOP_PREFIX.$shopId.self::KEY_SHOP_SUFFIX);
    }

    public function deleteAll(): void
    {
        try {
            $redis = $this->redisFactory->create();
            if (!$redis) {
                return;
            }
            foreach ([
                self::KEY_PLATFORM,
                self::KEY_PLAN_PREFIX.'*'.self::KEY_PLAN_SUFFIX,
                self::KEY_SHOP_PREFIX.'*'.self::KEY_SHOP_SUFFIX,
            ] as $pattern) {
                foreach ($redis->keys($pattern) as $key) {
                    $redis->del($key);
                }
            }
        } catch (\Throwable) {
        }
    }

    private function get(string $key): ?array
    {
        try {
            $redis = $this->redisFactory->create();
            if (!$redis) {
                return null;
            }
            $data = $redis->get($key);
            if (false === $data) {
                return null;
            }

            $decoded = json_decode($data, true);

            return \is_array($decoded) ? $decoded : null;
        } catch (\Throwable) {
            return null;
        }
    }

    private function set(string $key, array $data): void
    {
        try {
            $redis = $this->redisFactory->create();
            if (!$redis) {
                return;
            }
            $redis->setex($key, self::TTL, json_encode($data));
        } catch (\Throwable) {
        }
    }

    private function del(string $key): void
    {
        try {
            $redis = $this->redisFactory->create();
            if (!$redis) {
                return;
            }
            $redis->del($key);
        } catch (\Throwable) {
        }
    }
}
