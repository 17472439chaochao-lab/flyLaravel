<?php

use Illuminate\Support\Arr;
use Readfly\Laravel\Support\File;

if (! function_exists('proxy_assets')) {
    function proxy_assets(string $path, string $bucket = '', bool $version = false): string
    {
        $assets = config('project.domains.assets') ?: config('project.domains.cdn');
        $prefix = $bucket !== '' ? trim($bucket, '/').'/' : '';

        if (is_string($assets) && $assets !== '') {
            $url = rtrim($assets, '/').'/'.$prefix.ltrim($path, '/');
        } else {
            $url = rtrim((string) config('app.url', ''), '/').'/'.$prefix.ltrim($path, '/');
        }

        if ($version) {
            $url .= (str_contains($url, '?') ? '&' : '?').'v='.config('toolkit.app_version');
        }

        return $url;
    }
}

if (! function_exists('format_datetime')) {
    function format_datetime(string $format = 'Y-m-d H:i:s', mixed $time = false): string
    {
        if ($time === false || $time === null || $time === '') {
            return date($format);
        }

        if ($time instanceof DateTimeInterface) {
            return $time->format($format);
        }

        $timestamp = is_numeric($time) ? (int) $time : strtotime((string) $time);

        return date($format, $timestamp !== false ? $timestamp : time());
    }
}

if (! function_exists('exception_logger_contents')) {
    function exception_logger_contents(Throwable $exception, array $data = []): array
    {
        return [
            'code' => $exception->getCode(),
            'message' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'data' => $data,
        ];
    }
}

if (! function_exists('random_avatar')) {
    function random_avatar(string $default = ''): string
    {
        $files = File::allFiles(public_path('static/avatars'));

        if ($files === []) {
            return $default;
        }

        $avatar = Arr::random($files);

        return proxy_assets('avatars/'.basename($avatar), 'static');
    }
}

if (! function_exists('millisecond')) {
    function millisecond(): int
    {
        return (int) (microtime(true) * 1000);
    }
}