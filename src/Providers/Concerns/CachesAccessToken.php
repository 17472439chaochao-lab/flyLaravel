<?php

declare(strict_types=1);

namespace Readfly\Laravel\Providers\Concerns;

use Illuminate\Support\Facades\Cache;

trait CachesAccessToken
{
    protected function cachedToken(string $key, callable $resolver, int $ttl = 7000): ?string
    {
        $cached = Cache::get($key);

        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        $token = $resolver();

        if (! is_string($token) || $token === '') {
            return null;
        }

        Cache::put($key, $token, max(1, $ttl));

        return $token;
    }

    protected function forgetToken(string $key): void
    {
        Cache::forget($key);
    }
}