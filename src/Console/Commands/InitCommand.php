<?php

declare(strict_types=1);

namespace Readfly\Laravel\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use Readfly\Laravel\Support\File;
use Throwable;

class InitCommand extends Command
{
    protected $signature = 'builder:laravel
        {--domain= : 项目默认域名，例如 api.example.com}
        {--force : 强制重新生成密钥}
        {--no-link : 跳过 storage:link}';

    protected $description = '初始化 Readfly Laravel Toolkit 配置（首次引入时执行）';

    public function __construct(protected Repository $config)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $projectPath = config_path('project.php');
        $toolkitPath = config_path('toolkit.php');

        if (! file_exists($projectPath) || ! file_exists($toolkitPath)) {
            $this->error('配置文件尚未发布，请先执行：php artisan vendor:publish --tag=toolkit-config');

            return self::FAILURE;
        }

        $domain = (string) ($this->option('domain') ?: $this->askOrDefault('请输入当前项目默认域名，如：domain.com', 'domain.com'));
        $force = (bool) $this->option('force');

        $this->prepareProjectConfig($projectPath, $domain, $force);
        $this->prepareToolkitConfig($toolkitPath);

        if (! $this->option('no-link')) {
            try {
                Artisan::call('storage:link');
                $this->info('已执行 storage:link');
            } catch (Throwable $exception) {
                $this->warn('storage:link 执行失败：'.$exception->getMessage());
            }
        }

        File::makeDirectory(app_path('Helpers'));
        $this->info('初始化完成。');

        return self::SUCCESS;
    }

    protected function prepareProjectConfig(string $path, string $domain, bool $force): void
    {
        $content = (string) file_get_contents($path);

        if (! $force && ! Str::contains($content, ['__APP_KEY__', '__APP_SECRET__', '__AES_IV__', '__AES_ENCRYPT_KEY__', 'domain.com'])) {
            $this->warn('project.php 已初始化，跳过。如需重新生成密钥请加 --force');

            return;
        }

        $replacements = [
            '// __DEFAULT_USE_USAGE__' => '',
            'domain.com' => $domain,
            '__APP_KEY__' => 'AK'.date('Ymd').Str::upper(Str::random(10)),
            '__APP_SECRET__' => Str::upper(hash('sha256', Str::uuid()->toString().Str::random(16))),
            '__AES_IV__' => Str::upper(Str::random(16)),
            '__AES_ENCRYPT_KEY__' => Str::upper(Str::random(32)),
        ];

        File::put($path, str_replace(array_keys($replacements), array_values($replacements), $content));
        $this->info('已写入签名与 AES 密钥：config/project.php');
    }

    protected function prepareToolkitConfig(string $path): void
    {
        $content = (string) file_get_contents($path);

        $replacements = [
            '__DEFAULT_DATABASE_CONNECTION__' => (string) $this->config->get('database.default', 'mysql'),
            '__DEFAULT_CACHE_DRIVER__' => (string) $this->config->get('cache.default', 'file'),
            '__APP_VERSION__' => (string) random_int(10000, 99999),
        ];

        File::put($path, str_replace(array_keys($replacements), array_values($replacements), $content));
    }
}