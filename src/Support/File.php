<?php

declare(strict_types=1);

namespace Readfly\Laravel\Support;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

final class File
{
    public static function exists(string $path): bool
    {
        return file_exists($path);
    }

    public static function missing(string $path): bool
    {
        return ! file_exists($path);
    }

    public static function isDirectory(string $path): bool
    {
        return is_dir($path);
    }

    public static function makeDirectory(string $path, int $mode = 0777, bool $recursive = true): bool
    {
        if (is_dir($path)) {
            return true;
        }

        return @mkdir($path, $mode, $recursive);
    }

    public static function checkDirectory(string $path): bool
    {
        $directory = str_ends_with($path, '/') || str_ends_with($path, '\\') ? $path : dirname($path);

        return self::makeDirectory($directory);
    }

    public static function get(string $path, mixed $default = null): mixed
    {
        if (! is_file($path)) {
            return $default;
        }

        $content = @file_get_contents($path);

        return $content === false ? $default : $content;
    }

    public static function put(string $path, string $content, bool $lock = false): bool
    {
        self::checkDirectory($path);

        return @file_put_contents($path, $content, $lock ? LOCK_EX : 0) !== false;
    }

    public static function files(string $directory): array
    {
        if (! is_dir($directory)) {
            return [];
        }

        $files = [];
        foreach (new FilesystemIterator($directory, FilesystemIterator::SKIP_DOTS) as $file) {
            if (! $file->isFile()) {
                continue;
            }
            $path = $file->getPathname();
            $files[] = [
                'path' => $path,
                'basename' => $file->getBasename(),
                'filename' => $file->getBasename('.'.$file->getExtension()),
                'extension' => $file->getExtension(),
                'size' => $file->getSize(),
            ];
        }

        usort($files, static fn (array $a, array $b): int => strcmp($a['basename'], $b['basename']));

        return $files;
    }

    public static function allFiles(string $directory): array
    {
        if (! is_dir($directory)) {
            return [];
        }

        $files = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $files[] = $file->getPathname();
            }
        }

        sort($files);

        return $files;
    }

    public static function copy(string $from, string $to): bool
    {
        if (! is_file($from)) {
            return false;
        }

        self::checkDirectory($to);

        return @copy($from, $to);
    }

    public static function move(string $from, string $to): bool
    {
        if (! file_exists($from)) {
            return false;
        }

        self::checkDirectory($to);

        return @rename($from, $to);
    }

    public static function delete(string $path): bool
    {
        if (is_file($path) || is_link($path)) {
            return @unlink($path);
        }

        if (is_dir($path)) {
            $items = new FilesystemIterator($path, FilesystemIterator::SKIP_DOTS);
            foreach ($items as $item) {
                self::delete($item->getPathname());
            }

            return @rmdir($path);
        }

        return false;
    }

    public static function size(string $path): int
    {
        return is_file($path) ? (int) filesize($path) : 0;
    }
}