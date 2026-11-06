<?php

declare(strict_types=1);

namespace Readfly\Laravel\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Readfly\Laravel\Console\Concerns\BuildsScaffoldContext;
use Readfly\Laravel\Console\Concerns\InteractsWithScaffolding;
use Readfly\Laravel\Console\StubRenderer;
use Readfly\Laravel\Support\File;

class PackageCommand extends Command
{
    use BuildsScaffoldContext;
    use InteractsWithScaffolding;

    protected $signature = 'builder:package
        {name : 表名，不含表前缀}
        {--desc= : 表注释，如：用户信息表}
        {--dictionary= : 目录，多层级请用 \\ 或 / 分隔}
        {--dp= : 表前缀}
        {--dc= : 数据库连接}
        {--dcs= : 表字符集}
        {--de= : 表引擎}
        {--cd= : 缓存驱动}
        {--fields= : 字段定义，如 name:string(64):名称;age:int:年龄=0}
        {--index= : 普通索引字段，逗号分隔}
        {--unique= : 唯一索引字段，逗号分隔}
        {--migration : 生成迁移文件}
        {--cache : 生成数据缓存}
        {--controller : 生成控制器}
        {--request : 生成请求验证类}
        {--route : 生成路由片段}
        {--fcp : 覆盖已存在的文件}
        {--force : 等同于 --fcp}';

    protected $description = '生成业务分层脚手架（Model / Repository / Service / Hook / Cacher / Controller / Request / Migration / Route）';

    public function handle(): int
    {
        $name = trim((string) $this->argument('name'));

        if ($name === '' || ! preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $name)) {
            $this->error('表名必须由字母、数字、下划线组成。');

            return self::FAILURE;
        }

        $context = $this->buildContext($name);
        $renderer = new StubRenderer($this->templatePath());
        $written = [];

        $files = [
            ['model.tpl', $context['model_path']],
            ['repository.tpl', $context['repository_path']],
            ['service.tpl', $context['service_path']],
            ['hook.tpl', $context['hook_path']],
        ];

        if ($context['has_cache']) {
            $files[] = ['cacher.tpl', $context['cache_path']];
        }

        if ($context['has_controller']) {
            $files[] = ['controller.tpl', $context['controller_path']];
        }

        if ($context['has_request']) {
            $files[] = ['request.tpl', $context['request_path']];
        }

        foreach ($files as [$stub, $path]) {
            if (! $renderer->exists($stub)) {
                $this->error('缺少模板：'.$renderer->path($stub));

                return self::FAILURE;
            }

            if (! $this->shouldOverwrite($path, (bool) ($this->option('fcp') || $this->option('force')))) {
                continue;
            }

            File::makeDirectory(dirname($path));

            if ($this->writeFile($path, $renderer->render($stub, $context))) {
                $written[] = $path;
            }
        }

        if ($context['has_migration']) {
            $migration = $this->writeMigration($renderer, $context);

            if ($migration !== null) {
                $written[] = $migration;
            }
        }

        if ($context['has_route']) {
            $route = $this->writeRoute($renderer, $context);

            if ($route !== null) {
                $written[] = $route;
            }
        }

        $this->newLine();
        $this->info('已生成 '.count($written).' 个文件，表：'.$context['table_name']);

        if ($context['has_route']) {
            $this->warn('路由片段需手动引入：require base_path(\''.$this->relative($context['route_path']).'\');');
        }

        return self::SUCCESS;
    }

    protected function buildContext(string $name): array
    {
        $desc = (string) ($this->option('desc') ?: $this->askOrDefault('请输入表注释名称（以“表”字结尾，例如：用户信息表）', Str::headline($name).'表'));
        $dictionary = $this->normalizeDictionary((string) ($this->option('dictionary') ?: $this->askOrDefault('目录名称（多层级用 \\ 或 / 分隔，留空表示根目录）', '')));

        $prefix = (string) ($this->option('dp') ?: config('toolkit.database_prefix', 's_'));
        $prefix = $prefix === '' ? '' : rtrim($prefix, '_').'_';

        $connection = (string) ($this->option('dc') ?: $this->defaultConnection());
        $charset = (string) ($this->option('dcs') ?: config('toolkit.database_charset', 'utf8mb4'));
        $engine = (string) ($this->option('de') ?: config('toolkit.database_engine', 'innodb'));
        $cacheDriver = (string) ($this->option('cd') ?: config('cache.default', 'file'));
        $has_route = (bool) $this->option('route');

        $fields = $this->scaffoldFields();

        $caseName = Str::studly(Str::singular($name));
        $dataName = trim((string) preg_replace('/表$/u', '', $desc));
        $dataName = $dataName !== '' ? $dataName : $desc;

        $root = trim((string) config('toolkit.namespace.root', 'App'), '\\');
        $namespaces = $this->namespaces($root, $dictionary);

        $tableName = $prefix.$name;
        $cacheName = ($dictionary !== '' ? strtolower(str_replace('\\', ':', trim($dictionary, '\\'))).':' : '').$name;

        $fieldLines = $this->migrationFieldLines($fields);

        $paths = $this->paths($dictionary, $caseName, $name);

        return array_merge($namespaces, $this->authoringContext(), [
            'repository_class' => $caseName.'Repository',
            'service_class' => $caseName.'Service',
            'hook_class' => $caseName.'Hook',
            'cache_class' => $caseName.'Cacher',
            'controller_class' => $caseName.'Controller',
            'request_class' => $caseName.'Request',
            'dictionary' => $dictionary,
            'table_name' => $tableName,
            'table_comment' => $desc,
            'data_name' => $dataName,
            'case_name' => $caseName,
            'db_prefix' => $prefix,
            'db_connection' => $connection,
            'charset' => $charset,
            'engine' => $engine,
            'fields_code' => $fieldLines !== '' ? $fieldLines : '            // 使用 --fields 生成字段，或在此手动补充',
            'has_fields' => $fields !== [],
            'fillable_code' => $this->fillableLines($fields),
            'casts_code' => $this->castLines($fields),
            'attributes_code' => $this->attributeLines($fields),
            'rules_code' => $this->ruleLines($fields),
            'index_code' => $this->indexCode(),
            'cache_name' => $cacheName,
            'cache_expire' => random_int(3600, 86400),
            'cache_driver' => $cacheDriver,
            'has_cache' => $this->wants('cache', 'data_cache', '是否生成数据缓存文件？'),
            'has_controller' => $has_route || $this->wants('controller', 'controller', '是否生成控制器？'),
            'has_request' => $this->wants('request', 'request', '是否生成请求验证类？'),
            'has_migration' => $this->wants('migration', 'migration', '是否生成数据迁移（Migration）文件？'),
            'has_route' => $has_route,
            'route_uri' => str_replace('_', '-', $name),
            'route_name' => str_replace('_', '.', $name),
        ], $paths);
    }

    protected function namespaces(string $root, string $dictionary): array
    {
        $join = static fn (string $segment): string => trim($root.'\\'.$segment.str_replace('/', '\\', $dictionary), '\\');

        return [
            'model_namespace' => $join('Models'),
            'repository_namespace' => $join('Repository'),
            'service_namespace' => $join('Services'),
            'hook_namespace' => $join('Hooks'),
            'cache_namespace' => $join('Caches'),
            'controller_namespace' => $join('Http\\Controllers'),
            'request_namespace' => $join('Http\\Requests'),
        ];
    }

    protected function paths(string $dictionary, string $caseName, string $name): array
    {
        $suffix = str_replace('\\', '/', $dictionary);

        return [
            'model_path' => app_path('Models'.$suffix.'/'.$caseName.'.php'),
            'repository_path' => app_path('Repository'.$suffix.'/'.$caseName.'Repository.php'),
            'service_path' => app_path('Services'.$suffix.'/'.$caseName.'Service.php'),
            'hook_path' => app_path('Hooks'.$suffix.'/'.$caseName.'Hook.php'),
            'cache_path' => app_path('Caches'.$suffix.'/'.$caseName.'Cacher.php'),
            'controller_path' => app_path('Http/Controllers'.$suffix.'/'.$caseName.'Controller.php'),
            'request_path' => app_path('Http/Requests'.$suffix.'/'.$caseName.'Request.php'),
            'route_path' => base_path('routes/generated/'.$name.'.php'),
        ];
    }

    protected function writeMigration(StubRenderer $renderer, array $context): ?string
    {
        $dictionary = trim((string) $context['dictionary'], '\\');
        $directory = database_path('migrations'.($dictionary !== '' ? '/'.strtolower(Str::before($dictionary, '\\')) : ''));
        File::makeDirectory($directory);

        $base = ($dictionary !== '' ? Str::snake(str_replace('\\', '_', $dictionary)).'_' : '').$context['case_name'];
        $timestamp = date('Y_m_d_His');
        $path = $directory.'/'.$timestamp.'_create_'.Str::snake($base).'_table.php';
        $counter = 1;

        while (file_exists($path)) {
            $path = $directory.'/'.$timestamp.'_create_'.Str::snake($base).'_table_'.$counter++.'.php';
        }

        if (! $this->writeFile($path, $renderer->render('migration.tpl', $context))) {
            return null;
        }

        return $path;
    }

    protected function writeRoute(StubRenderer $renderer, array $context): ?string
    {
        File::makeDirectory(dirname($context['route_path']));

        if (! $this->shouldOverwrite($context['route_path'], (bool) ($this->option('fcp') || $this->option('force')))) {
            return null;
        }

        return $this->writeFile($context['route_path'], $renderer->render('route.tpl', $context))
            ? $context['route_path']
            : null;
    }

    protected function wants(string $option, string $configKey, string $question): bool
{
    if ((bool) $this->option($option)) {
        return true;
    }

    $default = (bool) config('toolkit.default_builder.'.$configKey, true);

    if (! $this->input->isInteractive()) {
        return $default;
    }

    return $this->confirmOrDefault($question, $default);
}

    protected function indexCode(): string
    {
        $lines = [];

        foreach (array_filter(array_map('trim', explode(',', (string) $this->option('index')))) as $field) {
            $lines[] = '            $table->index([\''.$field.'\']);';
        }

        foreach (array_filter(array_map('trim', explode(',', (string) $this->option('unique')))) as $field) {
            $lines[] = '            $table->unique([\''.$field.'\']);';
        }

        return implode("\n", $lines);
    }

    protected function defaultConnection(): string
    {
        $connections = array_keys((array) config('database.connections', []));
        $default = (string) config('database.default', 'mysql');

        if (! $this->input->isInteractive() || $connections === []) {
            return $default;
        }

        return (string) $this->choice('请选择数据库连接', $connections, $default);
    }

    protected function relative(string $path): string
    {
        return str_replace(base_path().'/', '', $path);
    }
}
