<?php

declare(strict_types=1);

namespace Readfly\Laravel\Console;

use Readfly\Laravel\Support\File;
use Readfly\Laravel\Support\Path;
use RuntimeException;

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
        $path = $this->path($stub);
        $content = File::get($path);

        if ($content === null) {
            throw new RuntimeException('模板不存在或不可读：'.$path);
        }

        $content = $this->renderConditionals($content, $data);

        $missing = new \stdClass();

        $content = (string) preg_replace_callback(
            '/\{\{\s*([A-Za-z0-9_.]+)\s*\}\}|\{__([A-Z0-9_]+)__\}/',
            static function (array $matches) use ($data, $missing): string {
                $key = $matches[1] !== '' ? $matches[1] : strtolower($matches[2]);
                $value = data_get($data, $key, $missing);

                if ($value === $missing) {
                    return $matches[0];
                }

                return is_scalar($value) || $value === null ? (string) $value : '';
            },
            $content
        );

        $content = (string) preg_replace('/[ \t]+\n/', "\n", $content);
        $content = (string) preg_replace('/\n{3,}/', "\n\n", $content);

        if (trim($content) === '') {
            throw new RuntimeException('模板 ['.$stub.'] 渲染结果为空。');
        }

        if (preg_match('/\{\{.*?\}\}|\{__[A-Z0-9_]+__\}/s', $content, $matches) === 1) {
            throw new RuntimeException('模板 ['.$stub.'] 存在未渲染占位符：'.$matches[0]);
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
            $boolean = is_bool($value)
                ? $value
                : (is_string($value) ? filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) : null);
            $truthy = $boolean ?? ($value !== null && $value !== '' && $value !== [] && $value !== 0 && $value !== '0');

            return $matches[1] === 'if' ? ($truthy ? $matches[3] : '') : ($truthy ? '' : $matches[3]);
        }, $content);
    }
}
