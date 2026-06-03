<?php

namespace Teoprayoga\TeobiefyLaravelApiResponse\Replay;

use Illuminate\Cache\CacheManager;
use Illuminate\Contracts\Cache\Repository;
use RuntimeException;
use Teoprayoga\TeobiefyLaravelApiResponse\Exceptions\ReplayDetectedException;

class ReplayGuard
{
    public function __construct(
        private readonly Repository $cache,
        private readonly int $windowSeconds,
        private readonly int $nonceTtlSeconds,
        private readonly string $cachePrefix,
    ) {
        if ($windowSeconds < 1) {
            throw new RuntimeException('teobiefy.replay_protection.window_seconds must be positive.');
        }

        if ($nonceTtlSeconds < 2 * $windowSeconds) {
            throw new RuntimeException('teobiefy.replay_protection.nonce_ttl_seconds must be at least 2 * window_seconds.');
        }
    }

    public static function fromConfig(CacheManager $cacheManager): self
    {
        $store = config('teobiefy.replay_protection.cache_store');
        $repository = $cacheManager->store(is_string($store) && $store !== '' ? $store : null);

        return new self(
            $repository,
            (int) config('teobiefy.replay_protection.window_seconds', 300),
            (int) config('teobiefy.replay_protection.nonce_ttl_seconds', 600),
            (string) config('teobiefy.replay_protection.cache_prefix', 'teobiefy:rp:'),
        );
    }

    /**
     * @throws ReplayDetectedException
     */
    public function assert(int $timestamp, string $rnonce): void
    {
        $now = time();

        if (abs($now - $timestamp) > $this->windowSeconds) {
            throw ReplayDetectedException::because('Payload timestamp is outside the replay window.');
        }

        $key = $this->cachePrefix.hash('sha256', $rnonce);

        if (! $this->cache->add($key, true, $this->nonceTtlSeconds)) {
            throw ReplayDetectedException::because('Payload replay nonce has already been used.');
        }
    }
}
