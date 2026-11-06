<?php

declare(strict_types=1);

namespace Readfly\Laravel\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Readfly\Laravel\Console\Concerns\BuildsScaffoldContext;
use Readfly\Laravel\Console\Concerns\InteractsWithScaffolding;
use Readfly\Laravel\Console\StubRenderer;
use Readfly\Laravel\Support\File;

class InterfaceCommand extends Command
{
    use BuildsScaffoldContext;
    use InteractsWithScaffolding;

    protected $signature = 'builder:interface
        {name : 对应表名，不含表前缀，如：admins}
        {--desc= : 表注释，例如：管理员表}
        {--dictionary= : 目录，多层级请用 \\ 或 / 分隔，例如：api/v1}
        {--fields= : 字段定义，用于生成请求验证规则}
        {--request : 生成请求验证类}
        {--route : 生成路由片段}
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

        $context = $this->buildContext($name);
        $renderer = new StubRenderer($this->templatePath());
        $written = [];

        $files = [
            ['interface_service.tpl', $context['service_path']],
            ['interface_controller.tpl', $context['controller_path']],
            ['interface_hook.tpl', $context['hook_path']],
        ];

        if ($context['has_request']) {
            $files[] = ['request.tpl', $context['request_path']];
        }

        foreach ($files as [$stub, $path]) {
            if (! $renderer->exists($stub)) {
                $this->error('缺少模板：'.$renderer->path($stub));

                return self::FAILURE;
            }

            if (! $this->shouldOverwrite($path, (bool) ($this->option('fcp') || $this->option('force')))) {
                continue;
            }

            File::makeDirectory(dirname($path));

            if ($this->writeFile($path, $renderer->render($stub, $context))) {
                $written[] = $path;
            }
        }

        if ($context['has_route']) {
            File::makeDirectory(dirname($context['route_path']));

            if ($this->shouldOverwrite($context['route_path'], (bool) ($this->option('fcp') || $this->option('force')))) {
                if ($this->writeFile($context['route_path'], $renderer->render('interface_route.tpl', $context))) {
                    $written[] = $context['route_path'];
                }
            }
        }

        $this->newLine();
        $this->info('已生成 '.count($written).' 个接口文件。');

        if ($context['has_route']) {
            $this->warn('路由片段需手动引入：require base_path(\'routes/generated/'.$name.'.php\');');
        }

        return self::SUCCESS;
    }

    protected function buildContext(string $name): array
    {
        $desc = (string) ($this->option('desc') ?: $this->askOrDefault('请输入表注释名称（例如：管理员表）', Str::headline($name).'表'));
        $dictionary = $this->normalizeDictionary((string) ($this->option('dictionary') ?: $this->askOrDefault('接口目录（多层级用 \\ 或 / 分隔，留空表示根目录）', '')));

        $fields = $this->scaffoldFields('是否现在定义请求字段？');

        $caseName = Str::studly(Str::singular($name));
        $dataName = trim((string) preg_replace('/表$/u', '', $desc));
        $dataName = $dataName !== '' ? $dataName : $desc;

        $root = trim((string) config('toolkit.namespace.root', 'App'), '\\');
        $interfaceNamespace = trim($root.'\\'.config('toolkit.namespace.interfaces', 'Interfaces').'.'.ltrim($dictionary, '\\'), '.\\');

        $suffix = str_replace('\\', '/', $dictionary);

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
            'request_namespace' => trim($root.'\\'.config('toolkit.namespace.requests', 'Http\\Requests').($dictionary !== '' ? $dictionary : ''), '\\'),
            'service_path' => app_path('Interfaces'.$suffix.'/Services/'.$caseName.'InterfaceService.php'),
            'controller_path' => app_path('Interfaces'.$suffix.'/Controllers/'.$caseName.'Controller.php'),
            'hook_path' => app_path('Interfaces'.$suffix.'/Hooks/'.$caseName.'Hook.php'),
            'request_path' => app_path('Http/Requests'.$suffix.'/'.$caseName.'Request.php'),
            'route_path' => base_path('routes/generated/'.$name.'.php'),
            'rules_code' => ($rule_code = $this->ruleLines($fields)) !== '' ? $rule_code : '            // 使用 --fields 生成验证规则',
            'attributes_code' => $this->attributeLines($fields),
            'has_request' => (bool) ($this->option('request') || (! $this->input->isInteractive() && config('toolkit.default_builder.request', true))),
            'has_route' => (bool) $this->option('route'),
            'route_uri' => str_replace('_', '-', $name),
            'route_name' => str_replace('_', '.', $name),
        ]);
    }

}
