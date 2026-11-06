<?php

declare(strict_types=1);

namespace Readfly\Laravel\Libraries;

use Illuminate\Support\Facades\Storage;
use Readfly\Laravel\Support\File;
use Throwable;

class StorageFileLibrary
{
    public static function check(string $storage_name, string $storage_disk = 'public', bool $force_clear = false): array
    {
        $disk = Storage::disk($storage_disk);
        $storage_dir = dirname($storage_name);
        $storage_path = '';

        try {
            $storage_path = $disk->path($storage_name);
            $storage_dir = $disk->path($storage_dir);

            if (! is_dir($storage_dir)) {
                File::makeDirectory($storage_dir);
            }

            if ($force_clear && is_file($storage_path)) {
                File::delete($storage_path);
            }
        } catch (Throwable) {
            $storage_dir = dirname($storage_name);
        }

        return compact('storage_name', 'storage_disk', 'storage_dir', 'storage_path');
    }

    public static function move(string $from_storage_path, string $to_storage_name, string $to_storage_disk = 'public', bool $force_merge = true): bool
    {
        if (! is_file($from_storage_path)) {
            return false;
        }

        $target = Storage::disk($to_storage_disk);

        if ($force_merge) {
            $target->delete($to_storage_name);
        }

        if ($target->exists($to_storage_name)) {
            return true;
        }

        $stream = fopen($from_storage_path, 'rb');

        if ($stream === false) {
            return false;
        }

        $target->writeStream($to_storage_name, $stream);

        if (is_resource($stream)) {
            fclose($stream);
        }

        if ($target->exists($to_storage_name)) {
            File::delete($from_storage_path);

            return true;
        }

        return false;
    }

    public static function remove(string $storage_name, string $storage_disk = 'public'): bool
    {
        try {
            $disk = Storage::disk($storage_disk);
            $path = $disk->path($storage_name);

            if (is_file($path)) {
                return File::delete($path);
            }
        } catch (Throwable) {
        }

        return Storage::disk($storage_disk)->delete($storage_name);
    }

    public static function put(string $storage_name, string $content, string $storage_disk = 'public'): bool
    {
        return Storage::disk($storage_disk)->put($storage_name, $content);
    }

    public static function exists(string $storage_name, string $storage_disk = 'public'): bool
    {
        return Storage::disk($storage_disk)->exists($storage_name);
    }

    public static function url(string $storage_name, string $storage_disk = 'public'): string
    {
        return Storage::disk($storage_disk)->url($storage_name);
    }
}