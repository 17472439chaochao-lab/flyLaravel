<?php

declare(strict_types=1);

require __DIR__.'/../vendor/autoload.php';

use Readfly\Laravel\Console\StubRenderer;
use Readfly\Laravel\Support\File;

$context = [
    'model_namespace' => 'App\\Models\\Api',
    'model_class' => 'User',
    'table_name' => 's_users',
    'table_comment' => '用户信息表',
    'data_name' => '用户信息',
    'db_connection' => 'mysql',
    'charset' => 'utf8mb4',
    'engine' => 'innodb',
    'fillable_code' => "        'name',\n        'email',",
    'casts_code' => "        'meta' => 'array',",
    'repository_namespace' => 'App\\Repository\\Api',
    'repository_class' => 'UserRepository',
    'service_namespace' => 'App\\Services\\Api',
    'service_class' => 'UserService',
    'hook_namespace' => 'App\\Hooks\\Api',
    'hook_class' => 'UserHook',
    'cache_namespace' => 'App\\Caches\\Api',
    'cache_class' => 'UserCacher',
    'controller_namespace' => 'App\\Http\\Controllers\\Api',
    'controller_class' => 'UserController',
    'request_namespace' => 'App\\Http\\Requests\\Api',
    'request_class' => 'UserRequest',
    'rules_code' => "            'name' => ['required', 'string', 'max:64'],",
    'attributes_code' => "            'name' => '名称',",
    'fields_code' => "            \$table->string('name', 64)->comment('名称');",
    'index_code' => "            \$table->index(['name']);",
    'cache_name' => 'api:users',
    'cache_expire' => 3600,
    'cache_driver' => 'file',
    'route_uri' => 'users',
    'route_name' => 'users',
    'service_class_interface' => 'UserInterfaceService',
    'author' => 'readfly',
    'author_email' => 'wucc513721@163.com',
    'company' => '达州市飞鸟网络科技有限公司',
    'date' => date('Y-m-d'),
    'time' => date('H:i:s'),
];

$context['service_class'] = 'UserService';

$templates = [
    'model.tpl' => 'model.php',
    'repository.tpl' => 'repository.php',
    'service.tpl' => 'service.php',
    'hook.tpl' => 'hook.php',
    'cacher.tpl' => 'cacher.php',
    'controller.tpl' => 'controller.php',
    'request.tpl' => 'request.php',
    'migration.tpl' => 'migration.php',
    'route.tpl' => 'route.php',
    'interface_service.tpl' => 'interface_service.php',
    'interface_controller.tpl' => 'interface_controller.php',
    'interface_hook.tpl' => 'interface_hook.php',
    'interface_route.tpl' => 'interface_route.php',
];

$renderer = new StubRenderer();
$target = sys_get_temp_dir().'/toolkit-render';
File::makeDirectory($target);

$interfaceContext = $context;
$interfaceContext['service_namespace'] = 'App\\Interfaces\\Api\\Services';
$interfaceContext['controller_namespace'] = 'App\\Interfaces\\Api\\Controllers';
$interfaceContext['hook_namespace'] = 'App\\Interfaces\\Api\\Hooks';

foreach ($templates as $stub => $file) {
    $data = str_starts_with($stub, 'interface_') ? $interfaceContext : $context;
    $content = $renderer->render($stub, $data);

    if ($content === '') {
        fwrite(STDERR, "EMPTY: {$stub}\n");
        exit(1);
    }

    File::put($target.'/'.$file, $content);
    echo "rendered: {$stub} -> {$target}/{$file}\n";
}