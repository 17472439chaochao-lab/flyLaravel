<?php

declare(strict_types=1);

namespace Readfly\Laravel\Providers\Wechat\Concerns;

use Readfly\Laravel\Support\HttpClient;

trait FetchesAccessToken
{
    protected function fetchAccessToken(string $cache_key): array
    {
        $token = $this->cachedToken($cache_key, function (): ?string {
            $response = HttpClient::get('https://api.weixin.qq.com/cgi-bin/token', [
                'grant_type' => 'client_credential',
                'appid' => $this->configs['app_id'],
                'secret' => $this->configs['secret'],
            ]);

            $token = data_get($response, 'body.access_token');

            return is_string($token) && $token !== '' ? $token : null;
        });

        if ($token === null) {
            return ['ok' => false, 'token' => '', 'error' => 'access_token 获取失败'];
        }

        return ['ok' => true, 'token' => $token, 'error' => null];
    }
}