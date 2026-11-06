<?php

declare(strict_types=1);

namespace Readfly\Laravel\Providers\Wechat;

use Readfly\Laravel\Support\Code;
use Readfly\Laravel\Support\HttpClient;
use Readfly\Laravel\Providers\Concerns\CachesAccessToken;
use Readfly\Laravel\Providers\Wechat\Concerns\FetchesAccessToken;
use Readfly\Laravel\Support\ResponsePayload;

class MiniProgramingProvider
{
    use CachesAccessToken;
    use FetchesAccessToken;

    protected array $configs;

    public function __construct(array $configs = [])
    {
        $configured = (array) config('project.3rd_passports.tencent.wechat.mini_program', []);
        $this->configs = array_merge([
            'app_id' => '',
            'secret' => '',
            'token' => '',
            'aes_key' => '',
        ], $configs !== [] ? $configs : $configured);
    }

    public function code2Session(string $code): ResponsePayload
    {
        $response = HttpClient::get('https://api.weixin.qq.com/sns/jscode2session', [
            'appid' => $this->configs['app_id'],
            'secret' => $this->configs['secret'],
            'js_code' => $code,
            'grant_type' => 'authorization_code',
        ]);

        $body = is_array($response['body']) ? $response['body'] : [];

        if (! $response['ok'] || empty($body['openid'])) {
            return ResponsePayload::error(Code::UNAUTHORIZED, '小程序登录失败', $body);
        }

        return ResponsePayload::success([
            'open_id' => $body['openid'],
            'union_id' => $body['unionid'] ?? null,
            'session_key' => $body['session_key'] ?? null,
            'row' => $body,
        ], '小程序登录成功');
    }

    public function sendSubscribeMessage(string $open_id, string $template_id, array $data, string $page = '', string $state = 'formal'): ResponsePayload
    {
        $token = $this->fetchAccessToken($this->tokenKey());

        if (! $token['ok']) {
            return ResponsePayload::error(Code::LOGIC_ERROR, $token['error']);
        }

        $body = [
            'touser' => $open_id,
            'template_id' => $template_id,
            'page' => $page,
            'miniprogram_state' => $state,
            'lang' => 'zh_CN',
            'data' => $data,
        ];

        $response = HttpClient::post(
            'https://api.weixin.qq.com/cgi-bin/message/subscribe/send?access_token='.$token['token'],
            $body
        );

        $result = is_array($response['body']) ? $response['body'] : [];
        $success = $response['ok'] && (int) ($result['errcode'] ?? 0) === 0;

        if ($success) {
            return ResponsePayload::success($result, '发送成功');
        }

        if (in_array((int) ($result['errcode'] ?? 0), [40001, 42001], true)) {
            $this->forgetToken($this->tokenKey());
        }

        return ResponsePayload::error(Code::LOGIC_ERROR, (string) ($result['errmsg'] ?? '发送失败'), $result);
    }

    protected function tokenKey(): string
    {
        return 'wechat:mini_program:access_token:'.$this->configs['app_id'];
    }
}