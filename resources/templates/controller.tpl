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
{{#if has_request}}
use {{ request_namespace }}\{{ request_class }};
{{/if}}

/**
 * {{ data_name }}控制器
 */
class {{ controller_class }} extends ControllerModule
{
    protected {{ service_class }} $service;

    public function __construct({{ service_class }} $service)
    {
        $this->service = $service;
    }

    public function index(Request $request): JsonResponse
    {
        $this->service->index($request);

        return responseService($this->service);
    }

    public function show({{ id_php_type }} $id, Request $request): JsonResponse
    {
        $this->service->show($id);

        return responseService($this->service);
    }

    public function store({{#if has_request}}{{ request_class }}{{/if}}{{#unless has_request}}Request{{/unless}} $request): JsonResponse
    {
        $data = {{#if has_request}}$request->validated(){{/if}}{{#unless has_request}}$request->all(){{/unless}};
        $this->service->store($data);

        return responseService($this->service);
    }

    public function update({{ id_php_type }} $id, {{#if has_request}}{{ request_class }}{{/if}}{{#unless has_request}}Request{{/unless}} $request): JsonResponse
    {
        $data = {{#if has_request}}$request->validated(){{/if}}{{#unless has_request}}$request->all(){{/unless}};
        $this->service->update($id, $data);

        return responseService($this->service);
    }

    public function destroy({{ id_php_type }} $id, Request $request): JsonResponse
    {
        $this->service->destroy($id);

        return responseService($this->service);
    }
}
