<?php

declare(strict_types=1);

namespace App\Config;

use Redis as RedisClient;

class Redis
{
    private static ?RedisClient $instance = null;

    public static function connection(): RedisClient
    {
        if (self::$instance === null || !self::$instance->isConnected()) {
            self::$instance = self::createConnection();
        }
        return self::$instance;
    }

    private static function createConnection(): RedisClient
    {
        $redis = new RedisClient();
        $connected = $redis->connect(
            $_ENV['REDIS_HOST'] ?? 'localhost',
            (int) ($_ENV['REDIS_PORT'] ?? 6379),
            2.5  // timeout
        );

        if (!$connected) {
            throw new \RuntimeException('Redis connection failed', 500);
        }

        if (!empty($_ENV['REDIS_PASSWORD'])) {
            $redis->auth($_ENV['REDIS_PASSWORD']);
        }

        $redis->setOption(RedisClient::OPT_SERIALIZER, RedisClient::SERIALIZER_JSON);
        $redis->select(0);

        return $redis;
    }
}
