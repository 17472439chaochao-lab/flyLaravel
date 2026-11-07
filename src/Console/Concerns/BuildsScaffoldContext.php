<?php

declare(strict_types=1);

namespace Readfly\Laravel\Console\Concerns;

use Illuminate\Support\Str;
use Readfly\Laravel\Console\FieldDefinition;
use Readfly\Laravel\Support\File;
use Readfly\Laravel\Support\Path;

trait BuildsScaffoldContext
{
    protected function templatePath(): string
    {
        $custom = config('toolkit.templates_path');

        if (is_string($custom) && trim($custom) !== '') {
            return $custom;
        }

        $published = function_exists('resource_path')
            ? resource_path('toolkit/templates')
            : Path::base('resources/toolkit/templates');

        if (is_dir($published)) {
            return $published;
        }

        return Path::package('resources/templates');
    }

    protected function authoringContext(): array
    {
        return [
            'date' => date('Y-m-d'),
            'time' => date('H:i:s'),
            'author' => (string) config('toolkit.author', 'readfly'),
            'author_email' => (string) config('toolkit.author_email', 'wucc513721@163.com'),
            'company' => (string) config('toolkit.company', '达州市飞鸟网络科技有限公司'),
        ];
    }

    protected function namespaceRoot(): string
    {
        return trim((string) config('toolkit.namespace.root', 'App'), '\\');
    }

    protected function namespaceBase(string $key, string $default): string
    {
        $base = trim((string) config('toolkit.namespace.'.$key, $default), '\\');
        $root = $this->namespaceRoot();

        if ($base === '') {
            $base = trim($default, '\\');
        }

        if ($root !== '' && $base === $root) {
            return '';
        }

        if ($root !== '' && str_starts_with($base, $root.'\\')) {
            $base = substr($base, strlen($root) + 1);
        }

        return $base;
    }

    protected function namespaceWith(string $key, string $default, string $dictionary): string
    {
        $segments = array_filter([
            $this->namespaceRoot(),
            $this->namespaceBase($key, $default),
            trim($dictionary, '\\'),
        ], static fn (string $segment): bool => $segment !== '');

        return implode('\\', $segments);
    }

    protected function appPathFor(string $key, string $default, string $dictionary, string $file): string
    {
        $relative = $this->namespaceBase($key, $default).($dictionary !== '' ? $dictionary : '');
        $relative = trim(str_replace('\\', '/', $relative), '/');

        return app_path(($relative !== '' ? $relative.'/' : '').ltrim($file, '/'));
    }

    protected function dictionarySegments(string $dictionary): array
    {
        $dictionary = trim($dictionary, '\\');

        if ($dictionary === '') {
            return [];
        }

        return array_values(array_filter(explode('\\', $dictionary), static fn (string $segment): bool => $segment !== ''));
    }

    protected function dictionarySlug(string $dictionary, string $separator = '/'): string
    {
        $segments = array_map(
            static fn (string $segment): string => Str::kebab($segment),
            $this->dictionarySegments($dictionary)
        );

        return implode($separator, array_filter($segments, static fn (string $segment): bool => $segment !== ''));
    }

    protected function dictionarySnake(string $dictionary): string
    {
        $segments = array_map(
            static fn (string $segment): string => Str::snake($segment),
            $this->dictionarySegments($dictionary)
        );

        return implode('_', array_filter($segments, static fn (string $segment): bool => $segment !== ''));
    }

    protected function routePath(string $name, string $dictionary, string $variant = ''): string
    {
        $directory = $this->dictionarySlug($dictionary);
        $filename = $name.($variant !== '' ? '.'.$variant : '').'.php';

        return base_path('routes/generated/'.($directory !== '' ? $directory.'/' : '').$filename);
    }

    protected function routeUri(string $name, string $dictionary): string
    {
        $prefix = $this->dictionarySlug($dictionary);

        return ($prefix !== '' ? $prefix.'/' : '').str_replace('_', '-', $name);
    }

    protected function routeName(string $name, string $dictionary, string $variant = ''): string
    {
        $prefix = str_replace('/', '.', $this->dictionarySlug($dictionary));
        $name = str_replace('_', '.', $name);

        return trim(($prefix !== '' ? $prefix.'.' : '').$name.($variant !== '' ? '.'.$variant : ''), '.');
    }

    protected function routePrefix(string $name, string $dictionary): string
    {
        $override = trim((string) ($this->option('prefix') ?: ''));
        $prefix = $override !== '' ? $override : $this->routeUri($name, $dictionary);
        $prefix = trim($prefix, '/');

        if ($prefix === '' || str_contains($prefix, '..') || ! preg_match('~^[A-Za-z0-9_./-]+$~', $prefix)) {
            throw new \InvalidArgumentException('非法路由前缀：'.$prefix);
        }

        return $prefix;
    }

    protected function routeNamePrefix(string $name, string $dictionary, string $variant = ''): string
    {
        $override = trim((string) ($this->option('route-name') ?: ''));
        $routeName = $override !== '' ? $override : $this->routeName($name, $dictionary, $variant);

        if ($override !== '' && $variant !== '' && ! str_ends_with($routeName, '.'.$variant)) {
            $routeName .= '.'.$variant;
        }

        $routeName = trim($routeName, '.');

        if ($routeName === '' || ! preg_match('/^[A-Za-z0-9_.-]+$/', $routeName)) {
            throw new \InvalidArgumentException('非法路由名称：'.$routeName);
        }

        return $routeName;
    }

    protected function routeMiddlewareCode(): string
    {
        $raw = trim((string) ($this->option('middleware') ?: ''));

        if ($raw === '') {
            return '';
        }

        $middleware = array_values(array_filter(array_map('trim', explode('|', $raw)), static fn (string $item): bool => $item !== ''));

        if ($middleware === []) {
            return '';
        }

        return '->middleware(['.implode(', ', array_map(static fn (string $item): string => var_export($item, true), $middleware)).'])';
    }

    protected function sanitizeDocblock(string $value): string
    {
        return str_replace('*/', '* /', $value);
    }

    protected function scaffoldFields(string $question = '是否现在定义表字段？'): array
    {
        $input = (string) ($this->option('fields') ?: '');
        $fieldsFile = trim((string) ($this->hasOption('fields-file') ? $this->option('fields-file') : ''));

        if ($fieldsFile !== '') {
            $fileContent = File::get($fieldsFile);

            if ($fileContent === null) {
                throw new \InvalidArgumentException('字段文件不存在或不可读：'.$fieldsFile);
            }

            $input = trim((string) preg_replace('/^\xEF\xBB\xBF/', '', $fileContent));
        }

        if ($input === '' && $this->input->isInteractive() && $this->confirmOrDefault($question, false)) {
            $input = (string) $this->ask('字段定义（name:string(64):名称;age:int:年龄=0）', '');
        }

        $trimmedInput = ltrim($input);

        $fields = str_starts_with($trimmedInput, '{') || str_starts_with($trimmedInput, '[')
            ? FieldDefinition::parseJson($trimmedInput)
            : FieldDefinition::parseList($input);

        unset($fields['id'], $fields['created_at'], $fields['updated_at']);

        return $fields;
    }

    protected function migrationFieldLines(array $fields): string
    {
        return implode("\n", array_map(
            static fn (FieldDefinition $field): string => '            '.$field->migrationLine(),
            array_values($fields)
        ));
    }

    protected function fillableLines(array $fields): string
    {
        return implode("\n", array_map(
            static fn (FieldDefinition $field): string => '        '.var_export($field->name, true).',',
            array_values($fields)
        ));
    }

    protected function castLines(array $fields): string
    {
        $lines = [];

        foreach ($fields as $field) {
            if ($field->cast() !== null) {
                $lines[] = '        '.var_export($field->name, true).' => '.$field->cast().',';
            }
        }

        return implode("\n", $lines);
    }

    protected function ruleLines(array $fields): string
    {
        $lines = [];

        foreach ($fields as $field) {
            $rules = array_map(
                static fn (string $rule): string => str_starts_with($rule, 'Rule::') ? $rule : var_export($rule, true),
                $field->rules()
            );
            $lines[] = '            '.var_export($field->name, true).' => ['.implode(', ', $rules).'],';
        }

        return implode("\n", $lines);
    }

    protected function hasEnum(array $fields): bool
    {
        foreach ($fields as $field) {
            if ($field->baseType() === 'enum') {
                return true;
            }
        }

        return false;
    }

    protected function attributeLines(array $fields): string
    {
        $lines = [];

        foreach ($fields as $field) {
            $lines[] = '            '.var_export($field->name, true).' => '.var_export($field->comment, true).',';
        }

        return implode("\n", $lines);
    }

    protected function wants(string $option, string $configKey, string $question, string $negativeOption = ''): bool
    {
        if ($negativeOption !== '' && $this->hasOption($negativeOption) && (bool) $this->option($negativeOption)) {
            return false;
        }

        if ((bool) $this->option($option)) {
            return true;
        }

        $default = (bool) config('toolkit.default_builder.'.$configKey, true);

        if (! $this->input->isInteractive()) {
            return $default;
        }

        return $this->confirmOrDefault($question, $default);
    }
}
