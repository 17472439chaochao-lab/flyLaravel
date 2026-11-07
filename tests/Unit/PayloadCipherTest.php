<?php

declare(strict_types=1);

namespace Readfly\Laravel\Tests\Unit;

use Illuminate\Container\Container;
use PHPUnit\Framework\TestCase;
use Readfly\Laravel\Support\PayloadCipher;
use Readfly\Laravel\Tests\Unit\Support\TestApplication;
use Readfly\Laravel\Tests\Unit\Support\TestConfigRepository;
use RuntimeException;

class PayloadCipherTest extends TestCase
{
    protected function setUp(): void
    {
        $app = new TestApplication();
        Container::setInstance($app);
        $app->instance('config', new TestConfigRepository([
            'toolkit' => ['payload_encryption' => ['enabled' => true, 'header' => 'X-Payload-Encrypted', 'key' => 'test-payload-key']],
        ]));
    }

    protected function tearDown(): void
    {
        Container::setInstance(null);
    }

    public function test_encrypt_and_decrypt_round_trip(): void
    {
        $plain = '{"name":"Readfly","message":"中文 payload"}';
        $encoded = PayloadCipher::encrypt($plain);

        $this->assertNotSame($plain, $encoded);
        $this->assertSame($plain, PayloadCipher::decrypt($encoded));
    }

    public function test_tampered_payload_is_rejected(): void
    {
        $encoded = PayloadCipher::encrypt('{"a":1}');
        $raw = base64_decode($encoded, true);
        $raw[strlen($raw) - 1] = chr(ord($raw[strlen($raw) - 1]) ^ 1);

        $this->expectException(RuntimeException::class);

        PayloadCipher::decrypt(base64_encode($raw));
    }
}
