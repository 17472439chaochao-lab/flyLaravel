<?php

declare(strict_types=1);

namespace Readfly\Laravel;

use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\ValidationException;
use Readfly\Laravel\Http\ApiResponse;
use Readfly\Laravel\Console\Commands\DoctorCommand;
use Readfly\Laravel\Console\Commands\InitCommand;
use Readfly\Laravel\Console\Commands\InterfaceCommand;
use Readfly\Laravel\Console\Commands\PackageCommand;
use Readfly\Laravel\Support\Code;
use Readfly\Laravel\Support\File;
use Readfly\Laravel\Support\Path;

class LaravelToolkitServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/toolkit.php', 'toolkit');

        $this->commands([
            InitCommand::class,
            PackageCommand::class,
            InterfaceCommand::class,
            DoctorCommand::class,
        ]);
    }

    public function boot(): void
    {
        $this->publishAssets();
        $this->publishConfiguration();
        $this->publishRuntimeFiles();
        $this->ensureAvatars();
        $this->registerExceptionRenderer();
    }

    protected function registerExceptionRenderer(): void
    {
        if (! $this->app->bound(ExceptionHandler::class)) {
            return;
        }

        $handler = $this->app->make(ExceptionHandler::class);

        if (! is_object($handler) || ! method_exists($handler, 'renderable')) {
            return;
        }

        call_user_func([$handler, 'renderable'], static function (ValidationException $exception, mixed $request) {
            $expectsJson = is_object($request) && method_exists($request, 'expectsJson') && $request->expectsJson();
            $isApi = is_object($request) && method_exists($request, 'is') && $request->is('api/*');

            if (! $expectsJson && ! $isApi) {
                return null;
            }

            return ApiResponse::error(Code::VALIDATE_FAILED, $exception->getMessage(), [], [
                'validations' => $exception->errors(),
            ]);
        });
    }

    protected function publishAssets(): void
    {
        $this->publishes([
            __DIR__.'/../resources/templates' => $this->templateTarget(),
        ], 'toolkit-stubs');

        $this->publishes([
            __DIR__.'/../data/forbidden_words.dic' => Path::base('database/data/forbidden_words.dic'),
        ], 'toolkit-assets');
    }

    protected function publishConfiguration(): void
    {
        $this->publishes([
            __DIR__.'/../config/toolkit.php' => config_path('toolkit.php'),
            __DIR__.'/../config/project.php' => config_path('project.php'),
        ], 'toolkit-config');
    }

    protected function publishRuntimeFiles(): void
    {
        $this->publishes([
            __DIR__.'/../functions/auth.php' => app_path('Helpers/auth.php'),
            __DIR__.'/../functions/response.php' => app_path('Helpers/response.php'),
            __DIR__.'/../functions/projects.php' => app_path('Helpers/projects.php'),
            __DIR__.'/../resources/runtime/TestCommand.php' => app_path('Console/Commands/TestCommand.php'),
            __DIR__.'/../resources/runtime/UpdateCommand.php' => app_path('Console/Commands/Application/UpdateCommand.php'),
        ], 'toolkit-runtime');
    }

    public static function templatePath(): string
    {
        $config = function_exists('config') ? config('toolkit.templates_path') : null;

        if (is_string($config) && trim($config) !== '') {
            return $config;
        }

        $published = function_exists('resource_path')
            ? resource_path('toolkit/templates')
            : Path::base('resources/toolkit/templates');

        if (is_dir($published)) {
            return $published;
        }

        return Path::package('resources/templates');
    }

    protected function templateTarget(): string
    {
        return function_exists('resource_path') ? resource_path('toolkit/templates') : Path::base('resources/toolkit/templates');
    }

    protected function ensureAvatars(): void
    {
        $target = Path::public((string) config('toolkit.assets.avatars', 'static/avatars'));

        if (File::files($target) !== []) {
            return;
        }

        ToolkitEvents::autoload();
    }
}