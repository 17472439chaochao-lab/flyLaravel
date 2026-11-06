<?php

declare(strict_types=1);

namespace Readfly\Laravel\Libraries\Concerns;

use Intervention\Image\ImageManager;
use Readfly\Laravel\Exceptions\ToolkitException;

trait CreatesImageManager
{
    protected function imageManager(): ImageManager
    {
        if (! class_exists(ImageManager::class)) {
            throw new ToolkitException('缺少依赖 intervention/image，请执行 composer require intervention/image');
        }

        if (extension_loaded('imagick') && method_exists(ImageManager::class, 'imagick')) {
            return ImageManager::imagick();
        }

        if (method_exists(ImageManager::class, 'gd')) {
            return ImageManager::gd();
        }

        throw new ToolkitException('intervention/image 版本过低，请升级到 ^3.0');
    }
}