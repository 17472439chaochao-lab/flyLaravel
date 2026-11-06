<?php
/**
 * Power by readfly/laravel.
 * User: {{ author }} <{{ author_email }}>
 * Company: {{ company }}
 * Date: {{ date }}
 * Time: {{ time }}
 */

namespace {{ repository_namespace }};

use {{ model_namespace }}\{{ model_class }};
use Readfly\Laravel\Modules\RepositoryModule;

/**
 * {{ data_name }}数据仓库
 */
class {{ repository_class }} extends RepositoryModule
{
    public function __construct()
    {
        parent::__construct(new {{ model_class }}(), {{ model_class }}::DB_CONNECTION);
    }
}