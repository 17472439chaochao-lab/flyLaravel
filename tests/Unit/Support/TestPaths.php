<?php

declare(strict_types=1);

namespace Readfly\Laravel\Tests\Unit\Support;

final class TestPaths
{
    public static string $base = '';

    public static function path(string $path = ''): string
    {
        return self::$base.($path !== '' ? '/'.ltrim($path, '/') : '');
    }
}
