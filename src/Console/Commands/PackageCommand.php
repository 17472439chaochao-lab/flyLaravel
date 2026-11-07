<?php

declare(strict_types=1);

namespace Readfly\Laravel\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Readfly\Laravel\Console\Concerns\BuildsScaffoldContext;
use Readfly\Laravel\Console\Concerns\InteractsWithScaffolding;
use Readfly\Laravel\Console\StubRenderer;
use RuntimeException;

class PackageCommand extends Command
{
    use BuildsScaffoldContext;
    use InteractsWithScaffolding;

    protected $signature = 'builder:package
        {name : 表名，不含表前缀}
        {--desc= : 表注释，如：用户信息表}
        {--dictionary= : 目录，多层级请用 \\ 或 / 分隔}
        {--class= : 生成类名，默认根据表名推导}
        {--model= : 生成类名别名，等同于 --class}
        {--dp= : 表前缀}
        {--dc= : 数据库连接}
        {--dcs= : 表字符集}
        {--collation= : 表排序规则}
        {--de= : 表引擎}
        {--cd= : 缓存驱动}
        {--fields= : 字段定义，如 name:string(64):名称;age:int:年龄=0}
        {--fields-file= : 从文件读取字段定义}
        {--index= : 普通索引，逗号分隔多个索引，用 + 连接联合索引}
        {--unique= : 唯一索引，逗号分隔多个索引，用 + 连接联合索引}
        {--fulltext= : 全文索引，逗号分隔多个索引，用 + 连接联合索引}
        {--index-name= : 自定义索引名，格式 email+tenant_id:idx_name，多个用 ; 分隔}
        {--foreign= : 外键定义，如 user_id:users,id:cascade:restrict，多个用 ; 分隔}
        {--id= : 主键类型 id|uuid|ulid}
        {--no-timestamps : 不生成 created_at / updated_at}
        {--soft-deletes : 生成 deleted_at 并启用 SoftDeletes}
        {--migration : 生成迁移文件}
        {--cache : 生成数据缓存}
        {--controller : 生成控制器}
        {--request : 生成请求验证类}
        {--route : 生成路由片段}
        {--middleware= : 路由中间件，多个用 | 分隔，如 auth:sanctum|throttle:60,1}
        {--prefix= : 覆盖路由 URI 前缀}
        {--route-name= : 覆盖路由名称前缀}
        {--register-route : 将生成的路由片段注册到 routes/api.php}
        {--dry-run : 只预览将生成的文件，不写入}
        {--new-migration : 即使已存在同名迁移也创建新迁移}
        {--no-migration : 不生成迁移文件}
        {--no-cache : 不生成数据缓存}
        {--no-controller : 不生成控制器}
        {--no-request : 不生成请求验证类}
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

        try {
            $context = $this->buildContext($name);
            $renderer = new StubRenderer($this->templatePath());
            $plan = $this->buildPlan($renderer, $context);

            $rendered = [];

            foreach ($plan as [$stub, $path]) {
                $rendered[] = [$path, $renderer->render($stub, $context)];
            }

            if ((bool) $this->option('dry-run')) {
                foreach ($rendered as [$path]) {
                    $this->line('将生成：'.$this->relativePath($path));
                }

                $this->info('dry-run：未写入任何文件。');

                return self::SUCCESS;
            }

            $written = [];
            $failed = 0;

            foreach ($rendered as [$path, $content]) {
                if ($this->writeFile($path, $content)) {
                    $written[] = $path;
                } else {
                    $failed++;
                }
            }

            if ($failed > 0) {
                $this->error($failed.' 个文件写入失败。');

                return self::FAILURE;
            }
        } catch (InvalidArgumentException|RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->newLine();
        $this->info('已生成 '.count($written).' 个文件，表：'.$context['table_name']);

        if ($context['has_route']) {
            if ((bool) $this->option('register-route')) {
                if (! $this->registerRouteFile($context['route_path'])) {
                    return self::FAILURE;
                }
            } elseif (in_array($context['route_path'], $written, true)) {
                $this->warn('路由片段需手动引入：require base_path(\''.$this->relativePath($context['route_path']).'\');');
            }
        }

        return self::SUCCESS;
    }

    protected function buildContext(string $name): array
    {
        $desc = (string) ($this->option('desc') ?: $this->askOrDefault('请输入表注释名称（以“表”字结尾，例如：用户信息表）', Str::headline($name).'表'));
        $desc = $this->sanitizeDocblock($desc);
        $dictionary = $this->normalizeDictionary((string) ($this->option('dictionary') ?: $this->askOrDefault('目录名称（多层级用 \\ 或 / 分隔，留空表示根目录）', '')));

        $prefix = (string) ($this->option('dp') ?: config('toolkit.database_prefix', 's_'));
        if ($prefix !== '' && ! preg_match('/^[A-Za-z0-9_]*$/', $prefix)) {
            throw new InvalidArgumentException('非法表前缀：'.$prefix);
        }
        $prefix = $prefix === '' ? '' : rtrim($prefix, '_').'_';

        $connection = $this->resolveConnection();
        $charset = $this->validateCodeToken((string) ($this->option('dcs') ?: config('toolkit.database_charset', 'utf8mb4')), '表字符集');
        $collation = trim((string) ($this->option('collation') ?: config('toolkit.database_collation', '')));
        $collation = $collation !== '' ? $this->validateCodeToken($collation, '表排序规则') : $charset.'_general_ci';
        $engine = $this->validateCodeToken((string) ($this->option('de') ?: config('toolkit.database_engine', 'innodb')), '表引擎');
        $cacheDriver = $this->resolveCacheDriver();
        $hasRoute = (bool) $this->option('route');
        $idType = strtolower(trim((string) ($this->option('id') ?: 'id')));
        $hasTimestamps = ! (bool) $this->option('no-timestamps');
        $hasSoftDeletes = (bool) $this->option('soft-deletes');

        if (! in_array($idType, ['id', 'uuid', 'ulid'], true)) {
            throw new InvalidArgumentException('非法主键类型：'.$idType);
        }

        if ($hasRoute && (bool) $this->option('no-controller')) {
            throw new InvalidArgumentException('--route 需要控制器，不能同时使用 --no-controller。');
        }

        $fields = $this->scaffoldFields();

        if ($hasSoftDeletes) {
            unset($fields['deleted_at']);
        }

        $classOption = trim((string) ($this->option('class') ?: $this->option('model') ?: ''));
        $caseName = $classOption !== '' ? Str::studly($classOption) : Str::studly(Str::singular($name));

        if ($caseName === '' || ! preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $caseName)) {
            throw new InvalidArgumentException('无法从表名生成类名：'.$name);
        }
        $dataName = trim((string) preg_replace('/表$/u', '', $desc));
        $dataName = $dataName !== '' ? $dataName : $desc;

        $namespaces = $this->namespaces($dictionary);
        $hasCache = $this->wants('cache', 'data_cache', '是否生成数据缓存文件？', 'no-cache');
        $hasController = $hasRoute || $this->wants('controller', 'controller', '是否生成控制器？', 'no-controller');
        $hasRequest = $this->wants('request', 'request', '是否生成请求验证类？', 'no-request');
        $hasMigration = $this->wants('migration', 'migration', '是否生成数据迁移（Migration）文件？', 'no-migration');
        $force = (bool) ($this->option('fcp') || $this->option('force'));
        $newMigration = (bool) $this->option('new-migration');
        $paths = $this->paths($dictionary, $caseName, $name, $hasMigration, $force, $newMigration);
        $tableName = $prefix.$name;
        $cacheName = ($dictionary !== '' ? strtolower(str_replace('\\', ':', trim($dictionary, '\\'))).':' : '').$name;
        $fieldLines = $this->migrationFieldLines($fields);

        return array_merge($namespaces, $this->authoringContext(), [
            'model_class' => $caseName,
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
            'collation' => $collation,
            'engine' => $engine,
            'fields_code' => $fieldLines !== '' ? $fieldLines : '            // 使用 --fields 生成字段，或在此手动补充',
            'id_code' => $this->idCode($idType),
            'id_type' => $idType,
            'id_is_string' => $idType !== 'id',
            'id_is_integer' => $idType === 'id',
            'id_php_type' => $idType === 'id' ? 'int' : 'string',
            'id_generation_code' => $this->idGenerationCode($idType),
            'auto_timestamp_code' => $hasTimestamps ? 'true' : 'false',
            'has_timestamps' => $hasTimestamps,
            'has_soft_deletes' => $hasSoftDeletes,
            'timestamps_code' => $this->timestampsCode($hasTimestamps),
            'soft_deletes_code' => $this->softDeletesCode($hasSoftDeletes),
            'has_fields' => $fields !== [],
            'fillable_code' => $this->fillableLines($fields),
            'casts_code' => $this->castLines($fields),
            'attributes_code' => $this->attributeLines($fields),
            'rules_code' => $this->ruleLines($fields),
            'has_enum' => $this->hasEnum($fields),
            'index_code' => $this->indexCode($fields, $hasTimestamps, $hasSoftDeletes),
            'fulltext_code' => $this->fulltextCode($fields, $hasTimestamps, $hasSoftDeletes),
            'foreign_code' => $this->foreignCode($fields),
            'has_relations' => $this->foreignDefinitions($fields) !== [],
            'relations_code' => $this->relationsCode($fields, $this->namespaceWith('models', 'Models', $dictionary)),
            'cache_name' => $cacheName,
            'cache_expire' => max(0, (int) config('toolkit.default_builder.cache_expire', 3600)),
            'cache_limit' => max(1, (int) config('toolkit.default_builder.cache_limit', 100)),
            'cache_driver' => $cacheDriver,
            'has_cache' => $hasCache,
            'has_controller' => $hasController,
            'has_request' => $hasRequest,
            'has_migration' => $hasMigration,
            'has_route' => $hasRoute,
            'route_uri' => $this->routePrefix($name, $dictionary),
            'route_name' => $this->routeNamePrefix($name, $dictionary),
            'middleware_code' => $this->routeMiddlewareCode(),
        ], $paths);
    }

    protected function buildPlan(StubRenderer $renderer, array $context): array
    {
        $entries = [
            ['model.tpl', $context['model_path']],
            ['repository.tpl', $context['repository_path']],
            ['service.tpl', $context['service_path']],
            ['hook.tpl', $context['hook_path']],
        ];

        if ($context['has_cache']) {
            $entries[] = ['cacher.tpl', $context['cache_path']];
        }

        if ($context['has_controller']) {
            $entries[] = ['controller.tpl', $context['controller_path']];
        }

        if ($context['has_request']) {
            $entries[] = ['request.tpl', $context['request_path']];
        }

        if ($context['has_migration'] && $context['migration_path'] !== null) {
            $entries[] = ['migration.tpl', $context['migration_path']];
        }

        if ($context['has_route']) {
            $entries[] = ['route.tpl', $context['route_path']];
        }

        $force = (bool) ($this->option('fcp') || $this->option('force') || $this->option('dry-run'));
        $plan = [];

        foreach ($entries as [$stub, $path]) {
            if (! $renderer->exists($stub)) {
                throw new RuntimeException('缺少模板：'.$renderer->path($stub));
            }

            if ($stub !== 'migration.tpl' && ! $this->shouldOverwrite($path, $force)) {
                continue;
            }

            $plan[] = [$stub, $path];
        }

        return $plan;
    }

    protected function namespaces(string $dictionary): array
    {
        return [
            'model_namespace' => $this->namespaceWith('models', 'Models', $dictionary),
            'repository_namespace' => $this->namespaceWith('repositories', 'Repository', $dictionary),
            'service_namespace' => $this->namespaceWith('services', 'Services', $dictionary),
            'hook_namespace' => $this->namespaceWith('hooks', 'Hooks', $dictionary),
            'cache_namespace' => $this->namespaceWith('caches', 'Caches', $dictionary),
            'controller_namespace' => $this->namespaceWith('controllers', 'Http\\Controllers', $dictionary),
            'request_namespace' => $this->namespaceWith('requests', 'Http\\Requests', $dictionary),
        ];
    }

    protected function paths(string $dictionary, string $caseName, string $name, bool $hasMigration, bool $force, bool $newMigration): array
    {
        return [
            'model_path' => $this->appPathFor('models', 'Models', $dictionary, $caseName.'.php'),
            'repository_path' => $this->appPathFor('repositories', 'Repository', $dictionary, $caseName.'Repository.php'),
            'service_path' => $this->appPathFor('services', 'Services', $dictionary, $caseName.'Service.php'),
            'hook_path' => $this->appPathFor('hooks', 'Hooks', $dictionary, $caseName.'Hook.php'),
            'cache_path' => $this->appPathFor('caches', 'Caches', $dictionary, $caseName.'Cacher.php'),
            'controller_path' => $this->appPathFor('controllers', 'Http\\Controllers', $dictionary, $caseName.'Controller.php'),
            'request_path' => $this->appPathFor('requests', 'Http\\Requests', $dictionary, $caseName.'Request.php'),
            'migration_path' => $hasMigration ? $this->migrationPath($dictionary, $caseName, $force, $newMigration) : null,
            'route_path' => $this->routePath($name, $dictionary),
        ];
    }

    protected function idCode(string $idType): string
    {
        return match ($idType) {
            'uuid' => '            $table->uuid(\'id\')->primary()->comment(\'表ID\');',
            'ulid' => '            $table->ulid(\'id\')->primary()->comment(\'表ID\');',
            default => '            $table->increments(\'id\')->comment(\'表ID\');',
        };
    }

    protected function idGenerationCode(string $idType): string
    {
        return match ($idType) {
            'uuid' => '                $data[\'id\'] = (string) \\Illuminate\\Support\\Str::uuid();',
            'ulid' => '                $data[\'id\'] = (string) \\Illuminate\\Support\\Str::ulid();',
            default => '',
        };
    }

    protected function timestampsCode(bool $hasTimestamps): string
    {
        if (! $hasTimestamps) {
            return '';
        }

        return '            $table->timestamp(\'created_at\')->nullable()->comment(\'创建时间\');'."\n"
            .'            $table->timestamp(\'updated_at\')->nullable()->comment(\'更新时间\');';
    }

    protected function softDeletesCode(bool $hasSoftDeletes): string
    {
        return $hasSoftDeletes ? '            $table->softDeletes()->comment(\'删除时间\');' : '';
    }

    protected function migrationPath(string $dictionary, string $caseName, bool $force = false, bool $newMigration = false): ?string
    {
        $parts = array_filter([
            $this->dictionarySnake($dictionary),
            Str::snake($caseName),
        ], static fn (string $part): bool => $part !== '');
        $base = preg_replace('/_+/', '_', implode('_', $parts)) ?: Str::snake($caseName);
        $existing = glob(database_path('migrations/*_create_'.$base.'_table*.php')) ?: [];

        if ($existing !== []) {
            if ($force) {
                return $existing[0];
            }

            if (! $newMigration) {
                $this->warn('已存在同名迁移，已跳过：'.$this->relativePath($existing[0]).'（如需新迁移请加 --new-migration）');

                return null;
            }
        }

        $timestamp = date('Y_m_d_His');
        $path = database_path('migrations/'.$timestamp.'_create_'.$base.'_table.php');
        $counter = 1;

        while (file_exists($path)) {
            $path = database_path('migrations/'.$timestamp.'_create_'.$base.'_table_'.$counter++.'.php');
        }

        return $path;
    }

    protected function indexCode(array $fields, bool $hasTimestamps = true, bool $hasSoftDeletes = false): string
    {
        $allowed = array_merge(['id'], array_keys($fields));

        if ($hasTimestamps) {
            $allowed = array_merge($allowed, ['created_at', 'updated_at']);
        }

        if ($hasSoftDeletes) {
            $allowed[] = 'deleted_at';
        }
        $unique = $this->indexGroups('unique', $allowed);
        $index = $this->indexGroups('index', $allowed);
        $lines = [];

        foreach ($unique as $group) {
            $name = $this->indexNameFor($group);
            $lines[] = '            $table->unique(['.implode(', ', array_map(static fn (string $field): string => var_export($field, true), $group)).']'.($name !== null ? ', '.var_export($name, true) : '').');';
        }

        foreach ($index as $group) {
            if (in_array($group, $unique, true)) {
                continue;
            }

            $name = $this->indexNameFor($group);
            $lines[] = '            $table->index(['.implode(', ', array_map(static fn (string $field): string => var_export($field, true), $group)).']'.($name !== null ? ', '.var_export($name, true) : '').');';
        }

        return implode("\n", $lines);
    }

    protected function allowedIndexFields(array $fields, bool $hasTimestamps, bool $hasSoftDeletes): array
    {
        $allowed = array_merge(['id'], array_keys($fields));

        if ($hasTimestamps) {
            $allowed = array_merge($allowed, ['created_at', 'updated_at']);
        }

        if ($hasSoftDeletes) {
            $allowed[] = 'deleted_at';
        }

        return $allowed;
    }

    protected function fulltextCode(array $fields, bool $hasTimestamps = true, bool $hasSoftDeletes = false): string
    {
        $groups = $this->indexGroups('fulltext', $this->allowedIndexFields($fields, $hasTimestamps, $hasSoftDeletes));

        return implode("\n", array_map(
            function (array $group): string {
                $name = $this->indexNameFor($group);

                return '            $table->fullText(['.implode(', ', array_map(static fn (string $field): string => var_export($field, true), $group)).']'.($name !== null ? ', '.var_export($name, true) : '').');';
            },
            $groups
        ));
    }

    protected function foreignDefinitions(array $fields): array
    {
        $raw = trim((string) ($this->option('foreign') ?: ''));

        if ($raw === '') {
            return [];
        }

        $definitions = [];

        foreach (array_filter(array_map('trim', explode(';', $raw))) as $spec) {
            $parts = array_map('trim', explode(':', $spec));

            if (count($parts) < 2) {
                throw new InvalidArgumentException('非法外键定义：'.$spec);
            }

            $field = $parts[0];
            $target = array_map('trim', explode(',', $parts[1]));

            if (! preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $field) || ! in_array($field, array_keys($fields), true)) {
                throw new InvalidArgumentException('外键字段未在 --fields 中定义：'.$field);
            }

            if (count($target) !== 2 || ! preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $target[0]) || ! preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $target[1])) {
                throw new InvalidArgumentException('非法外键目标：'.$parts[1]);
            }

            $onDelete = $parts[2] ?? '';
            $onUpdate = $parts[3] ?? '';

            foreach ([$onDelete, $onUpdate] as $action) {
                if ($action !== '' && ! preg_match('/^[A-Za-z ]+$/', $action)) {
                    throw new InvalidArgumentException('非法外键动作：'.$action);
                }
            }

            $definitions[] = [
                'field' => $field,
                'table' => $target[0],
                'column' => $target[1],
                'on_delete' => $onDelete,
                'on_update' => $onUpdate,
            ];
        }

        return $definitions;
    }

    protected function foreignCode(array $fields): string
    {
        $lines = [];

        foreach ($this->foreignDefinitions($fields) as $definition) {
            $line = '            $table->foreign('.var_export($definition['field'], true).')->references('.var_export($definition['column'], true).')->on('.var_export($definition['table'], true).')';

            if ($definition['on_delete'] !== '') {
                $line .= '->onDelete('.var_export($definition['on_delete'], true).')';
            }

            if ($definition['on_update'] !== '') {
                $line .= '->onUpdate('.var_export($definition['on_update'], true).')';
            }

            $lines[] = $line.';';
        }

        return implode("\n", $lines);
    }

    protected function relationsCode(array $fields, string $modelNamespace): string
    {
        $relations = [];
        $prefix = (string) config('toolkit.database_prefix', 's_');

        foreach ($this->foreignDefinitions($fields) as $definition) {
            $table = $definition['table'];

            if ($prefix !== '' && str_starts_with($table, $prefix)) {
                $table = substr($table, strlen($prefix));
            }

            $method = Str::camel(Str::singular((string) preg_replace('/_(id|uuid|ulid)$/', '', $definition['field'])));
            $class = Str::studly(Str::singular($table));

            if ($method === '' || $class === '') {
                continue;
            }

            $relations[] = '    public function '.$method.'(): BelongsTo'.PHP_EOL
                .'    {'.PHP_EOL
                .'        return $this->belongsTo(\\'.$modelNamespace.'\\'.$class.'::class, '.var_export($definition['field'], true).', '.var_export($definition['column'], true).');'.PHP_EOL
                .'    }';
        }

        return implode(PHP_EOL.PHP_EOL, $relations);
    }

    protected function indexNames(): array
    {
        $raw = trim((string) ($this->option('index-name') ?: ''));

        if ($raw === '') {
            return [];
        }

        $names = [];

        foreach (array_filter(array_map('trim', explode(';', $raw))) as $spec) {
            $parts = array_map('trim', explode(':', $spec, 2));

            if (count($parts) !== 2) {
                throw new InvalidArgumentException('非法索引名定义：'.$spec);
            }

            $fields = array_filter(array_map('trim', explode('+', $parts[0])));
            $name = $parts[1];

            if ($fields === [] || ! preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $name)) {
                throw new InvalidArgumentException('非法索引名：'.$spec);
            }

            $names[implode('+', $fields)] = $name;
        }

        return $names;
    }

    protected function indexNameFor(array $group): ?string
    {
        return $this->indexNames()[implode('+', $group)] ?? null;
    }

    protected function indexGroups(string $option, array $allowed): array
    {
        $groups = [];

        foreach (array_filter(array_map('trim', explode(',', (string) $this->option($option)))) as $item) {
            $group = [];

            foreach (array_filter(array_map('trim', explode('+', $item))) as $field) {
                if (! preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $field)) {
                    throw new InvalidArgumentException('非法索引字段：'.$field);
                }

                if (! in_array($field, $allowed, true)) {
                    throw new InvalidArgumentException('索引字段未在 --fields 中定义：'.$field);
                }

                $group[$field] = $field;
            }

            if ($group !== []) {
                $group = array_values($group);
                $groups[implode('|', $group)] = $group;
            }
        }

        return array_values($groups);
    }

    protected function resolveConnection(): string
    {
        $option = trim((string) $this->option('dc'));

        if ($option !== '') {
            return $this->validateCodeToken($option, '数据库连接');
        }

        $configured = trim((string) config('toolkit.database_connection', ''));

        if ($configured !== '' && ! str_starts_with($configured, '__')) {
            return $this->validateCodeToken($configured, '数据库连接');
        }

        return $this->validateCodeToken($this->defaultConnection(), '数据库连接');
    }

    protected function resolveCacheDriver(): string
    {
        $option = trim((string) $this->option('cd'));

        if ($option !== '') {
            return $this->validateCodeToken($option, '缓存驱动');
        }

        $configured = trim((string) config('toolkit.cache_driver', ''));

        if ($configured !== '' && ! str_starts_with($configured, '__')) {
            return $this->validateCodeToken($configured, '缓存驱动');
        }

        $defaultDriver = trim((string) config('cache.default', 'file'));

        return $this->validateCodeToken($defaultDriver !== '' ? $defaultDriver : 'file', '缓存驱动');
    }

    protected function defaultConnection(): string
    {
        $connections = array_keys((array) config('database.connections', []));
        $default = (string) config('database.default', 'mysql');

        if (! $this->input->isInteractive() || $connections === []) {
            return $default;
        }

        if (! in_array($default, $connections, true)) {
            $default = (string) $connections[0];
        }

        return (string) $this->choice('请选择数据库连接', $connections, $default);
    }
}
