<?php

declare(strict_types=1);

namespace Readfly\Laravel\Providers;

use Readfly\Laravel\Exceptions\ToolkitException;
use Readfly\Laravel\Providers\Douyin\MiniProgramingProvider;
use Readfly\Laravel\Support\Arr;
use Readfly\Laravel\Support\Str;

/**
 * @method static MiniProgramingProvider miniProgram(array $configs = [])
 */
class BytedanceProvider
{
    public static function miniProgram(array $configs = []): MiniProgramingProvider
    {
        return new MiniProgramingProvider($configs);
    }

    public static function douyin(array $configs = []): MiniProgramingProvider
    {
        return new MiniProgramingProvider($configs);
    }

    public static function __callStatic(string $name, array $arguments = []): mixed
    {
        $class = __NAMESPACE__.'\\Douyin\\'.Str::studly($name).'Provider';

        if (! class_exists($class)) {
            throw new ToolkitException('字节跳动服务不存在：'.$name);
        }

        return new $class(Arr::first($arguments) ?: []);
    }
}