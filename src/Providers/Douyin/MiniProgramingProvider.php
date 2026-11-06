<?php

declare(strict_types=1);

namespace Readfly\Laravel\Providers\Douyin;

use Readfly\Laravel\Providers\Concerns\CachesAccessToken;
use Readfly\Laravel\Support\Code;
use Readfly\Laravel\Support\HttpClient;
use Readfly\Laravel\Support\ResponsePayload;

class MiniProgramingProvider
{
    use CachesAccessToken;

    protected array $configs;

    public function __construct(array $configs = [])
    {
        $configured = (array) config('project.3rd_passports.bytedance.douyin', []);
        $this->configs = array_merge([
            'app_id' => '',
            'secret' => '',
            'token' => '',
            'aes_key' => '',
        ], $configs !== [] ? $configs : $configured);
    }

    public function code2Session(string $code): ResponsePayload
    {
        $response = HttpClient::post('https://developer.toutiao.com/api/apps/v2/jscode2session', [
            'appid' => $this->configs['app_id'],
            'secret' => $this->configs['secret'],
            'code' => $code,
        ]);

        $body = is_array($response['body']) ? $response['body'] : [];
        $data = (array) ($body['data'] ?? $body);

        if (! $response['ok'] || empty($data['openid'])) {
            return ResponsePayload::error(Code::UNAUTHORIZED, '抖音小程序登录失败', $body);
        }

        return ResponsePayload::success([
            'open_id' => $data['openid'],
            'anonymous_openid' => $data['anonymous_openid'] ?? null,
            'session_key' => $data['session_key'] ?? null,
            'union_id' => $data['unionid'] ?? null,
            'row' => $data,
        ], '抖音小程序登录成功');
    }

    public function accessToken(): ResponsePayload
    {
        $key = 'douyin:client_token:'.$this->configs['app_id'];
        $expires_in = 7200;

        $token = $this->cachedToken($key, function () use (&$expires_in): ?string {
            $response = HttpClient::post('https://open.douyin.com/oauth/client_token/', [
                'client_key' => $this->configs['app_id'],
                'client_secret' => $this->configs['secret'],
                'grant_type' => 'client_credential',
            ]);

            $data = (array) data_get($response, 'body.data', []);
            $token = $data['access_token'] ?? null;
            $expires_in = (int) ($data['expires_in'] ?? 7200);

            return is_string($token) && $token !== '' ? $token : null;
        }, 6900);

        if ($token === null) {
            return ResponsePayload::error(Code::LOGIC_ERROR, '抖音 access_token 获取失败');
        }

        return ResponsePayload::success(['access_token' => $token, 'expires_in' => $expires_in]);
    }
}