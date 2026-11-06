<?php

declare(strict_types=1);

namespace Readfly\Laravel\Providers;

use Readfly\Laravel\Exceptions\ToolkitException;
use Readfly\Laravel\Providers\Qiniu\OssProvider;
use Readfly\Laravel\Support\Arr;
use Readfly\Laravel\Support\Str;

/**
 * @method static OssProvider oss(array $configs = [])
 * @method static OssProvider storage(array $configs = [])
 */
class QiniuProvider
{
    public static function oss(array $configs = []): OssProvider
    {
        return new OssProvider($configs);
    }

    public static function storage(array $configs = []): OssProvider
    {
        return self::oss($configs);
    }

    public static function __callStatic(string $name, array $arguments = []): mixed
    {
        $class = __NAMESPACE__.'\\Qiniu\\'.Str::studly($name).'Provider';

        if (! class_exists($class)) {
            throw new ToolkitException('七牛云服务不存在：'.$name);
        }

        return new $class(Arr::first($arguments) ?: []);
    }
}