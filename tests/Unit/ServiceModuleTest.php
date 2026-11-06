<?php

declare(strict_types=1);

namespace Readfly\Laravel\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Readfly\Laravel\Modules\ServiceModule;

class TestServiceModule extends ServiceModule
{
    public function ok(mixed $data): bool
    {
        return $this->success($data);
    }

    public function err(int $code): bool
    {
        return $this->fail($code, 'failed');
    }
}

class ServiceModuleTest extends TestCase
{
    public function test_default_state_is_error(): void
    {
        $service = new TestServiceModule();

        $this->assertFalse($service->getState());
        $this->assertSame('LOGIC ERROR', $service->getMessage());
    }

    public function test_success_payload(): void
    {
        $service = new TestServiceModule();
        $service->ok(['id' => 1]);

        $this->assertTrue($service->getState());
        $this->assertSame(['id' => 1], $service->getResult());
        $this->assertSame(['extra' => 2, 'id' => 1], $service->getResult(['extra' => 2]));
        $this->assertSame(0, $service->getCode());
    }

    public function test_failure_payload(): void
    {
        $service = new TestServiceModule();
        $service->err(1234);

        $this->assertFalse($service->getState());
        $this->assertSame(1234, $service->getCode());
        $this->assertSame('failed', $service->getMessage());
    }
}