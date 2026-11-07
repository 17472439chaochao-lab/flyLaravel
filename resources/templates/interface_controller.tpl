<?php
/**
 * Power by readfly/laravel.
 * User: {{ author }} <{{ author_email }}>
 * Company: {{ company }}
 * Date: {{ date }}
 * Time: {{ time }}
 */

namespace {{ controller_namespace }};

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Readfly\Laravel\Modules\ControllerModule;
use {{ service_namespace }}\{{ service_class }};

/**
 * {{ data_name }}接口控制器
 */
class {{ controller_class }} extends ControllerModule
{
    public function information(Request $request, {{ service_class }} $service): JsonResponse
    {
        $service->information($request);

        return responseService($service);
    }
}
