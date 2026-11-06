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

/**
 * {{ data_name }}控制器
 */
class {{ controller_class }} extends ControllerModule
{
    public function index(Request $request): JsonResponse
    {
        return responseSuccess([]);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        return responseSuccess(['id' => $id]);
    }

    public function store(Request $request): JsonResponse
    {
        return responseSuccess();
    }

    public function update(Request $request, int $id): JsonResponse
    {
        return responseSuccess(['id' => $id]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        return responseSuccess(['id' => $id]);
    }
}