<?php

declare(strict_types=1);

namespace Readfly\Laravel\Libraries;

use Readfly\Laravel\Exceptions\ToolkitException;
use Readfly\Laravel\Support\File;
use SimpleSoftwareIO\QrCode\Generator;

class QrLibrary
{
    public static function create(string $content, string $path, int $size = 200, bool $force_merge = false): string
    {
        if (! class_exists(Generator::class)) {
            throw new ToolkitException('缺少依赖 simple-qrcode，请执行 composer require simplesoftwareio/simple-qrcode');
        }

        File::checkDirectory($path);

        if (file_exists($path) && ! $force_merge) {
            return $path;
        }

        $generator = (new Generator())->format('png')->size(max(32, $size))->encoding('UTF-8');
        $data = (string) $generator->generate($content);

        if ($data !== '') {
            File::put($path, $data);
        }

        if (! file_exists($path)) {
            throw new ToolkitException('二维码文件生成失败：'.$path);
        }

        return $path;
    }
}