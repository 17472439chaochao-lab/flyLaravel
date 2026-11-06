<?php

declare(strict_types=1);

namespace Readfly\Laravel\Console\Commands;

use Illuminate\Console\Command;
use Readfly\Laravel\Support\File;
use Readfly\Laravel\Support\Path;

class DoctorCommand extends Command
{
    protected $signature = 'toolkit:doctor';

    protected $description = '检查 Readfly Laravel Toolkit 运行环境与可选依赖';

    public function handle(): int
    {
        $checks = [
            ['PHP >= 8.1', version_compare(PHP_VERSION, '8.1.0', '>='), PHP_VERSION],
            ['ext-json', extension_loaded('json'), ''],
            ['ext-mbstring', extension_loaded('mbstring'), ''],
            ['ext-openssl', extension_loaded('openssl'), ''],
            ['ext-pdo', extension_loaded('pdo'), ''],
            ['config/toolkit.php', file_exists(config_path('toolkit.php')), 'php artisan vendor:publish --tag=toolkit-config'],
            ['config/project.php', file_exists(config_path('project.php')), 'php artisan vendor:publish --tag=toolkit-config'],
            ['storage 可写', is_writable(storage_path()), storage_path()],
            ['public/static/avatars', File::files(Path::public((string) config('toolkit.assets.avatars', 'static/avatars'))) !== [], 'php artisan vendor:publish --tag=toolkit-assets'],
            ['intervention/image', class_exists(\Intervention\Image\ImageManager::class), 'composer require intervention/image'],
            ['simple-qrcode', class_exists(\SimpleSoftwareIO\QrCode\Generator::class), 'composer require simplesoftwareio/simple-qrcode'],
            ['php-dfa-sensitive', class_exists(\DfaFilter\SensitiveHelper::class), 'composer require lustre/php-dfa-sensitive'],
            ['easy-sms', class_exists(\Overtrue\EasySms\EasySms::class), 'composer require overtrue/easy-sms'],
            ['aliyun-oss', class_exists(\OSS\OssClient::class), 'composer require aliyuncs/oss-sdk-php'],
            ['qiniu-sdk', class_exists(\Qiniu\Auth::class), 'composer require qiniu/php-sdk'],
        ];

        $rows = [];
        $failed = 0;

        foreach ($checks as [$name, $passed, $hint]) {
            if (! $passed) {
                $failed++;
            }
            $rows[] = [$name, $passed ? '<info>OK</info>' : '<error>MISSING</error>', $passed ? '' : $hint];
        }

        $this->table(['检查项', '状态', '修复建议'], $rows);

        if ($failed > 0) {
            $this->warn($failed.' 项检查未通过，其中可选依赖仅影响对应功能。');

            return self::FAILURE;
        }

        $this->info('环境检查全部通过。');

        return self::SUCCESS;
    }
}