<?php

declare(strict_types=1);

namespace Readfly\Laravel\Providers;

use Readfly\Laravel\Exceptions\ToolkitException;
use Readfly\Laravel\Providers\Wechat\MiniProgramingProvider;
use Readfly\Laravel\Providers\Wechat\OfficeAccountProvider;
use Readfly\Laravel\Support\Arr;
use Readfly\Laravel\Support\Str;

/**
 * @method static OfficeAccountProvider officeAccount(array $configs = [])
 * @method static MiniProgramingProvider miniProgram(array $configs = [])
 */
class WechatProvider
{
    public static function officeAccount(array $configs = []): OfficeAccountProvider
    {
        return new OfficeAccountProvider($configs);
    }

    public static function miniProgram(array $configs = []): MiniProgramingProvider
    {
        return new MiniProgramingProvider($configs);
    }

    public static function miniPrograming(array $configs = []): MiniProgramingProvider
    {
        return new MiniProgramingProvider($configs);
    }

    public static function __callStatic(string $name, array $arguments = []): mixed
    {
        $class = __NAMESPACE__.'\\Wechat\\'.Str::studly($name).'Provider';

        if (! class_exists($class)) {
            throw new ToolkitException('微信服务不存在：'.$name);
        }

        return new $class(Arr::first($arguments) ?: []);
    }
}