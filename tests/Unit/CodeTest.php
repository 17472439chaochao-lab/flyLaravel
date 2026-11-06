<?php

declare(strict_types=1);

namespace Readfly\Laravel\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Readfly\Laravel\Support\Code;

class CodeTest extends TestCase
{
    public function test_business_codes_are_unique(): void
    {
        $codes = [
            Code::SUCCESS,
            Code::ERROR,
            Code::LOGIC_ERROR,
            Code::VALIDATE_FAILED,
            Code::UNAUTHORIZED,
            Code::FORBIDDEN,
            Code::NOT_FOUND,
            Code::METHOD_NOT_ALLOWED,
            Code::TOO_MANY_REQUESTS,
            Code::SERVER_ERROR,
            Code::SERVICE_UNAVAILABLE,
        ];

        $this->assertCount(count($codes), array_unique($codes));
    }

    public function test_message_fallback(): void
    {
        $this->assertSame('参数校验失败', Code::message(Code::VALIDATE_FAILED));
        $this->assertSame('[ERROR:9999]', Code::message(9999));
        $this->assertSame('custom', Code::message(9999, 'custom'));
    }

    public function test_http_status_mapping(): void
    {
        $this->assertSame(401, Code::httpStatus(Code::UNAUTHORIZED));
        $this->assertSame(422, Code::httpStatus(Code::VALIDATE_FAILED));
        $this->assertSame(500, Code::httpStatus(Code::SERVER_ERROR));
        $this->assertSame(200, Code::httpStatus(Code::ERROR));
    }
}