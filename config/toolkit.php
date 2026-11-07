<?php

return [

    'database_prefix' => 's_',
    'database_charset' => 'utf8mb4',
    'database_collation' => null,
    'database_engine' => 'innodb',

    'database_connection' => '__DEFAULT_DATABASE_CONNECTION__',
    'cache_driver' => '__DEFAULT_CACHE_DRIVER__',
    'app_version' => '__APP_VERSION__',

    'templates_path' => null,

    'default_builder' => [
        'migration' => true,
        'data_cache' => true,
        'controller' => true,
        'request' => true,
        'cache_expire' => 3600,
        'cache_limit' => 100,
    ],

    'author' => 'readfly',
    'author_email' => 'wucc513721@163.com',
    'company' => '达州市飞鸟网络科技有限公司',

    'namespace' => [
        'root' => 'App',
        'models' => 'Models',
        'repositories' => 'Repository',
        'services' => 'Services',
        'hooks' => 'Hooks',
        'caches' => 'Caches',
        'controllers' => 'Http\\Controllers',
        'requests' => 'Http\\Requests',
        'interfaces' => 'Interfaces',
    ],

    'assets' => [
        'avatars' => 'static/avatars',
    ],

    'middleware' => [
        'request_id' => [
            'enabled' => true,
            'header' => 'X-Request-Id',
        ],
        'cors' => [
            'enabled' => true,
            'origins' => ['*'],
            'methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],
            'headers' => ['Content-Type', 'Authorization', 'X-Requested-With', 'X-Request-Id'],
            'credentials' => false,
            'max_age' => 86400,
        ],
        'exception' => [
            'enabled' => true,
        ],
    ],

    'response' => [
        'include_consuming' => true,
        'include_locale' => true,
        'include_request_id' => true,
    ],

    'repository' => [
        'page_size' => 20,
        'max_page_size' => 200,
    ],

];