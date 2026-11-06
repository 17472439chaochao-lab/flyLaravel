<?php

declare(strict_types=1);

namespace Readfly\Laravel\Console\Concerns;

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

        return $this->confirmOrDefault('文件 ['.$path.'] 已存在，是否覆盖写入？', false);
    }

    protected function writeFile(string $path, string $content): bool
    {
        if (! File::put($path, $content)) {
            $this->error('文件写入失败：'.$path);

            return false;
        }

        $this->info('已生成：'.str_replace(base_path().'/', '', $path));

        return true;
    }

    protected function normalizeDictionary(string $dictionary): string
    {
        $dictionary = trim($dictionary);

        if ($dictionary === '') {
            return '';
        }

        $segments = array_filter(array_map(static function (string $segment): string {
            return \Illuminate\Support\Str::studly(str_replace(['/', '-'], '_', trim($segment)));
        }, preg_split('/[\\\\\/]+/', $dictionary) ?: []));

        return $segments === [] ? '' : '\\'.implode('\\', $segments);
    }

    protected function currentTimestamp(): array
    {
        return [date('Y-m-d'), date('H:i:s')];
    }
}