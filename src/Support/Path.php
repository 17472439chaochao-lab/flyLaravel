<?php

declare(strict_types=1);

namespace Readfly\Laravel\Support;

final class Path
{
    public static function package(string $path = ''): string
    {
        return self::join(dirname(__DIR__, 2), $path);
    }

    public static function base(string $path = ''): string
    {
        return self::join(function_exists('base_path') ? base_path() : (string) getcwd(), $path);
    }

    public static function public(string $path = ''): string
    {
        return self::join(function_exists('public_path') ? public_path() : self::base('public'), $path);
    }

    public static function storage(string $path = ''): string
    {
        return self::join(function_exists('storage_path') ? storage_path() : self::base('storage'), $path);
    }

    public static function logs(string $path = ''): string
    {
        return self::join(self::storage('logs'), $path);
    }

    public static function vendor(string $path = ''): string
    {
        return self::join(self::base('vendor'), $path);
    }

    public static function join(string ...$segments): string
    {
        $result = '';
        foreach ($segments as $segment) {
            if ($segment === '') {
                continue;
            }
            if ($result === '') {
                $result = rtrim($segment, '/\\');
                continue;
            }
            $result .= '/'.ltrim(rtrim($segment, '/\\'), '/\\');
        }

        return $result;
    }
}