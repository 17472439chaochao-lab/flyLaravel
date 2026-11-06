<?php
/**
 * Power by readfly/laravel.
 * User: {{ author }} <{{ author_email }}>
 * Company: {{ company }}
 * Date: {{ date }}
 * Time: {{ time }}
 */

namespace {{ service_namespace }};

use Illuminate\Http\Request;
use Readfly\Laravel\Modules\ServiceModule;

/**
 * {{ data_name }}接口逻辑服务容器
 */
class {{ service_class }} extends ServiceModule
{
    public function information(Request $request): bool
    {
        return $this->success([
            'data' => [],
        ]);
    }
}