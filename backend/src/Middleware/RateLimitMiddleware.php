<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Config\Redis;
use App\Helpers\Response;

class RateLimitMiddleware
{
    public function handle(string $identifier = '', int $maxRequests = 0, int $windowSeconds = 60): void
    {
        $ip      = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $max     = $maxRequests ?: (int) ($_ENV['RATE_LIMIT_REQUESTS'] ?? 60);
        $window  = $windowSeconds ?: (int) ($_ENV['RATE_LIMIT_WINDOW'] ?? 60);
        $key     = 'rate_limit:api:' . ($identifier ?: $ip);

        $redis   = Redis::connection();
        $current = $redis->incr($key);

        if ($current === 1) {
            $redis->expire($key, $window);
        }

        $ttl = $redis->ttl($key);

        header('X-RateLimit-Limit: ' . $max);
        header('X-RateLimit-Remaining: ' . max(0, $max - $current));
        header('X-RateLimit-Reset: ' . (time() + $ttl));

        if ($current > $max) {
            header('Retry-After: ' . $ttl);
            Response::error('Too many requests. Please slow down.', 429);
            exit;
        }
    }
}
