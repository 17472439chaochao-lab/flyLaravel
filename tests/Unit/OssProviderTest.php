<?php

declare(strict_types=1);

namespace Readfly\Laravel\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Readfly\Laravel\Exceptions\ToolkitException;
use Readfly\Laravel\Providers\Aliyun\OssProvider as AliyunOssProvider;
use Readfly\Laravel\Providers\AliyunProvider;
use Readfly\Laravel\Providers\Qiniu\OssProvider as QiniuOssProvider;
use Readfly\Laravel\Providers\QiniuProvider;

class OssProviderTest extends TestCase
{
    protected function aliyunConfigs(array $overrides = []): array
    {
        return array_merge([
            'access_key_id' => 'test-access-key-id',
            'access_key_secret' => 'test-access-key-secret',
            'bucket' => 'demo-bucket',
            'endpoint' => 'oss-cn-hangzhou.aliyuncs.com',
            'domain' => '',
            'is_cname' => false,
            'visibility' => 'public',
        ], $overrides);
    }

    public function test_aliyun_public_url_uses_domain(): void
    {
        $provider = AliyunProvider::oss($this->aliyunConfigs(['domain' => 'https://cdn.example.com/']));

        $this->assertSame('https://cdn.example.com/upload/a.png', $provider->url('upload/a.png'));
    }

    public function test_aliyun_public_url_falls_back_to_bucket_endpoint(): void
    {
        $provider = AliyunProvider::oss($this->aliyunConfigs());

        $this->assertSame('https://demo-bucket.oss-cn-hangzhou.aliyuncs.com/upload/a.png', $provider->url('/upload/a.png'));
    }

    public function test_aliyun_custom_domain_is_used_as_host(): void
    {
        $provider = AliyunProvider::oss($this->aliyunConfigs([
            'endpoint' => 'img.example.com',
            'is_cname' => true,
        ]));

        $this->assertSame('https://img.example.com/a.png', $provider->url('a.png'));
    }

    public function test_aliyun_post_policy_is_signed(): void
    {
        $provider = AliyunProvider::oss($this->aliyunConfigs());

        $policy = $provider->postPolicy('uploads/2026', 3600);

        $decoded = json_decode((string) base64_decode($policy['policy']), true);

        $this->assertSame('demo-bucket', $decoded['conditions'][0]['bucket']);
        $this->assertSame(['content-length-range', 0, 104857600], $decoded['conditions'][1]);
        $this->assertSame(['starts-with', '$key', 'uploads/2026/'], $decoded['conditions'][2]);
        $this->assertSame('uploads/2026/', $policy['dir']);
        $this->assertSame('https://demo-bucket.oss-cn-hangzhou.aliyuncs.com', $policy['host']);
        $this->assertSame('test-access-key-id', $policy['access_key_id']);
        $this->assertSame(
            base64_encode(hash_hmac('sha1', $policy['policy'], 'test-access-key-secret', true)),
            $policy['signature']
        );
        $this->assertSame(gmdate('Y-m-d\TH:i:s.000\Z', $policy['expire_at']), $decoded['expiration']);
    }

    public function test_aliyun_post_policy_without_dir(): void
    {
        $provider = AliyunProvider::oss($this->aliyunConfigs());

        $policy = $provider->postPolicy();

        $this->assertSame('', $policy['dir']);
        $this->assertCount(2, json_decode((string) base64_decode($policy['policy']), true)['conditions']);
    }

    public function test_aliyun_requires_bucket(): void
    {
        $provider = new AliyunOssProvider($this->aliyunConfigs(['bucket' => '']));

        $this->expectException(ToolkitException::class);
        $this->expectExceptionMessage('阿里云 OSS 配置不完整：bucket');

        $provider->url('a.png');
    }

    public function test_qiniu_public_url_uses_domain(): void
    {
        $provider = QiniuProvider::oss([
            'domain' => 'cdn.example.com',
            'visibility' => 'public',
        ]);

        $this->assertSame('https://cdn.example.com/upload/a.png', $provider->url('upload/a.png'));
    }

    public function test_qiniu_domain_keeps_scheme(): void
    {
        $provider = QiniuProvider::storage([
            'domain' => 'http://cdn.example.com/',
            'visibility' => 'public',
        ]);

        $this->assertSame('http://cdn.example.com/a.png', $provider->url('/a.png'));
    }

    public function test_qiniu_requires_domain(): void
    {
        $provider = new QiniuOssProvider([]);

        $this->expectException(ToolkitException::class);
        $this->expectExceptionMessage('七牛云 OSS 配置不完整：domain');

        $provider->url('a.png');
    }
}