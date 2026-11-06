<?php

declare(strict_types=1);

namespace Readfly\Laravel\Providers\Aliyun;

use OSS\OssClient;
use Readfly\Laravel\Contracts\OssUploader;
use Readfly\Laravel\Exceptions\ToolkitException;
use Readfly\Laravel\Support\Logger;
use Throwable;

class OssProvider implements OssUploader
{
    protected array $configs;

    public function __construct(array $configs = [])
    {
        $configured = $this->loadConfigs();

        $this->configs = $configs !== [] ? $configs : $configured;
    }

    public static function make(
        string $access_key_id,
        string $access_key_secret,
        string $bucket,
        string $endpoint,
        string $domain = '',
        bool $is_cname = false,
        string $visibility = 'public'
    ): static {
        return new static(compact('access_key_id', 'access_key_secret', 'bucket', 'endpoint', 'domain', 'is_cname', 'visibility'));
    }

    public static function makeByConfigs(array $configs): static
    {
        return new static($configs);
    }

    public function put(string $path, mixed $content, array $options = []): bool
    {
        $client = $this->client();

        try {
            $client->putObject($this->bucket(), $this->path($path), $content, $this->headers($options));

            return true;
        } catch (Throwable $exception) {
            Logger::record('oss:aliyun:put_failed', ['exception' => $exception, 'path' => $path]);
        }

        return false;
    }

    public function putFile(string $path, string $local_path, array $options = []): bool
    {
        if (! is_file($local_path)) {
            Logger::record('oss:aliyun:file_missing', ['path' => $path, 'local_path' => $local_path]);

            return false;
        }

        $client = $this->client();

        try {
            $client->uploadFile($this->bucket(), $this->path($path), $local_path, $this->headers($options));

            return true;
        } catch (Throwable $exception) {
            Logger::record('oss:aliyun:put_file_failed', ['exception' => $exception, 'path' => $path, 'local_path' => $local_path]);
        }

        return false;
    }

    public function delete(string $path): bool
    {
        $client = $this->client();

        try {
            $client->deleteObject($this->bucket(), $this->path($path));

            return true;
        } catch (Throwable $exception) {
            Logger::record('oss:aliyun:delete_failed', ['exception' => $exception, 'path' => $path]);
        }

        return false;
    }

    public function exists(string $path): bool
    {
        $client = $this->client();

        try {
            return (bool) $client->doesObjectExist($this->bucket(), $this->path($path));
        } catch (Throwable $exception) {
            Logger::record('oss:aliyun:exists_failed', ['exception' => $exception, 'path' => $path]);
        }

        return false;
    }

    public function url(string $path, int $expires = 0): string
    {
        $path = $this->path($path);

        if ($expires > 0 || $this->visibility() === 'private') {
            return (string) $this->client()->signUrl($this->bucket(), $path, $expires > 0 ? $expires : 3600);
        }

        return $this->host().'/'.$path;
    }

    public function postPolicy(string $path = '', int $expires = 3600, array $conditions = []): array
    {
        $expire_at = time() + $expires;
        $dir = trim($path, '/');
        $dir = $dir === '' ? '' : $dir.'/';

        $policy_conditions = array_merge([
            ['bucket' => $this->bucket()],
            ['content-length-range', 0, 104857600],
        ], $conditions);

        if ($dir !== '') {
            $policy_conditions[] = ['starts-with', '$key', $dir];
        }

        $policy = base64_encode((string) json_encode([
            'expiration' => gmdate('Y-m-d\TH:i:s.000\Z', $expire_at),
            'conditions' => $policy_conditions,
        ]));

        return [
            'host' => $this->uploadHost(),
            'access_key_id' => $this->config('access_key_id'),
            'policy' => $policy,
            'signature' => base64_encode(hash_hmac('sha1', $policy, $this->required('access_key_secret'), true)),
            'dir' => $dir,
            'expire_at' => $expire_at,
        ];
    }

    protected function client(): OssClient
    {
        if (! class_exists(OssClient::class)) {
            throw new ToolkitException('缺少依赖 aliyuncs/oss-sdk-php，请执行 composer require aliyuncs/oss-sdk-php');
        }

        return new OssClient(
            $this->required('access_key_id'),
            $this->required('access_key_secret'),
            $this->endpoint(),
            $this->cname()
        );
    }

    protected function bucket(): string
    {
        return $this->required('bucket');
    }

    protected function endpoint(): string
    {
        $endpoint = $this->config('endpoint');

        if ($endpoint === '' && $this->cname()) {
            $endpoint = $this->domain();
        }

        if ($endpoint === '') {
            throw new ToolkitException('阿里云 OSS 配置不完整：endpoint');
        }

        if (! str_contains($endpoint, '://')) {
            $endpoint = 'https://'.$endpoint;
        }

        return rtrim($endpoint, '/');
    }

    protected function endpointHost(): string
    {
        $host = (string) parse_url($this->endpoint(), PHP_URL_HOST);

        return $host !== '' ? $host : trim((string) preg_replace('#^https?://#', '', $this->endpoint()), '/');
    }

    protected function domain(): string
    {
        return rtrim($this->config('domain'), '/');
    }

    protected function cname(): bool
    {
        return filter_var($this->configs['is_cname'] ?? false, FILTER_VALIDATE_BOOLEAN);
    }

    protected function visibility(): string
    {
        return $this->config('visibility', 'public') === 'private' ? 'private' : 'public';
    }

    protected function host(): string
    {
        if ($this->domain() !== '') {
            return $this->domain();
        }

        return $this->uploadHost();
    }

    protected function uploadHost(): string
    {
        $host = $this->endpointHost();

        return $this->cname() ? 'https://'.$host : 'https://'.$this->bucket().'.'.$host;
    }

    protected function path(string $path): string
    {
        return ltrim($path, '/');
    }

    protected function headers(array $options): array
    {
        if (isset($options['content_type'])) {
            $options[OssClient::OSS_CONTENT_TYPE] = (string) $options['content_type'];
            unset($options['content_type']);
        }

        return $options;
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
            throw new ToolkitException('阿里云 OSS 配置不完整：'.$key);
        }

        return $value;
    }

    protected function loadConfigs(): array
    {
        try {
            $configs = function_exists('config') ? config('project.3rd_passports.aliyun.oss', []) : [];

            return is_array($configs) ? $configs : [];
        } catch (Throwable) {
            return [];
        }
    }
}