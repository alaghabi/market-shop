<?php

namespace App\EventSubscriber;

use App\Entity\AccountSubscription;
use App\Entity\AccountSubscriptionExtension;
use App\Factory\RedisFactory;
use App\Service\Subscription\AccountSubscriptionService;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Event\PostPersistEventArgs;
use Doctrine\ORM\Event\PostRemoveEventArgs;
use Doctrine\ORM\Event\PostUpdateEventArgs;
use Doctrine\ORM\Events;

#[AsDoctrineListener(event: Events::postPersist)]
#[AsDoctrineListener(event: Events::postUpdate)]
#[AsDoctrineListener(event: Events::postRemove)]
#[AsDoctrineListener(event: Events::postFlush)]
final class AccountSubscriptionCacheSubscriber
{
    private array $pendingUserIds = [];

    public function __construct(
        private RedisFactory $redisFactory,
    ) {
    }

    public function postPersist(PostPersistEventArgs $args): void
    {
        $this->collect($args->getObject());
    }

    public function postUpdate(PostUpdateEventArgs $args): void
    {
        $this->collect($args->getObject());
    }

    public function postRemove(PostRemoveEventArgs $args): void
    {
        $this->collect($args->getObject());
    }

    public function postFlush(PostFlushEventArgs $args): void
    {
        if ([] === $this->pendingUserIds) {
            return;
        }

        $redis = $this->redisFactory->create();
        if (null !== $redis) {
            foreach (array_unique($this->pendingUserIds) as $userId) {
                $redis->del(AccountSubscriptionService::CACHE_KEY_PREFIX.'.max-boutiques.'.$userId);
            }
        }

        $this->pendingUserIds = [];
    }

    private function collect(object $entity): void
    {
        $userId = match (true) {
            $entity instanceof AccountSubscription => (string) $entity->getUser()->getId(),
            $entity instanceof AccountSubscriptionExtension => (string) $entity->getAccountSubscription()->getUser()->getId(),
            default => null,
        };

        if (null !== $userId) {
            $this->pendingUserIds[$userId] = $userId;
        }
    }
}
