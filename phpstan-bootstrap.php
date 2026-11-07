<?php

declare(strict_types=1);

if (! function_exists('config')) {
    function config(?string $key = null, mixed $default = null): mixed
    {
        return $default;
    }
}

if (! function_exists('app_path')) {
    function app_path(string $path = ''): string
    {
        return $path;
    }
}

if (! function_exists('base_path')) {
    function base_path(string $path = ''): string
    {
        return $path;
    }
}

if (! function_exists('config_path')) {
    function config_path(string $path = ''): string
    {
        return $path;
    }
}

if (! function_exists('database_path')) {
    function database_path(string $path = ''): string
    {
        return $path;
    }
}

if (! function_exists('public_path')) {
    function public_path(string $path = ''): string
    {
        return $path;
    }
}

if (! function_exists('storage_path')) {
    function storage_path(string $path = ''): string
    {
        return $path;
    }
}

if (! function_exists('resource_path')) {
    function resource_path(string $path = ''): string
    {
        return $path;
    }
}


if (! function_exists('response')) {
    function response(mixed $content = null, int $status = 200, array $headers = [], int $options = 0): \Illuminate\Contracts\Routing\ResponseFactory
    {
        throw new \RuntimeException('response() stub');
    }
}
