<?php

declare(strict_types=1);

namespace Readfly\Laravel\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Readfly\Laravel\Console\Concerns\BuildsScaffoldContext;
use Readfly\Laravel\Console\Concerns\InteractsWithScaffolding;
use Readfly\Laravel\Console\StubRenderer;
use RuntimeException;

class InterfaceCommand extends Command
{
    use BuildsScaffoldContext;
    use InteractsWithScaffolding;

    protected $signature = 'builder:interface
        {name : 对应表名，不含表前缀，如：admins}
        {--desc= : 表注释，例如：管理员表}
        {--dictionary= : 目录，多层级请用 \\ 或 / 分隔，例如：api/v1}
        {--class= : 生成类名，默认根据表名推导}
        {--model= : 生成类名别名，等同于 --class}
        {--fields= : 字段定义，用于生成请求验证规则}
        {--fields-file= : 从文件读取字段定义}
        {--request : 生成请求验证类}
        {--route : 生成路由片段}
        {--middleware= : 路由中间件，多个用 | 分隔，如 auth:sanctum|throttle:60,1}
        {--prefix= : 覆盖路由 URI 前缀}
        {--route-name= : 覆盖路由名称前缀}
        {--register-route : 将生成的路由片段注册到 routes/api.php}
        {--dry-run : 只预览将生成的文件，不写入}
        {--no-request : 不生成请求验证类}
        {--fcp : 覆盖已存在的文件}
        {--force : 等同于 --fcp}';

    protected $description = '生成纯 API 接口脚手架（InterfaceService / Controller / Hook / Request / Route）';

    public function handle(): int
    {
        $name = trim((string) $this->argument('name'));

        if ($name === '' || ! preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $name)) {
            $this->error('表名必须由字母、数字、下划线组成。');

            return self::FAILURE;
        }

        try {
            $context = $this->buildContext($name);
            $renderer = new StubRenderer($this->templatePath());
            $plan = $this->buildPlan($renderer, $context);

            $rendered = [];

            foreach ($plan as [$stub, $path]) {
                $rendered[] = [$path, $renderer->render($stub, $context)];
            }

            if ((bool) $this->option('dry-run')) {
                foreach ($rendered as [$path]) {
                    $this->line('将生成：'.$this->relativePath($path));
                }

                $this->info('dry-run：未写入任何文件。');

                return self::SUCCESS;
            }

            $written = [];
            $failed = 0;

            foreach ($rendered as [$path, $content]) {
                if ($this->writeFile($path, $content)) {
                    $written[] = $path;
                } else {
                    $failed++;
                }
            }

            if ($failed > 0) {
                $this->error($failed.' 个文件写入失败。');

                return self::FAILURE;
            }
        } catch (InvalidArgumentException|RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->newLine();
        $this->info('已生成 '.count($written).' 个接口文件。');

        if ($context['has_route']) {
            if ((bool) $this->option('register-route')) {
                if (! $this->registerRouteFile($context['route_path'])) {
                    return self::FAILURE;
                }
            } elseif (in_array($context['route_path'], $written, true)) {
                $this->warn('路由片段需手动引入：require base_path(\''.$this->relativePath($context['route_path']).'\');');
            }
        }

        return self::SUCCESS;
    }

    protected function buildContext(string $name): array
    {
        $desc = (string) ($this->option('desc') ?: $this->askOrDefault('请输入表注释名称（例如：管理员表）', Str::headline($name).'表'));
        $desc = $this->sanitizeDocblock($desc);
        $dictionary = $this->normalizeDictionary((string) ($this->option('dictionary') ?: $this->askOrDefault('接口目录（多层级用 \\ 或 / 分隔，留空表示根目录）', '')));

        $fields = $this->scaffoldFields('是否现在定义请求字段？');

        $classOption = trim((string) ($this->option('class') ?: $this->option('model') ?: ''));
        $caseName = $classOption !== '' ? Str::studly($classOption) : Str::studly(Str::singular($name));

        if ($caseName === '' || ! preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $caseName)) {
            throw new InvalidArgumentException('无法从表名生成类名：'.$name);
        }
        $dataName = trim((string) preg_replace('/表$/u', '', $desc));
        $dataName = $dataName !== '' ? $dataName : $desc;

        $interfaceNamespace = $this->namespaceWith('interfaces', 'Interfaces', $dictionary);

        return array_merge($this->authoringContext(), [
            'dictionary' => $dictionary,
            'data_name' => $dataName,
            'table_comment' => $desc,
            'case_name' => $caseName,
            'service_class' => $caseName.'InterfaceService',
            'controller_class' => $caseName.'Controller',
            'hook_class' => $caseName.'Hook',
            'request_class' => $caseName.'Request',
            'service_namespace' => $interfaceNamespace.'\\Services',
            'controller_namespace' => $interfaceNamespace.'\\Controllers',
            'hook_namespace' => $interfaceNamespace.'\\Hooks',
            'request_namespace' => $this->namespaceWith('requests', 'Http\\Requests', $dictionary),
            'service_path' => $this->appPathFor('interfaces', 'Interfaces', $dictionary, 'Services/'.$caseName.'InterfaceService.php'),
            'controller_path' => $this->appPathFor('interfaces', 'Interfaces', $dictionary, 'Controllers/'.$caseName.'Controller.php'),
            'hook_path' => $this->appPathFor('interfaces', 'Interfaces', $dictionary, 'Hooks/'.$caseName.'Hook.php'),
            'request_path' => $this->appPathFor('requests', 'Http\\Requests', $dictionary, $caseName.'Request.php'),
            'route_path' => $this->routePath($name, $dictionary, 'interface'),
            'rules_code' => ($ruleCode = $this->ruleLines($fields)) !== '' ? $ruleCode : '            // 使用 --fields 生成验证规则',
            'has_enum' => $this->hasEnum($fields),
            'attributes_code' => $this->attributeLines($fields),
            'has_request' => $this->wants('request', 'request', '是否生成请求验证类？', 'no-request'),
            'has_route' => (bool) $this->option('route'),
            'route_uri' => $this->routePrefix($name, $dictionary),
            'route_name' => $this->routeNamePrefix($name, $dictionary, 'interface'),
            'middleware_code' => $this->routeMiddlewareCode(),
        ]);
    }

    protected function buildPlan(StubRenderer $renderer, array $context): array
    {
        $entries = [
            ['interface_service.tpl', $context['service_path']],
            ['interface_controller.tpl', $context['controller_path']],
            ['interface_hook.tpl', $context['hook_path']],
        ];

        if ($context['has_request']) {
            $entries[] = ['request.tpl', $context['request_path']];
        }

        if ($context['has_route']) {
            $entries[] = ['interface_route.tpl', $context['route_path']];
        }

        $force = (bool) ($this->option('fcp') || $this->option('force') || $this->option('dry-run'));
        $plan = [];

        foreach ($entries as [$stub, $path]) {
            if (! $renderer->exists($stub)) {
                throw new RuntimeException('缺少模板：'.$renderer->path($stub));
            }

            if (! $this->shouldOverwrite($path, $force)) {
                continue;
            }

            $plan[] = [$stub, $path];
        }

        return $plan;
    }
}
