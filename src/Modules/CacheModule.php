<?php

declare(strict_types=1);

namespace Readfly\Laravel\Modules;

use Illuminate\Support\Facades\Cache;
use Psr\SimpleCache\InvalidArgumentException;

class CacheModule
{
    protected string $cache_name;

    protected int $expire_seconds;

    protected array $cache = [];

    protected string $driver = 'file';

    protected string $env = 'dev';

    protected string $locale = 'zh-CN';

    protected bool $loaded = false;

    /**
     * @throws InvalidArgumentException
     */
    public function __construct(string $name, int $expire_seconds = 0, string $driver = '', string $env = '', string $locale = '')
    {
        $this->env = $env !== '' ? $env : (string) config('app.env', 'production');
        $this->locale = $locale !== '' ? $locale : (string) config('app.locale', 'zh-CN');
        $this->cache_name = implode(':', array_filter([
            config('app.name'),
            $this->env,
            $this->locale,
            $name,
        ], static fn ($segment): bool => $segment !== null && $segment !== ''));
        $this->expire_seconds = max(0, $expire_seconds);
        $this->driver = $driver !== '' ? $driver : (string) config('cache.default', 'file');
        $this->read();
    }

    /**
     * @throws InvalidArgumentException
     */
    public function write(array $data): bool
    {
        $this->cache = $data;
        $this->loaded = true;
        $store = Cache::store($this->driver);

        return $this->expire_seconds > 0
            ? $store->put($this->cache_name, $data, $this->expire_seconds)
            : $store->forever($this->cache_name, $data);
    }

    /**
     * @throws InvalidArgumentException
     */
    public function clear(): bool
    {
        $this->cache = [];
        $this->loaded = true;

        return Cache::store($this->driver)->forget($this->cache_name);
    }

    /**
     * @throws InvalidArgumentException
     */
    public function get(string $index = '', mixed $default = false): mixed
    {
        if (! $this->loaded && ! $this->read()) {
            return $default;
        }

        return $index !== '' ? data_get($this->cache, $index, $default) : $this->cache;
    }

    public function key(): string
    {
        return $this->cache_name;
    }

    public function driver(): string
    {
        return $this->driver;
    }

    /**
     * @throws InvalidArgumentException
     */
    public function remember(callable $resolver): mixed
    {
        if ($this->loaded || $this->read()) {
            return $this->cache;
        }

        $data = $resolver($this);
        $this->write(is_array($data) ? $data : (array) $data);

        return $this->cache;
    }

    /**
     * @throws InvalidArgumentException
     */
    protected function read(): bool
    {
        $this->loaded = true;
        $cached = Cache::store($this->driver)->get($this->cache_name, []);
        $this->cache = is_array($cached) ? $cached : [];

        return $this->cache !== [];
    }
}