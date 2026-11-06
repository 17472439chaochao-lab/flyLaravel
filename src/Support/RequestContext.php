<?php

declare(strict_types=1);

namespace Readfly\Laravel\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Str;

final class RequestContext
{
    public const ATTRIBUTE_START = '__toolkit_start_time__';
    public const ATTRIBUTE_START_MS = '__LOGIC_REQUEST_LOG_TIME__';
    public const ATTRIBUTE_REQUEST_ID = '__toolkit_request_id__';

    public static function start(Request $request): void
    {
        $start = microtime(true);
        $request->attributes->set(self::ATTRIBUTE_START, $start);
        $request->attributes->set(self::ATTRIBUTE_START_MS, (int) ($start * 1000));
    }

    public static function requestId(Request $request): string
    {
        $existing = $request->attributes->get(self::ATTRIBUTE_REQUEST_ID);
        if (is_string($existing) && $existing !== '') {
            return $existing;
        }

        $header = (string) config('toolkit.middleware.request_id.header', 'X-Request-Id');
        $id = (string) ($request->header($header) ?: Str::uuid()->toString());
        $request->attributes->set(self::ATTRIBUTE_REQUEST_ID, $id);

        return $id;
    }

    public static function elapsedMs(Request $request): float
    {
        $start = $request->attributes->get(self::ATTRIBUTE_START);

        if (! is_float($start) && ! is_int($start)) {
            return 0.0;
        }

        return round((microtime(true) - $start) * 1000, 2);
    }

    public static function consuming(Request $request): string
    {
        return self::elapsedMs($request).'ms';
    }
}