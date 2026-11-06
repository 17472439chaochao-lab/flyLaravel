<?php

declare(strict_types=1);

namespace Readfly\Laravel\Providers;

use Readfly\Laravel\Exceptions\ToolkitException;
use Readfly\Laravel\Providers\Aliyun\OssProvider;
use Readfly\Laravel\Providers\Aliyun\SmsProvider;
use Readfly\Laravel\Support\Arr;
use Readfly\Laravel\Support\Str;

/**
 * @method static SmsProvider sms(array $configs = [])
 * @method static OssProvider oss(array $configs = [])
 */
class AliyunProvider
{
    public static function sms(array $configs = []): SmsProvider
    {
        return new SmsProvider($configs);
    }

    public static function oss(array $configs = []): OssProvider
    {
        return new OssProvider($configs);
    }

    public static function __callStatic(string $name, array $arguments = []): mixed
    {
        $class = __NAMESPACE__.'\\Aliyun\\'.Str::studly($name).'Provider';

        if (! class_exists($class)) {
            throw new ToolkitException('阿里云服务不存在：'.$name);
        }

        return new $class(Arr::first($arguments) ?: []);
    }
}