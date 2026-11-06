<?php
/**
 * Power by readfly/laravel.
 * User: readfly <wucc513721@163.com>
 * Company: 达州市飞鸟网络科技有限公司
 */

declare(strict_types=1);

namespace App\Console\Commands\Application;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Readfly\Laravel\Support\File;

class UpdateCommand extends Command
{
    protected $signature = 'application:update {--refresh : 重建 fillings 迁移（会回滚该目录下的表）}';

    protected $description = '应用更新逻辑';

    public function handle(): int
    {
        set_time_limit(0);
        ini_set('memory_limit', '5124M');

        Artisan::call('cache:clear');
        Artisan::call('view:clear');
        Artisan::call('route:clear');

        $path = database_path('migrations/fillings');

        if (File::isDirectory($path)) {
            Artisan::call($this->option('refresh') ? 'migrate:refresh' : 'migrate', [
                '--path' => $path,
                '--force' => true,
            ]);
            $this->line(Artisan::output());
        } else {
            $this->warn('未找到 fillings 迁移目录：'.$path);
        }

        Artisan::call('config:cache');

        if (config('app.env', 'production') === 'production') {
            Artisan::call('route:cache');
            Artisan::call('view:cache');
        }

        $this->beforeHandle();
        $this->defaultData();

        $this->info('应用更新成功');

        return self::SUCCESS;
    }

    public function beforeHandle(): bool
    {
        return true;
    }

    public function defaultData(): bool
    {
        return true;
    }
}