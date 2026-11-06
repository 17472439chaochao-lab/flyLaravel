<?php

declare(strict_types=1);

namespace Readfly\Laravel\Contracts;

interface SmsSender
{
    public function send(string $mobile, array $data = [], string $template = '', string $content = ''): bool;
}