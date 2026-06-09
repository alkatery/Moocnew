<?php

declare(strict_types=1);

namespace App\Contexts\Analytics\Infrastructure;

use Illuminate\Contracts\Redis\Factory as RedisFactory;
use Illuminate\Support\Facades\Date;

/**
 * Tracks currently-online users with a Redis sorted set keyed by a recent
 * heartbeat timestamp (PRD §5.ي). Only an aggregate count is exposed — no
 * per-user "what are they studying now" surveillance (PDPL). Stale entries
 * are pruned on read.
 */
final class PresenceTracker
{
    private const KEY = 'presence:online';

    public function __construct(
        private readonly RedisFactory $redis,
        private readonly int $windowSeconds = 300,
    ) {}

    public function heartbeat(int $userId): void
    {
        $this->connection()->zadd(self::KEY, Date::now()->getTimestamp(), (string) $userId);
    }

    public function onlineCount(): int
    {
        $cutoff = Date::now()->getTimestamp() - $this->windowSeconds;

        $connection = $this->connection();
        $connection->zremrangebyscore(self::KEY, '-inf', (string) $cutoff);

        return (int) $connection->zcard(self::KEY);
    }

    private function connection(): mixed
    {
        return $this->redis->connection();
    }
}
