<?php
/**
 * Power by readfly/laravel.
 * User: readfly <wucc513721@163.com>
 * Company: 达州市飞鸟网络科技有限公司
 */

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;

class TestCommand extends Command
{
    protected $signature = 'test:test';

    protected $description = '系统测试（开发时使用）';

    public function handle(): int
    {
        $this->info('测试完毕');

        return self::SUCCESS;
    }
}