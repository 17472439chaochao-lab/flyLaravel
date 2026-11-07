<?php

declare(strict_types=1);

require __DIR__.'/../vendor/autoload.php';

if (! function_exists('array_last')) {
    function array_last(array $array): mixed
    {
        return $array === [] ? null : end($array);
    }
}

if (! function_exists('array_first')) {
    function array_first(array $array): mixed
    {
        return $array === [] ? null : reset($array);
    }
}

if (! function_exists('config')) {
    function config(?string $key = null, mixed $default = null): mixed
    {
        $container = \Illuminate\Container\Container::getInstance();

        if ($container === null || ! $container->bound('config')) {
            return $default;
        }

        $repository = $container->make('config');

        return $key === null ? $repository : $repository->get($key, $default);
    }
}

if (! function_exists('base_path')) {
    function base_path(string $path = ''): string
    {
        return \Readfly\Laravel\Tests\Unit\Support\TestPaths::path($path);
    }
}

if (! function_exists('app_path')) {
    function app_path(string $path = ''): string
    {
        return base_path('app'.($path !== '' ? '/'.ltrim($path, '/') : ''));
    }
}

if (! function_exists('database_path')) {
    function database_path(string $path = ''): string
    {
        return base_path('database'.($path !== '' ? '/'.ltrim($path, '/') : ''));
    }
}

if (! function_exists('config_path')) {
    function config_path(string $path = ''): string
    {
        return base_path('config'.($path !== '' ? '/'.ltrim($path, '/') : ''));
    }
}

if (! function_exists('storage_path')) {
    function storage_path(string $path = ''): string
    {
        return base_path('storage'.($path !== '' ? '/'.ltrim($path, '/') : ''));
    }
}

if (! function_exists('public_path')) {
    function public_path(string $path = ''): string
    {
        return base_path('public'.($path !== '' ? '/'.ltrim($path, '/') : ''));
    }
}

if (! function_exists('resource_path')) {
    function resource_path(string $path = ''): string
    {
        return base_path('resources'.($path !== '' ? '/'.ltrim($path, '/') : ''));
    }
}
