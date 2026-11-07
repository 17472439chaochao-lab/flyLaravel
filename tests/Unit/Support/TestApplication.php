<?php

declare(strict_types=1);

namespace Readfly\Laravel\Tests\Unit\Support;

use Illuminate\Container\Container;

final class TestApplication extends Container
{
    public function runningUnitTests(): bool
    {
        return true;
    }
}
