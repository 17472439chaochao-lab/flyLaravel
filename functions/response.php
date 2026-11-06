<?php

use Illuminate\Http\JsonResponse;
use Readfly\Laravel\Http\ApiResponse;
use Readfly\Laravel\Modules\ServiceModule;
use Readfly\Laravel\Support\Code;
use Readfly\Laravel\Support\ResponsePayload;

if (! function_exists('responseService')) {
    function responseService(ServiceModule|ResponsePayload $service, string $format = 'json', string $callback = ''): JsonResponse
    {
        $payload = $service instanceof ServiceModule ? $service->getResponse() : $service;

        return ApiResponse::fromPayload($payload, $format, $callback);
    }
}

if (! function_exists('responseError')) {
    function responseError(int $code, array $data = [], string $msg = '', array $extras = [], string $format = 'json', string $callback = ''): JsonResponse
    {
        if (data_get($extras, 'validations', false)) {
            $code = Code::VALIDATE_FAILED;
        }

        return ApiResponse::error($code, $msg, $data, $extras, $format, $callback);
    }
}

if (! function_exists('responseSuccess')) {
    function responseSuccess(mixed $data = [], string $msg = '', array $extras = [], string $format = 'json', string $callback = ''): JsonResponse
    {
        return ApiResponse::success($data, $msg, $extras, $format, $callback);
    }
}

if (! function_exists('responseReturn')) {
    function responseReturn(int $code, string $msg, mixed $data, array $extras = [], string $format = 'json', string $callback = ''): JsonResponse
    {
        return ApiResponse::build($code, $msg, $data, $extras, $format, $callback);
    }
}