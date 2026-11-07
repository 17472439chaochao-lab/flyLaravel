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
use Readfly\Laravel\Support\Code;
use {{ repository_namespace }}\{{ repository_class }};
use {{ hook_namespace }}\{{ hook_class }};
{{#if has_cache}}
use {{ cache_namespace }}\{{ cache_class }};
{{/if}}

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

    public function index(Request $request): bool
    {
        $page = max(1, (int) $request->input('page', 1));
        $pageSize = max(1, (int) $request->input('page_size', (int) config('toolkit.repository.page_size', 20)));
        $result = $this->repository->lists([], ['*'], [], ['id' => 'desc'], '', $page, $pageSize);

        return $this->success($result);
    }

    public function show({{ id_php_type }} $id): bool
    {
        $row = $this->repository->row(['id' => $id]);

        if ($row === null || $row === []) {
            return $this->fail(Code::NOT_FOUND, Code::message(Code::NOT_FOUND));
        }

        return $this->success($row);
    }

    public function store(array $data): bool
    {
        try {
            $id = $this->repository->transaction(function () use ($data) {
{{#if id_is_string}}
{{ id_generation_code }}
                if (! $this->repository->insert($data)) {
                    throw new \RuntimeException('创建失败');
                }

                $id = $data['id'];
{{/if}}
{{#unless id_is_string}}
                $id = $this->repository->insertGetId($data);
{{/unless}}

                if (! {{ hook_class }}::create($id, $data)) {
                    throw new \RuntimeException('创建钩子执行失败');
                }

                return $id;
            });
        } catch (\Throwable $exception) {
            return $this->fail(Code::LOGIC_ERROR, $exception->getMessage());
        }

{{#if has_cache}}
        (new {{ cache_class }}())->refresh();
{{/if}}
        return $this->success(['id' => $id], [], '创建成功');
    }

    public function update({{ id_php_type }} $id, array $data): bool
    {
        if (! $this->repository->exists(['id' => $id])) {
            return $this->fail(Code::NOT_FOUND, Code::message(Code::NOT_FOUND));
        }

        try {
            $affected = $this->repository->transaction(function () use ($id, $data) {
                $affected = $this->repository->update(['id' => $id], $data);

                if (! {{ hook_class }}::update($id, $data)) {
                    throw new \RuntimeException('更新钩子执行失败');
                }

                return $affected;
            });
        } catch (\Throwable $exception) {
            return $this->fail(Code::LOGIC_ERROR, $exception->getMessage());
        }

{{#if has_cache}}
        (new {{ cache_class }}())->refresh();
{{/if}}
        return $this->success(['id' => $id, 'affected' => $affected], [], '更新成功');
    }

    public function destroy({{ id_php_type }} $id): bool
    {
        if (! $this->repository->exists(['id' => $id])) {
            return $this->fail(Code::NOT_FOUND, Code::message(Code::NOT_FOUND));
        }

        try {
            $affected = $this->repository->transaction(function () use ($id) {
{{#if has_soft_deletes}}
                $affected = $this->repository->softDelete(['id' => $id]);
{{/if}}
{{#unless has_soft_deletes}}
                $affected = $this->repository->delete(['id' => $id]);
{{/unless}}

                if (! {{ hook_class }}::delete($id)) {
                    throw new \RuntimeException('删除钩子执行失败');
                }

                return $affected;
            });
        } catch (\Throwable $exception) {
            return $this->fail(Code::LOGIC_ERROR, $exception->getMessage());
        }

{{#if has_cache}}
        (new {{ cache_class }}())->refresh();
{{/if}}
        return $this->success(['id' => $id, 'affected' => $affected], [], '删除成功');
    }
}
