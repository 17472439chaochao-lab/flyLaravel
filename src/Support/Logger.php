<?php

declare(strict_types=1);

namespace Readfly\Laravel\Support;

use Throwable;

final class Logger
{
    public static function record(string $channel, mixed $payload, string $level = 'error'): void
    {
        try {
            if (function_exists('logger')) {
                logger()->{$level}($channel, self::normalize($payload));

                return;
            }
        } catch (Throwable) {
        }

        self::fallback($channel, $payload, $level);
    }

    private static function normalize(mixed $payload): array
    {
        $value = self::normalizeValue($payload);

        return is_array($value) ? $value : ['payload' => $value];
    }

    private static function normalizeValue(mixed $payload): mixed
    {
        if (is_array($payload)) {
            return array_map(static fn (mixed $value): mixed => self::normalizeValue($value), $payload);
        }

        if ($payload instanceof Throwable) {
            return [
                'message' => $payload->getMessage(),
                'code' => $payload->getCode(),
                'file' => $payload->getFile(),
                'line' => $payload->getLine(),
            ];
        }

        if (is_object($payload) && method_exists($payload, 'toArray')) {
            return (array) $payload->toArray();
        }

        return $payload;
    }

    private static function fallback(string $channel, mixed $payload, string $level): void
    {
        $directory = Path::logs();
        File::makeDirectory($directory);
        $line = sprintf(
            "[%s] %s.%s %s\n",
            date('Y-m-d H:i:s'),
            $level,
            $channel,
            json_encode(self::normalize($payload), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );
        @file_put_contents(Path::logs('toolkit.log'), $line, FILE_APPEND | LOCK_EX);
    }
}