<?php

declare(strict_types=1);

namespace Readfly\Laravel\Support;

final class Code
{
    public const SUCCESS = 0;
    public const ERROR = 1000;
    public const LOGIC_ERROR = 1001;
    public const VALIDATE_FAILED = 1002;
    public const UNAUTHORIZED = 1003;
    public const FORBIDDEN = 1004;
    public const NOT_FOUND = 1005;
    public const METHOD_NOT_ALLOWED = 1006;
    public const TOO_MANY_REQUESTS = 1007;
    public const SERVER_ERROR = 1500;
    public const SERVICE_UNAVAILABLE = 1503;

    public const CODE_SUCCESS = self::SUCCESS;
    public const CODE_ERROR = self::ERROR;
    public const CODE_LOGIC_ERROR = self::LOGIC_ERROR;
    public const CODE_VALIDATE_FAILED = self::VALIDATE_FAILED;
    public const CODE_SERVER_ERROR = self::SERVER_ERROR;

    private const MESSAGES = [
        self::SUCCESS => '处理成功',
        self::ERROR => '处理失败',
        self::LOGIC_ERROR => '逻辑处理异常',
        self::VALIDATE_FAILED => '参数校验失败',
        self::UNAUTHORIZED => '未授权访问',
        self::FORBIDDEN => '禁止访问',
        self::NOT_FOUND => '资源不存在',
        self::METHOD_NOT_ALLOWED => '请求方式不允许',
        self::TOO_MANY_REQUESTS => '请求过于频繁',
        self::SERVER_ERROR => '服务器内部错误',
        self::SERVICE_UNAVAILABLE => '服务暂不可用',
    ];

    public static function message(int $code, string $default = ''): string
    {
        return self::MESSAGES[$code] ?? ($default !== '' ? $default : '[ERROR:'.$code.']');
    }

    public static function httpStatus(int $code): int
    {
        return match ($code) {
            self::SUCCESS => 200,
            self::UNAUTHORIZED => 401,
            self::FORBIDDEN => 403,
            self::NOT_FOUND => 404,
            self::METHOD_NOT_ALLOWED => 405,
            self::VALIDATE_FAILED => 422,
            self::TOO_MANY_REQUESTS => 429,
            self::SERVER_ERROR => 500,
            self::SERVICE_UNAVAILABLE => 503,
            default => 200,
        };
    }
}