<?php

namespace App\Messenger\Middleware;

use App\Factory\RedisFactory;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;
use Symfony\Component\Messenger\Stamp\TransportMessageIdStamp;

final readonly class RedisMessageIdempotencyMiddleware implements MiddlewareInterface
{
    private const COMPLETED_TTL = 604800;
    private const LOCK_TTL = 3600;

    public function __construct(private RedisFactory $redisFactory)
    {
    }

    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        $received = $envelope->last(ReceivedStamp::class);
        $transportId = $envelope->last(TransportMessageIdStamp::class);

        if (!$received instanceof ReceivedStamp || !$transportId instanceof TransportMessageIdStamp) {
            return $stack->next()->handle($envelope, $stack);
        }

        $redis = $this->redisFactory->create();
        if (!$redis instanceof \Redis) {
            return $stack->next()->handle($envelope, $stack);
        }

        $identity = hash('sha256', $received->getTransportName().'|'.(string) $transportId->getId());
        $completedKey = 'messenger.idempotency.completed.'.$identity;
        $lockKey = 'messenger.idempotency.lock.'.$identity;

        if (0 < $redis->exists($completedKey)) {
            return $envelope;
        }

        $lockToken = bin2hex(random_bytes(16));
        if (!(bool) $redis->set($lockKey, $lockToken, ['nx', 'ex' => self::LOCK_TTL])) {
            return $envelope;
        }

        try {
            $result = $stack->next()->handle($envelope, $stack);
            $redis->setex($completedKey, self::COMPLETED_TTL, '1');
            $redis->del($lockKey);

            return $result;
        } catch (\Throwable $exception) {
            if ($lockToken === $redis->get($lockKey)) {
                $redis->del($lockKey);
            }

            throw $exception;
        }
    }
}
