<?php

declare(strict_types=1);

namespace Readfly\Laravel\Console\Concerns;

use Illuminate\Support\Str;
use InvalidArgumentException;
use Readfly\Laravel\Support\File;

trait InteractsWithScaffolding
{
    protected function askOrDefault(string $question, mixed $default = null): mixed
    {
        if (! $this->input->isInteractive()) {
            return $default;
        }

        $answer = $this->output->ask($question, $default);

        return $answer === null || $answer === '' ? $default : $answer;
    }

    protected function confirmOrDefault(string $question, bool $default = true): bool
    {
        if (! $this->input->isInteractive()) {
            return $default;
        }

        return (bool) $this->confirm($question, $default);
    }

    protected function shouldOverwrite(string $path, bool $force): bool
    {
        if (! file_exists($path)) {
            return true;
        }

        if ($force) {
            return true;
        }

        if (! $this->confirmOrDefault('文件 ['.$path.'] 已存在，是否覆盖写入？', false)) {
            $this->warn('已跳过：'.$this->relativePath($path));

            return false;
        }

        return true;
    }

    protected function writeFile(string $path, string $content): bool
    {
        if ($content === '') {
            $this->error('拒绝写入空文件：'.$path);

            return false;
        }

        if (is_file($path)) {
            $existing = File::get($path);

            if ($existing === $content) {
                $this->line('未变化：'.$this->relativePath($path));

                return true;
            }

            $backup = $path.'.'.date('YmdHis').'.bak';
            $counter = 1;

            while (file_exists($backup)) {
                $backup = $path.'.'.date('YmdHis').'_'.$counter++.'.bak';
            }

            if (! File::copy($path, $backup)) {
                $this->error('原文件备份失败，已中止写入：'.$path);

                return false;
            }

            $this->warn('已备份原文件：'.$this->relativePath($backup));
        }

        $directory = dirname($path);
        File::makeDirectory($directory);

        $temporary = tempnam($directory, '.toolkit-');

        if ($temporary !== false) {
            $written = file_put_contents($temporary, $content, LOCK_EX);

            if ($written !== false && @rename($temporary, $path)) {
                @chmod($path, 0644);
                $this->info('已生成：'.$this->relativePath($path));

                return true;
            }

            @unlink($temporary);
        }

        if (! File::put($path, $content, true)) {
            $this->error('文件写入失败：'.$path);

            return false;
        }

        $this->info('已生成：'.$this->relativePath($path));

        return true;
    }

    protected function registerRouteFile(string $routePath): bool
    {
        if (! is_file($routePath)) {
            $this->error('路由文件不存在，无法注册：'.$this->relativePath($routePath));

            return false;
        }

        $apiPath = base_path('routes/api.php');

        if (! is_file($apiPath)) {
            $this->warn('未找到 routes/api.php，请手动注册路由：'.$this->relativePath($routePath));

            return false;
        }

        $content = File::get($apiPath);

        if ($content === null) {
            $this->error('路由文件读取失败：'.$apiPath);

            return false;
        }

        $statement = 'require base_path(\''.$this->relativePath($routePath).'\');';

        if (str_contains($content, $statement)) {
            $this->line('路由已注册：'.$this->relativePath($routePath));

            return true;
        }

        if (! $this->writeFile($apiPath, rtrim($content)."\n\n".$statement."\n")) {
            return false;
        }

        $this->info('已注册路由：'.$this->relativePath($routePath));

        return true;
    }

    protected function normalizeDictionary(string $dictionary): string
    {
        $dictionary = trim($dictionary);

        if ($dictionary === '') {
            return '';
        }

        $segments = [];

        foreach (preg_split('/[\\\\\/]+/', $dictionary) ?: [] as $segment) {
            $segment = trim($segment);

            if ($segment === '') {
                continue;
            }

            $normalized = Str::studly(str_replace('-', '_', $segment));

            if ($normalized === '' || ! preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $normalized)) {
                throw new InvalidArgumentException('非法目录片段：'.$segment);
            }

            $segments[] = $normalized;
        }

        return $segments === [] ? '' : '\\'.implode('\\', $segments);
    }

    protected function validateCodeToken(string $value, string $label): string
    {
        $value = trim($value);

        if ($value === '' || ! preg_match('/^[A-Za-z0-9_][A-Za-z0-9_.-]*$/', $value)) {
            throw new InvalidArgumentException('非法'.$label.'：'.$value);
        }

        return $value;
    }

    protected function relativePath(string $path): string
    {
        $base = function_exists('base_path') ? base_path() : (string) getcwd();

        return str_replace($base.'/', '', $path);
    }
}
