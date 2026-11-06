<?php
/**
 * Power by readfly/laravel.
 * User: {{ author }} <{{ author_email }}>
 * Company: {{ company }}
 * Date: {{ date }}
 * Time: {{ time }}
 */

namespace {{ service_namespace }};

use Readfly\Laravel\Modules\ServiceModule;
use {{ repository_namespace }}\{{ repository_class }};

/**
 * {{ data_name }}逻辑服务容器
 */
class {{ service_class }} extends ServiceModule
{
    protected {{ repository_class }} $repository;

    public function __construct(?{{ repository_class }} $repository = null)
    {
        parent::__construct();
        $this->repository = $repository ?? new {{ repository_class }}();
    }
}