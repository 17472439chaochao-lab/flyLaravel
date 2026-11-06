<?php

declare(strict_types=1);

namespace Readfly\Laravel\Console;

use Readfly\Laravel\Support\File;
use Readfly\Laravel\Support\Path;

final class StubRenderer
{
    public function __construct(protected string $basePath = '')
    {
        $this->basePath = $basePath !== '' ? $basePath : Path::package('resources/templates');
    }

    public function path(string $stub): string
    {
        return rtrim($this->basePath, '/\\').'/'.ltrim($stub, '/\\');
    }

    public function exists(string $stub): bool
    {
        return is_file($this->path($stub));
    }

    public function render(string $stub, array $data = []): string
    {
        $content = File::get($this->path($stub));

        if ($content === null) {
            return '';
        }

        foreach ($data as $key => $value) {
            if (is_array($value) || is_object($value)) {
                $data[$key] = '';
            }
        }

        $content = $this->renderConditionals($content, $data);

        foreach ($data as $key => $value) {
            $content = str_replace(
                ['{{ '.$key.' }}', '{{'.$key.'}}', '{__'.strtoupper((string) $key).'__}'],
                (string) $value,
                $content
            );
        }

        return $content;
    }

    public function write(string $path, string $content): bool
    {
        return File::put($path, $content);
    }

    protected function renderConditionals(string $content, array $data): string
    {
        $pattern = '/\{\{#(if|unless)\s+([A-Za-z0-9_.]+)\}\}(.*?)\{\{\/\1\}\}/s';

        return (string) preg_replace_callback($pattern, function (array $matches) use ($data): string {
            $value = data_get($data, $matches[2], false);
            $truthy = is_bool($value) ? $value : ($value !== null && $value !== '' && $value !== [] && $value !== 0 && $value !== '0');

            return $matches[1] === 'if' ? ($truthy ? $matches[3] : '') : ($truthy ? '' : $matches[3]);
        }, $content);
    }
}