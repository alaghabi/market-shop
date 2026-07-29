<?php

namespace App\State\Common;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\Common\HealthResource;
use App\Factory\RedisFactory;
use Doctrine\DBAL\Connection;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;

/** @implements ProviderInterface<HealthResource> */
final class HealthProvider implements ProviderInterface
{
    public function __construct(
        private readonly Connection $connection,
        private readonly RedisFactory $redisFactory,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): HealthResource
    {
        unset($operation, $uriVariables, $context);

        try {
            $this->connection->executeQuery('SELECT 1');
            $redis = $this->redisFactory->create();
            if (null === $redis || !$redis->ping()) {
                throw new \RuntimeException('Redis is unavailable.');
            }
        } catch (\Throwable $exception) {
            throw new ServiceUnavailableHttpException(null, 'Application dependencies are unavailable.', $exception);
        }

        return new HealthResource();
    }
}
