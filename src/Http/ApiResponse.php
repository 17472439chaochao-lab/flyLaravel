<?php

declare(strict_types=1);

namespace Readfly\Laravel\Http;

use Illuminate\Http\JsonResponse;
use Readfly\Laravel\Support\Code;
use Readfly\Laravel\Support\RequestContext;
use Readfly\Laravel\Support\ResponsePayload;

final class ApiResponse
{
    public static function fromPayload(ResponsePayload $payload, string $format = 'json', string $callback = ''): JsonResponse
    {
        $result = self::payload($payload->state, $payload->code, $payload->message, $payload->data, $payload->extras);

        return self::respond($result, $format, $callback);
    }

    public static function success(mixed $data = [], string $msg = '', array $extras = [], string $format = 'json', string $callback = ''): JsonResponse
    {
        return self::build(Code::SUCCESS, $msg !== '' ? $msg : Code::message(Code::SUCCESS), $data, $extras, $format, $callback);
    }

    public static function error(int $code = Code::ERROR, string $msg = '', mixed $data = [], array $extras = [], string $format = 'json', string $callback = ''): JsonResponse
    {
        return self::build($code, $msg !== '' ? $msg : Code::message($code), $data, $extras, $format, $callback);
    }

    public static function build(int $code, string $msg, mixed $data = [], array $extras = [], string $format = 'json', string $callback = ''): JsonResponse
    {
        $state = $code === Code::SUCCESS;
        $result = self::payload($state, $code, $msg, $data, $extras);

        return self::respond($result, $format, $callback);
    }

    protected static function payload(bool $state, int $code, string $msg, mixed $data, array $extras): array
    {
        $result = [
            'state' => $state,
            'code' => $code,
            'msg' => $msg,
            'data' => $data,
            'extras' => $extras,
        ];

        if ((bool) config('toolkit.response.include_locale', true)) {
            $result['locale'] = config('app.locale');
        }

        if ((bool) config('toolkit.response.include_consuming', true) && function_exists('request')) {
            $result['consuming'] = RequestContext::consuming(request());
        }

        if ((bool) config('toolkit.response.include_request_id', true) && function_exists('request')) {
            $result['request_id'] = request()->attributes->get(RequestContext::ATTRIBUTE_REQUEST_ID);
        }

        return $result;
    }

    protected static function respond(array $result, string $format, string $callback): JsonResponse
    {
        $callback = trim($callback);

        if ($format === 'jsonp' && $callback !== '') {
            return response()->jsonp($callback, $result);
        }

        return response()->json($result);
    }
}