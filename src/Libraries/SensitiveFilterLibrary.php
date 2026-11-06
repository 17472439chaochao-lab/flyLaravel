<?php

declare(strict_types=1);

namespace Readfly\Laravel\Libraries;

use DfaFilter\SensitiveHelper;
use Readfly\Laravel\Exceptions\ToolkitException;
use Readfly\Laravel\Support\Path;

class SensitiveFilterLibrary
{
    private static ?SensitiveHelper $handler = null;

    private static ?string $loaded_file = null;

    public static function islegal(string $content_raw, ?string $filter_file_path = null): bool
    {
        return self::load($filter_file_path)->islegal($content_raw);
    }

    public static function replace(string $content_raw, string $replace_content = '*', bool $repeat = true, ?string $filter_file_path = null): string
    {
        return (string) self::load($filter_file_path)->replace($content_raw, $replace_content, $repeat);
    }

    public static function mark(string $content_raw, string $before = '<forbidden_word>', string $after = '</forbidden_word>', ?string $filter_file_path = null): string
    {
        return (string) self::load($filter_file_path)->mark($content_raw, $before, $after);
    }

    public static function forbidden_word(string $content_raw, int $limit = 0, ?string $filter_file_path = null): array
    {
        return (array) self::load($filter_file_path)->getBadWord($content_raw, 1, $limit);
    }

    public static function flush(): void
    {
        self::$handler = null;
        self::$loaded_file = null;
    }

    protected static function load(?string $filter_file_path = null): SensitiveHelper
    {
        $path = $filter_file_path !== null && $filter_file_path !== ''
            ? $filter_file_path
            : Path::package('data/forbidden_words.dic');

        if (self::$handler !== null && self::$loaded_file === $path) {
            return self::$handler;
        }

        if (! class_exists(SensitiveHelper::class)) {
            throw new ToolkitException('缺少依赖 lustre/php-dfa-sensitive，请执行 composer require lustre/php-dfa-sensitive');
        }

        if (! is_file($path)) {
            throw new ToolkitException('敏感词词库不存在：'.$path);
        }

        self::$handler = SensitiveHelper::init()->setTreeByFile($path);
        self::$loaded_file = $path;

        return self::$handler;
    }
}