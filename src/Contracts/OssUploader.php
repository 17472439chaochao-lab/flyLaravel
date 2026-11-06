<?php

declare(strict_types=1);

namespace Readfly\Laravel\Contracts;

interface OssUploader
{
    public function put(string $path, mixed $content, array $options = []): bool;

    public function putFile(string $path, string $local_path, array $options = []): bool;

    public function delete(string $path): bool;

    public function exists(string $path): bool;

    public function url(string $path, int $expires = 0): string;
}