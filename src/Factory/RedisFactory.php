<?php

namespace App\Factory;

final class RedisFactory
{
    public function __construct(private readonly ?string $password = null)
    {
    }

    public function create(string $host = 'redis', int $port = 6379): ?\Redis
    {
        if (!class_exists(\Redis::class)) {
            return null;
        }

        $redis = new \Redis();
        $redis->connect($host, $port);
        if (null !== $this->password && '' !== $this->password) {
            $redis->auth($this->password);
        }

        return $redis;
    }
}
