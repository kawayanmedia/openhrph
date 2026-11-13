<?php

namespace App\Support\Tenancy;

use Closure;
use DateInterval;
use DateTimeInterface;
use Illuminate\Support\Facades\Cache;

class TenantCache
{
    /**
     * Build a cache key scoped to the active tenant.
     */
    public static function key(string $key): string
    {
        return self::keyFor(app(TenantContext::class)->id(), $key);
    }

    /**
     * Build a cache key for an explicit tenant.
     */
    public static function keyFor(int|string|null $tenant, string $key): string
    {
        $tenant ??= 'shared';

        return config('tenancy.cache_prefix', 'tenant').':'.$tenant.':'.$key;
    }

    /**
     * Retrieve an item from the tenant cache, or store the default value.
     */
    public static function remember(string $key, DateInterval|DateTimeInterface|int $ttl, Closure $callback): mixed
    {
        return Cache::remember(self::key($key), $ttl, $callback);
    }

    /**
     * Retrieve an item from the tenant cache forever, or store the default value.
     */
    public static function rememberForever(string $key, Closure $callback): mixed
    {
        return Cache::rememberForever(self::key($key), $callback);
    }

    /**
     * Remove an item from the tenant cache.
     */
    public static function forget(string $key): void
    {
        Cache::forget(self::key($key));
    }

    /**
     * Remove an explicit tenant's cache item.
     */
    public static function forgetFor(int|string|null $tenant, string $key): void
    {
        Cache::forget(self::keyFor($tenant, $key));
    }
}
