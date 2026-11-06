<?php

declare(strict_types=1);

namespace Readfly\Laravel\Providers\Qiniu;

use Qiniu\Auth;
use Qiniu\Storage\BucketManager;
use Qiniu\Storage\UploadManager;
use Readfly\Laravel\Contracts\OssUploader;
use Readfly\Laravel\Exceptions\ToolkitException;
use Readfly\Laravel\Support\Logger;
use Throwable;

class OssProvider implements OssUploader
{
    protected array $configs;

    protected ?Auth $auth = null;

    public function __construct(array $configs = [])
    {
        $configured = $this->loadConfigs();

        $this->configs = $configs !== [] ? $configs : $configured;
    }

    public static function make(
        string $access_key,
        string $access_secret,
        string $bucket,
        string $domain,
        string $visibility = 'public'
    ): static {
        return new static(compact('access_key', 'access_secret', 'bucket', 'domain', 'visibility'));
    }

    public static function makeByConfigs(array $configs): static
    {
        return new static($configs);
    }

    public function put(string $path, mixed $content, array $options = []): bool
    {
        $token = $this->uploadToken($path);

        try {
            [, $error] = $this->uploader()->put(
                $token,
                $this->path($path),
                $content,
                $options['params'] ?? null,
                $options['content_type'] ?? 'application/octet-stream'
            );

            if ($error === null) {
                return true;
            }

            Logger::record('oss:qiniu:put_failed', ['path' => $path, 'error' => $this->errorPayload($error)]);
        } catch (Throwable $exception) {
            Logger::record('oss:qiniu:put_failed', ['exception' => $exception, 'path' => $path]);
        }

        return false;
    }

    public function putFile(string $path, string $local_path, array $options = []): bool
    {
        if (! is_file($local_path)) {
            Logger::record('oss:qiniu:file_missing', ['path' => $path, 'local_path' => $local_path]);

            return false;
        }

        $token = $this->uploadToken($path);

        try {
            [, $error] = $this->uploader()->putFile(
                $token,
                $this->path($path),
                $local_path,
                $options['params'] ?? null,
                $options['content_type'] ?? 'application/octet-stream'
            );

            if ($error === null) {
                return true;
            }

            Logger::record('oss:qiniu:put_file_failed', ['path' => $path, 'error' => $this->errorPayload($error)]);
        } catch (Throwable $exception) {
            Logger::record('oss:qiniu:put_file_failed', ['exception' => $exception, 'path' => $path, 'local_path' => $local_path]);
        }

        return false;
    }

    public function delete(string $path): bool
    {
        try {
            [, $error] = $this->bucketManager()->delete($this->bucket(), $this->path($path));

            if ($error === null || $this->isMissing($error)) {
                return true;
            }

            Logger::record('oss:qiniu:delete_failed', ['path' => $path, 'error' => $this->errorPayload($error)]);
        } catch (Throwable $exception) {
            Logger::record('oss:qiniu:delete_failed', ['exception' => $exception, 'path' => $path]);
        }

        return false;
    }

    public function exists(string $path): bool
    {
        try {
            [, $error] = $this->bucketManager()->stat($this->bucket(), $this->path($path));

            if ($error === null) {
                return true;
            }

            if (! $this->isMissing($error)) {
                Logger::record('oss:qiniu:exists_failed', ['path' => $path, 'error' => $this->errorPayload($error)]);
            }

            return false;
        } catch (Throwable $exception) {
            Logger::record('oss:qiniu:exists_failed', ['exception' => $exception, 'path' => $path]);
        }

        return false;
    }

    public function url(string $path, int $expires = 0): string
    {
        $url = $this->host().'/'.$this->path($path);

        if ($expires > 0 || $this->visibility() === 'private') {
            return (string) $this->auth()->privateDownloadUrl($url, $expires > 0 ? $expires : 3600);
        }

        return $url;
    }

    public function uploadToken(string $path = '', int $expires = 3600, array $policy = []): string
    {
        $key = trim($path, '/');

        return $this->auth()->uploadToken($this->bucket(), $key === '' ? null : $key, $expires, $policy, true);
    }

    protected function auth(): Auth
    {
        if (! class_exists(Auth::class)) {
            throw new ToolkitException('缺少依赖 qiniu/php-sdk，请执行 composer require qiniu/php-sdk');
        }

        return $this->auth ??= new Auth($this->required('access_key'), $this->required('access_secret'));
    }

    protected function uploader(): UploadManager
    {
        if (! class_exists(UploadManager::class)) {
            throw new ToolkitException('缺少依赖 qiniu/php-sdk，请执行 composer require qiniu/php-sdk');
        }

        return new UploadManager();
    }

    protected function bucketManager(): BucketManager
    {
        if (! class_exists(BucketManager::class)) {
            throw new ToolkitException('缺少依赖 qiniu/php-sdk，请执行 composer require qiniu/php-sdk');
        }

        return new BucketManager($this->auth());
    }

    protected function bucket(): string
    {
        return $this->required('bucket');
    }

    protected function host(): string
    {
        $domain = $this->required('domain');

        return str_contains($domain, '://') ? rtrim($domain, '/') : 'https://'.rtrim($domain, '/');
    }

    protected function visibility(): string
    {
        return $this->config('visibility', 'public') === 'private' ? 'private' : 'public';
    }

    protected function path(string $path): string
    {
        return ltrim($path, '/');
    }

    protected function isMissing(mixed $error): bool
    {
        return is_object($error) && method_exists($error, 'code') && (int) $error->code() === 612;
    }

    protected function errorPayload(mixed $error): array
    {
        if (is_object($error) && method_exists($error, 'code') && method_exists($error, 'message')) {
            return ['code' => $error->code(), 'message' => $error->message()];
        }

        return ['message' => is_scalar($error) ? (string) $error : 'unknown'];
    }

    protected function config(string $key, string $default = ''): string
    {
        $value = $this->configs[$key] ?? $default;

        return is_scalar($value) ? (string) $value : $default;
    }

    protected function required(string $key): string
    {
        $value = $this->config($key);

        if ($value === '') {
            throw new ToolkitException('七牛云 OSS 配置不完整：'.$key);
        }

        return $value;
    }

    protected function loadConfigs(): array
    {
        try {
            $configs = function_exists('config') ? config('project.3rd_passports.qiniu.default', []) : [];

            return is_array($configs) ? $configs : [];
        } catch (Throwable) {
            return [];
        }
    }
}