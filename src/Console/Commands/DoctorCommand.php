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
            ['PHP >= 8.1', true, version_compare(PHP_VERSION, '8.1.0', '>='), PHP_VERSION],
            ['ext-json', true, extension_loaded('json'), ''],
            ['ext-mbstring', true, extension_loaded('mbstring'), ''],
            ['ext-openssl', true, extension_loaded('openssl'), ''],
            ['ext-pdo', true, extension_loaded('pdo'), ''],
            ['config/toolkit.php', true, file_exists(config_path('toolkit.php')), 'php artisan vendor:publish --tag=toolkit-config'],
            ['config/project.php', true, file_exists(config_path('project.php')), 'php artisan vendor:publish --tag=toolkit-config'],
            ['storage 可写', true, is_writable(storage_path()), storage_path()],
            ['public/static/avatars', true, File::files(Path::public((string) config('toolkit.assets.avatars', 'static/avatars'))) !== [], 'php artisan vendor:publish --tag=toolkit-assets'],
            ['intervention/image', false, class_exists(\Intervention\Image\ImageManager::class), 'composer require intervention/image'],
            ['simple-qrcode', false, class_exists(\SimpleSoftwareIO\QrCode\Generator::class), 'composer require simplesoftwareio/simple-qrcode'],
            ['php-dfa-sensitive', false, class_exists(\DfaFilter\SensitiveHelper::class), 'composer require lustre/php-dfa-sensitive'],
            ['easy-sms', false, class_exists(\Overtrue\EasySms\EasySms::class), 'composer require overtrue/easy-sms'],
            ['aliyun-oss', false, class_exists(\OSS\OssClient::class), 'composer require aliyuncs/oss-sdk-php'],
            ['qiniu-sdk', false, class_exists(\Qiniu\Auth::class), 'composer require qiniu/php-sdk'],
        ];

        $rows = [];
        $failed = 0;
        $optionalMissing = 0;

        foreach ($checks as [$name, $required, $passed, $hint]) {
            if (! $passed) {
                if ($required) {
                    $failed++;
                } else {
                    $optionalMissing++;
                }
            }

            $status = $passed ? '<info>OK</info>' : ($required ? '<error>MISSING</error>' : '<comment>OPTIONAL</comment>');
            $rows[] = [$name, $status, $passed ? '' : $hint];
        }

        $this->table(['检查项', '状态', '修复建议'], $rows);

        if ($failed > 0) {
            $this->warn($failed.' 项必需检查未通过。');

            return self::FAILURE;
        }

        if ($optionalMissing > 0) {
            $this->warn($optionalMissing.' 项可选依赖未安装，仅影响对应功能。');
        }

        $this->info('环境检查通过。');

        return self::SUCCESS;
    }
}
