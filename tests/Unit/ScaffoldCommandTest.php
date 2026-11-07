<?php

declare(strict_types=1);

namespace Readfly\Laravel\Tests\Unit;

use Illuminate\Container\Container;
use Illuminate\Console\Command;
use PHPUnit\Framework\TestCase;
use Readfly\Laravel\Console\Commands\InterfaceCommand;
use Readfly\Laravel\Console\Commands\PackageCommand;
use Readfly\Laravel\Support\File;
use Readfly\Laravel\Tests\Unit\Support\TestApplication;
use Readfly\Laravel\Tests\Unit\Support\TestConfigRepository;
use Readfly\Laravel\Tests\Unit\Support\TestPaths;
use Symfony\Component\Console\Tester\CommandTester;

class ScaffoldCommandTest extends TestCase
{
    protected TestApplication $app;

    protected TestConfigRepository $config;

    protected function setUp(): void
    {
        TestPaths::$base = sys_get_temp_dir().'/toolkit-scaffold-'.bin2hex(random_bytes(6));
        File::makeDirectory(TestPaths::path('app'));
        File::makeDirectory(TestPaths::path('database/migrations'));
        File::makeDirectory(TestPaths::path('routes/generated'));

        $toolkit = require dirname(__DIR__, 2).'/config/toolkit.php';

        $this->config = new TestConfigRepository([
            'toolkit' => $toolkit,
            'database' => [
                'default' => 'mysql',
                'connections' => ['mysql' => [], 'sqlite' => []],
            ],
            'cache' => ['default' => 'file'],
            'app' => ['name' => 'Toolkit Test', 'env' => 'testing', 'locale' => 'zh_CN'],
        ]);

        $this->app = new TestApplication();
        Container::setInstance($this->app);
        $this->app->instance('config', $this->config);
    }

    protected function tearDown(): void
    {
        File::delete(TestPaths::$base);
        Container::setInstance(null);
    }

    public function test_package_command_generates_syntax_valid_files(): void
    {
        $tester = $this->runCommand(new PackageCommand(), [
            'name' => 'users',
            '--desc' => '用户信息表',
            '--dictionary' => 'api/v1',
            '--fields' => 'name:string(64):用户名称;email:?email:邮箱;age:int:年龄=0;meta:?json:扩展信息',
            '--index' => 'email',
            '--unique' => 'email',
            '--migration' => true,
            '--cache' => true,
            '--request' => true,
            '--route' => true,
        ]);

        $this->assertSame(0, $tester->getStatusCode(), $tester->getDisplay());
        $this->assertFileExists(TestPaths::path('app/Models/Api/V1/User.php'));
        $this->assertFileExists(TestPaths::path('app/Repository/Api/V1/UserRepository.php'));
        $this->assertFileExists(TestPaths::path('app/Http/Controllers/Api/V1/UserController.php'));
        $this->assertFileExists(TestPaths::path('routes/generated/api/v1/users.php'));
        $this->assertGeneratedFilesAreValid();

        $route = (string) File::get(TestPaths::path('routes/generated/api/v1/users.php'));
        $this->assertStringContainsString("Route::prefix('api/v1/users')", $route);
        $this->assertStringContainsString("name('api.v1.users.')", $route);

        $controller = (string) File::get(TestPaths::path('app/Http/Controllers/Api/V1/UserController.php'));
        $this->assertStringContainsString('use App\\Http\\Requests\\Api\\V1\\UserRequest;', $controller);
        $this->assertStringContainsString('use App\\Services\\Api\\V1\\UserService;', $controller);
        $this->assertStringContainsString('index(Request $request)', $controller);
        $this->assertStringContainsString('UserRequest $request', $controller);
        $this->assertStringContainsString('$this->service->index($request);', $controller);

        $request = (string) File::get(TestPaths::path('app/Http/Requests/Api/V1/UserRequest.php'));
        $this->assertStringContainsString("isMethod('put')", $request);

        $service = (string) File::get(TestPaths::path('app/Services/Api/V1/UserService.php'));
        $this->assertStringContainsString('public function index(Request $request): bool', $service);
        $this->assertStringContainsString('$this->repository->lists(', $service);
        $this->assertStringContainsString('UserHook::create($id, $data)', $service);
        $this->assertStringContainsString('(new UserCacher())->refresh();', $service);

        $migration = $this->firstFile(TestPaths::path('database/migrations'));
        $this->assertNotNull($migration);
        $this->assertStringContainsString("create_api_v1_user_table", basename((string) $migration));
        $this->assertStringContainsString("\$table->unique(['email']);", (string) File::get((string) $migration));
        $this->assertStringNotContainsString("\$table->index(['email']);", (string) File::get((string) $migration));
    }

    public function test_interface_command_generates_valid_namespaces_and_routes(): void
    {
        $tester = $this->runCommand(new InterfaceCommand(), [
            'name' => 'orders',
            '--desc' => '订单表',
            '--dictionary' => 'api/v1',
            '--fields' => 'order_no:string(32):订单号;amount:decimal(10,2):金额',
            '--request' => true,
            '--route' => true,
        ]);

        $this->assertSame(0, $tester->getStatusCode(), $tester->getDisplay());
        $this->assertFileExists(TestPaths::path('app/Interfaces/Api/V1/Services/OrderInterfaceService.php'));
        $this->assertFileExists(TestPaths::path('routes/generated/api/v1/orders.interface.php'));
        $this->assertGeneratedFilesAreValid();

        $route = (string) File::get(TestPaths::path('routes/generated/api/v1/orders.interface.php'));
        $this->assertStringContainsString("Route::prefix('api/v1/orders')", $route);
        $this->assertStringContainsString("name('api.v1.orders.interface.')", $route);

        $service = (string) File::get(TestPaths::path('app/Interfaces/Api/V1/Services/OrderInterfaceService.php'));
        $this->assertStringContainsString('namespace App\\Interfaces\\Api\\V1\\Services;', $service);

        $this->assertFileExists(TestPaths::path('app/Http/Requests/Api/V1/OrderRequest.php'));

        $controller = (string) File::get(TestPaths::path('app/Interfaces/Api/V1/Controllers/OrderController.php'));
        $this->assertStringNotContainsString('OrderRequest', $controller);
        $this->assertStringContainsString('Request $request', $controller);
    }

    public function test_invalid_dictionary_is_rejected_without_writing_outside_app(): void
    {
        $tester = $this->runCommand(new PackageCommand(), [
            'name' => 'users',
            '--dictionary' => '../../evil',
            '--fields' => 'name:string(64):名称',
        ]);

        $this->assertSame(1, $tester->getStatusCode());
        $this->assertStringContainsString('非法目录片段', $tester->getDisplay());
        $this->assertDirectoryDoesNotExist(TestPaths::path('Evil'));
    }

    public function test_unknown_index_field_is_rejected(): void
    {
        $tester = $this->runCommand(new PackageCommand(), [
            'name' => 'users',
            '--fields' => 'name:string(64):名称',
            '--index' => 'unknown',
        ]);

        $this->assertSame(1, $tester->getStatusCode());
        $this->assertStringContainsString('索引字段未在 --fields 中定义', $tester->getDisplay());
    }

    protected function runCommand(Command $command, array $input): CommandTester
    {
        $command->setLaravel($this->app);
        $tester = new CommandTester($command);
        $tester->execute($input, ['interactive' => false]);

        return $tester;
    }

    protected function assertGeneratedFilesAreValid(): void
    {
        $files = array_merge(
            File::allFiles(TestPaths::path('app')),
            File::allFiles(TestPaths::path('database')),
            File::allFiles(TestPaths::path('routes'))
        );

        $this->assertNotEmpty($files);

        foreach ($files as $file) {
            if (! str_ends_with($file, '.php')) {
                continue;
            }

            $content = (string) File::get($file);
            $this->assertStringNotContainsString('{{', $content, $file);

            $output = [];
            $code = 0;
            exec(escapeshellarg(PHP_BINARY).' -l '.escapeshellarg($file).' 2>&1', $output, $code);
            $this->assertSame(0, $code, $file."\n".implode("\n", $output));
        }
    }

    protected function firstFile(string $directory): ?string
    {
        $files = File::allFiles($directory);

        return $files === [] ? null : $files[0];
    }

    public function test_package_without_request_uses_base_request(): void
    {
        $tester = $this->runCommand(new PackageCommand(), [
            'name' => 'users',
            '--dictionary' => 'api/v1',
            '--fields' => 'name:string(64):名称',
            '--no-request' => true,
        ]);

        $this->assertSame(0, $tester->getStatusCode(), $tester->getDisplay());
        $this->assertFileDoesNotExist(TestPaths::path('app/Http/Requests/Api/V1/UserRequest.php'));

        $controller = (string) File::get(TestPaths::path('app/Http/Controllers/Api/V1/UserController.php'));
        $this->assertStringNotContainsString('UserRequest', $controller);
        $this->assertStringContainsString('Request $request', $controller);
        $this->assertGeneratedFilesAreValid();
    }

    public function test_published_templates_are_used_automatically(): void
    {
        $source = dirname(__DIR__, 2).'/resources/templates';
        $target = TestPaths::path('resources/toolkit/templates');

        foreach (File::allFiles($source) as $file) {
            $relative = str_replace($source.'/', '', $file);
            File::copy($file, $target.'/'.$relative);
        }

        $model = (string) File::get($target.'/model.tpl');
        File::put($target.'/model.tpl', str_replace(
            'use Readfly\Laravel\Modules\ModelModule;',
            "// CUSTOM MODEL TEMPLATE\nuse Readfly\Laravel\Modules\ModelModule;",
            $model
        ));

        $tester = $this->runCommand(new PackageCommand(), [
            'name' => 'users',
            '--fields' => 'name:string(64):名称',
        ]);

        $this->assertSame(0, $tester->getStatusCode(), $tester->getDisplay());
        $this->assertStringContainsString(
            '// CUSTOM MODEL TEMPLATE',
            (string) File::get(TestPaths::path('app/Models/User.php'))
        );
    }

    public function test_namespace_config_is_applied_to_paths_and_namespaces(): void
    {
        $this->config->set('toolkit.namespace.models', 'Domain\\Models');

        $tester = $this->runCommand(new PackageCommand(), [
            'name' => 'users',
            '--fields' => 'name:string(64):名称',
            '--no-cache' => true,
            '--no-controller' => true,
            '--no-request' => true,
            '--no-migration' => true,
        ]);

        $this->assertSame(0, $tester->getStatusCode(), $tester->getDisplay());
        $this->assertFileExists(TestPaths::path('app/Domain/Models/User.php'));
        $this->assertStringContainsString(
            'namespace App\\Domain\\Models;',
            (string) File::get(TestPaths::path('app/Domain/Models/User.php'))
        );
    }

    public function test_connection_and_cache_driver_config_are_applied(): void
    {
        $this->config->set('toolkit.database_connection', 'sqlite');
        $this->config->set('toolkit.cache_driver', 'array');

        $tester = $this->runCommand(new PackageCommand(), [
            'name' => 'users',
            '--fields' => 'name:string(64):名称',
            '--cache' => true,
            '--no-controller' => true,
            '--no-request' => true,
            '--no-migration' => true,
        ]);

        $this->assertSame(0, $tester->getStatusCode(), $tester->getDisplay());
        $this->assertStringContainsString(
            "protected \$connection = 'sqlite';",
            (string) File::get(TestPaths::path('app/Models/User.php'))
        );
        $this->assertStringContainsString(
            "parent::__construct('users', 3600, 'array');",
            (string) File::get(TestPaths::path('app/Caches/UserCacher.php'))
        );
    }

    public function test_enum_and_escaped_defaults_generate_valid_migration(): void
    {
        $tester = $this->runCommand(new PackageCommand(), [
            'name' => 'settings',
            '--fields' => 'status:enum(Active):状态;code:string(2):编码=08;path:string(64):路径:"C:\\\\"',
            '--migration' => true,
            '--no-cache' => true,
            '--no-controller' => true,
            '--no-request' => true,
        ]);

        $this->assertSame(0, $tester->getStatusCode(), $tester->getDisplay());
        $this->assertGeneratedFilesAreValid();

        $migration = (string) File::get((string) $this->firstFile(TestPaths::path('database/migrations')));
        $this->assertStringContainsString("enum('status', ['Active'])", $migration);
        $this->assertStringContainsString("default('08')", $migration);
        $this->assertStringContainsString("default('C:\\\\')", $migration);
    }

    public function test_index_code_injection_is_rejected(): void
    {
        $tester = $this->runCommand(new PackageCommand(), [
            'name' => 'users',
            '--fields' => 'email:string(64):邮箱',
            '--index' => "email']); phpinfo(); //",
        ]);

        $this->assertSame(1, $tester->getStatusCode());
        $this->assertStringContainsString('非法索引字段', $tester->getDisplay());
        $this->assertFileDoesNotExist(TestPaths::path('app/Models/User.php'));
    }

    public function test_interface_without_request_uses_base_request(): void
    {
        $tester = $this->runCommand(new InterfaceCommand(), [
            'name' => 'orders',
            '--dictionary' => 'api/v1',
            '--fields' => 'order_no:string(32):订单号',
            '--no-request' => true,
        ]);

        $this->assertSame(0, $tester->getStatusCode(), $tester->getDisplay());
        $this->assertFileDoesNotExist(TestPaths::path('app/Http/Requests/Api/V1/OrderRequest.php'));

        $controller = (string) File::get(TestPaths::path('app/Interfaces/Api/V1/Controllers/OrderController.php'));
        $this->assertStringNotContainsString('OrderRequest', $controller);
        $this->assertStringContainsString('Request $request', $controller);
        $this->assertGeneratedFilesAreValid();
    }

    public function test_class_override_changes_generated_class_and_path(): void
    {
        $tester = $this->runCommand(new PackageCommand(), [
            'name' => 'media',
            '--class' => 'MediaItem',
            '--fields' => 'title:string(64):标题',
        ]);

        $this->assertSame(0, $tester->getStatusCode(), $tester->getDisplay());
        $this->assertFileExists(TestPaths::path('app/Models/MediaItem.php'));
        $this->assertStringContainsString(
            'class MediaItem extends ModelModule',
            (string) File::get(TestPaths::path('app/Models/MediaItem.php'))
        );
        $this->assertGeneratedFilesAreValid();
    }

    public function test_fields_file_is_supported(): void
    {
        $fieldsFile = TestPaths::path('fields.txt');
        File::put($fieldsFile, "name:string(64):名称\nage:int:年龄=0");

        $tester = $this->runCommand(new PackageCommand(), [
            'name' => 'users',
            '--fields-file' => $fieldsFile,
        ]);

        $this->assertSame(0, $tester->getStatusCode(), $tester->getDisplay());
        $model = (string) File::get(TestPaths::path('app/Models/User.php'));
        $this->assertStringContainsString("'name'", $model);
        $this->assertStringContainsString("'age'", $model);
    }

    public function test_dry_run_does_not_write_files(): void
    {
        $tester = $this->runCommand(new PackageCommand(), [
            'name' => 'users',
            '--fields' => 'name:string(64):名称',
            '--route' => true,
            '--dry-run' => true,
        ]);

        $this->assertSame(0, $tester->getStatusCode(), $tester->getDisplay());
        $this->assertStringContainsString('将生成：', $tester->getDisplay());
        $this->assertFileDoesNotExist(TestPaths::path('app/Models/User.php'));
        $this->assertFileDoesNotExist(TestPaths::path('routes/generated/users.php'));
    }

    public function test_register_route_appends_require_statement(): void
    {
        File::put(TestPaths::path('routes/api.php'), "<?php\n\n");

        $tester = $this->runCommand(new PackageCommand(), [
            'name' => 'users',
            '--fields' => 'name:string(64):名称',
            '--route' => true,
            '--register-route' => true,
        ]);

        $this->assertSame(0, $tester->getStatusCode(), $tester->getDisplay());
        $this->assertStringContainsString(
            "require base_path('routes/generated/users.php');",
            (string) File::get(TestPaths::path('routes/api.php'))
        );
    }

    public function test_duplicate_migration_is_skipped_unless_new_migration_is_requested(): void
    {
        $input = [
            'name' => 'users',
            '--fields' => 'name:string(64):名称',
            '--migration' => true,
            '--no-cache' => true,
            '--no-controller' => true,
            '--no-request' => true,
        ];

        $first = $this->runCommand(new PackageCommand(), $input);
        $this->assertSame(0, $first->getStatusCode(), $first->getDisplay());
        $this->assertCount(1, File::allFiles(TestPaths::path('database/migrations')));

        $second = $this->runCommand(new PackageCommand(), $input);
        $this->assertSame(0, $second->getStatusCode(), $second->getDisplay());
        $this->assertCount(1, File::allFiles(TestPaths::path('database/migrations')));
        $this->assertStringContainsString('已存在同名迁移', $second->getDisplay());

        $third = $this->runCommand(new PackageCommand(), array_merge($input, ['--new-migration' => true]));
        $this->assertSame(0, $third->getStatusCode(), $third->getDisplay());
        $this->assertCount(2, File::allFiles(TestPaths::path('database/migrations')));
    }

    public function test_composite_index_is_generated(): void
    {
        $tester = $this->runCommand(new PackageCommand(), [
            'name' => 'users',
            '--fields' => 'email:string(64):邮箱;tenant_id:int:租户',
            '--index' => 'email+tenant_id',
            '--no-cache' => true,
            '--no-controller' => true,
            '--no-request' => true,
        ]);

        $this->assertSame(0, $tester->getStatusCode(), $tester->getDisplay());
        $migration = (string) File::get((string) $this->firstFile(TestPaths::path('database/migrations')));
        $this->assertStringContainsString("\$table->index(['email', 'tenant_id']);", $migration);
    }

    public function test_uuid_primary_key_timestamps_and_soft_deletes_options(): void
    {
        $tester = $this->runCommand(new PackageCommand(), [
            'name' => 'users',
            '--fields' => 'name:string(64):名称',
            '--id' => 'uuid',
            '--no-timestamps' => true,
            '--soft-deletes' => true,
        ]);

        $this->assertSame(0, $tester->getStatusCode(), $tester->getDisplay());
        $this->assertGeneratedFilesAreValid();

        $migration = (string) File::get((string) $this->firstFile(TestPaths::path('database/migrations')));
        $this->assertStringContainsString("\$table->uuid('id')", $migration);
        $this->assertStringContainsString('$table->softDeletes()', $migration);
        $this->assertStringNotContainsString("created_at", $migration);

        $model = (string) File::get(TestPaths::path('app/Models/User.php'));
        $this->assertStringContainsString('use SoftDeletes;', $model);
        $this->assertStringContainsString("protected \$keyType = 'string';", $model);
        $this->assertStringContainsString('public $incrementing = false;', $model);
        $this->assertStringContainsString('AUTO_TIMESTAMP = false;', $model);

        $service = (string) File::get(TestPaths::path('app/Services/UserService.php'));
        $this->assertStringContainsString('Str::uuid()', $service);
        $this->assertStringContainsString('->insert($data)', $service);
        $this->assertStringContainsString('$this->repository->softDelete(', $service);

        $controller = (string) File::get(TestPaths::path('app/Http/Controllers/UserController.php'));
        $this->assertStringContainsString('string $id', $controller);
        $this->assertStringNotContainsString("whereNumber", (string) File::get(TestPaths::path('routes/generated/users.php')));
    }

    public function test_force_writes_backup_before_overwrite(): void
    {
        $input = [
            'name' => 'users',
            '--fields' => 'name:string(64):名称',
            '--no-cache' => true,
            '--no-controller' => true,
            '--no-request' => true,
            '--no-migration' => true,
        ];

        $this->assertSame(0, $this->runCommand(new PackageCommand(), $input)->getStatusCode());
        $modelPath = TestPaths::path('app/Models/User.php');
        File::put($modelPath, "<?php\n// USER EDIT\n");

        $tester = $this->runCommand(new PackageCommand(), array_merge($input, ['--force' => true]));
        $this->assertSame(0, $tester->getStatusCode(), $tester->getDisplay());
        $this->assertStringNotContainsString('USER EDIT', (string) File::get($modelPath));

        $backups = glob($modelPath.'.*.bak') ?: [];
        $this->assertNotEmpty($backups);
        $this->assertStringContainsString('USER EDIT', (string) File::get($backups[0]));
    }

    public function test_enum_request_uses_rule_in_and_imports_rule(): void
    {
        $tester = $this->runCommand(new PackageCommand(), [
            'name' => 'users',
            '--fields' => 'status:enum(Active,Pending):状态',
            '--request' => true,
            '--no-cache' => true,
            '--no-controller' => true,
        ]);

        $this->assertSame(0, $tester->getStatusCode(), $tester->getDisplay());
        $request = (string) File::get(TestPaths::path('app/Http/Requests/UserRequest.php'));
        $this->assertStringContainsString('use Illuminate\\Validation\\Rule;', $request);
        $this->assertStringContainsString("Rule::in(['Active', 'Pending'])", $request);
    }

    public function test_ulid_primary_key_uses_string_key_and_generator(): void
    {
        $tester = $this->runCommand(new PackageCommand(), [
            'name' => 'users',
            '--fields' => 'name:string(64):名称',
            '--id' => 'ulid',
            '--no-cache' => true,
            '--no-controller' => true,
            '--no-request' => true,
            '--migration' => true,
        ]);

        $this->assertSame(0, $tester->getStatusCode(), $tester->getDisplay());
        $this->assertGeneratedFilesAreValid();

        $migration = (string) File::get((string) $this->firstFile(TestPaths::path('database/migrations')));
        $this->assertStringContainsString("\$table->ulid('id')", $migration);

        $service = (string) File::get(TestPaths::path('app/Services/UserService.php'));
        $this->assertStringContainsString('Str::ulid()', $service);
        $this->assertStringContainsString('public function show(string $id)', $service);
    }

    public function test_json_fields_file_is_supported(): void
    {
        $fieldsFile = TestPaths::path('fields.json');
        File::put($fieldsFile, json_encode([
            ['name' => 'name', 'type' => 'string(64)', 'comment' => '名称'],
            ['name' => 'age', 'type' => 'int', 'comment' => '年龄', 'default' => 0],
        ], JSON_UNESCAPED_UNICODE));

        $tester = $this->runCommand(new PackageCommand(), [
            'name' => 'users',
            '--fields-file' => $fieldsFile,
            '--no-cache' => true,
            '--no-controller' => true,
            '--no-request' => true,
            '--no-migration' => true,
        ]);

        $this->assertSame(0, $tester->getStatusCode(), $tester->getDisplay());
        $model = (string) File::get(TestPaths::path('app/Models/User.php'));
        $this->assertStringContainsString("'name'", $model);
        $this->assertStringContainsString("'age'", $model);
    }

    public function test_route_prefix_middleware_and_name_can_be_overridden(): void
    {
        $tester = $this->runCommand(new PackageCommand(), [
            'name' => 'users',
            '--fields' => 'name:string(64):名称',
            '--route' => true,
            '--prefix' => 'api/v2/members',
            '--route-name' => 'api.v2.members',
            '--middleware' => 'auth:sanctum|throttle:60,1',
            '--no-cache' => true,
            '--no-request' => true,
            '--no-migration' => true,
        ]);

        $this->assertSame(0, $tester->getStatusCode(), $tester->getDisplay());
        $route = (string) File::get(TestPaths::path('routes/generated/users.php'));
        $this->assertStringContainsString("Route::prefix('api/v2/members')->name('api.v2.members.')->middleware(['auth:sanctum', 'throttle:60,1'])->group", $route);
    }

    public function test_fulltext_index_is_generated(): void
    {
        $tester = $this->runCommand(new PackageCommand(), [
            'name' => 'articles',
            '--fields' => 'title:string(255):标题;content:text:内容',
            '--fulltext' => 'title+content',
            '--no-cache' => true,
            '--no-controller' => true,
            '--no-request' => true,
        ]);

        $this->assertSame(0, $tester->getStatusCode(), $tester->getDisplay());
        $migration = (string) File::get((string) $this->firstFile(TestPaths::path('database/migrations')));
        $this->assertStringContainsString("\$table->fullText(['title', 'content']);", $migration);
    }

    public function test_foreign_key_constraint_is_generated(): void
    {
        $tester = $this->runCommand(new PackageCommand(), [
            'name' => 'posts',
            '--fields' => 'user_id:bigint!:用户ID;title:string(255):标题',
            '--foreign' => 'user_id:users,id:cascade:restrict',
            '--no-cache' => true,
            '--no-controller' => true,
            '--no-request' => true,
        ]);

        $this->assertSame(0, $tester->getStatusCode(), $tester->getDisplay());
        $this->assertGeneratedFilesAreValid();

        $migration = (string) File::get((string) $this->firstFile(TestPaths::path('database/migrations')));
        $this->assertStringContainsString(
            "\$table->foreign('user_id')->references('id')->on('users')->onDelete('cascade')->onUpdate('restrict');",
            $migration
        );

        $model = (string) File::get(TestPaths::path('app/Models/Post.php'));
        $this->assertStringContainsString('use Illuminate\Database\Eloquent\Relations\BelongsTo;', $model);
        $this->assertStringContainsString('public function user(): BelongsTo', $model);
        $this->assertStringContainsString("->belongsTo(\App\Models\User::class, 'user_id', 'id')", $model);
    }

    public function test_custom_index_name_is_generated(): void
    {
        $tester = $this->runCommand(new PackageCommand(), [
            'name' => 'users',
            '--fields' => 'email:string(64):邮箱;tenant_id:int:租户',
            '--index' => 'email+tenant_id',
            '--index-name' => 'email+tenant_id:users_email_tenant_index',
            '--no-cache' => true,
            '--no-controller' => true,
            '--no-request' => true,
        ]);

        $this->assertSame(0, $tester->getStatusCode(), $tester->getDisplay());
        $migration = (string) File::get((string) $this->firstFile(TestPaths::path('database/migrations')));
        $this->assertStringContainsString("\$table->index(['email', 'tenant_id'], 'users_email_tenant_index');", $migration);
    }

    public function test_interface_route_options_are_applied(): void
    {
        $tester = $this->runCommand(new InterfaceCommand(), [
            'name' => 'orders',
            '--fields' => 'order_no:string(32):订单号',
            '--route' => true,
            '--prefix' => 'api/v2/orders',
            '--route-name' => 'api.v2.orders',
            '--middleware' => 'auth:sanctum',
        ]);

        $this->assertSame(0, $tester->getStatusCode(), $tester->getDisplay());
        $route = (string) File::get(TestPaths::path('routes/generated/orders.interface.php'));
        $this->assertStringContainsString("Route::prefix('api/v2/orders')->name('api.v2.orders.interface.')->middleware(['auth:sanctum'])->group", $route);
    }

    public function test_custom_field_rules_are_rendered_into_request(): void
    {
        $tester = $this->runCommand(new PackageCommand(), [
            'name' => 'users',
            '--fields' => 'email:string(64):邮箱::false:"required|email|unique:users,email"',
            '--request' => true,
            '--no-cache' => true,
            '--no-controller' => true,
            '--no-migration' => true,
        ]);

        $this->assertSame(0, $tester->getStatusCode(), $tester->getDisplay());
        $request = (string) File::get(TestPaths::path('app/Http/Requests/UserRequest.php'));
        $this->assertStringContainsString("'unique:users,email'", $request);
    }
}
