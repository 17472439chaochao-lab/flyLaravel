<?php

declare(strict_types=1);

namespace Readfly\Laravel\Providers\Wechat;

use Readfly\Laravel\Support\Code;
use Readfly\Laravel\Support\HttpClient;
use Readfly\Laravel\Providers\Concerns\CachesAccessToken;
use Readfly\Laravel\Providers\Wechat\Concerns\FetchesAccessToken;
use Readfly\Laravel\Support\ResponsePayload;

class OfficeAccountProvider
{
    use CachesAccessToken;
    use FetchesAccessToken;

    protected array $configs;

    public function __construct(array $configs = [])
    {
        $configured = (array) config('project.3rd_passports.tencent.wechat.official_account', []);
        $this->configs = array_merge([
            'app_id' => '',
            'secret' => '',
            'token' => '',
            'aes_key' => '',
            'templates' => [],
        ], $configs !== [] ? $configs : $configured);
    }

    public function sendTemplate(string $uuid, string $open_id, array $data, string $alias, array $extras = []): ResponsePayload
    {
        $template_id = (string) data_get($this->configs, 'templates.'.$alias, '');

        if ($template_id === '') {
            return ResponsePayload::error(Code::LOGIC_ERROR, '未配置模板消息：'.$alias);
        }

        $body = array_merge([
            'touser' => $open_id,
            'template_id' => $template_id,
            'client_msg_id' => $uuid,
            'data' => $data,
        ], $extras);

        $result = $this->postWithToken('/cgi-bin/message/template/send', $body);

        if (! $result['ok']) {
            return ResponsePayload::error(Code::LOGIC_ERROR, '发送失败：'.$result['error'], $result['body']);
        }

        return ResponsePayload::success($result['body'], '发送成功');
    }

    public function authorize(string $redirect_uri, array $scopes = ['snsapi_userinfo']): ResponsePayload
    {
        $query = http_build_query([
            'appid' => $this->configs['app_id'],
            'redirect_uri' => $redirect_uri,
            'response_type' => 'code',
            'scope' => implode(',', $scopes),
            'state' => md5($redirect_uri.microtime(true)),
        ]);

        return ResponsePayload::success([
            'url' => 'https://open.weixin.qq.com/connect/oauth2/authorize?'.$query.'#wechat_redirect',
        ], '生成授权链接成功');
    }

    public function authorizeUserInfo(string $code): ResponsePayload
    {
        $token = HttpClient::get('https://api.weixin.qq.com/sns/oauth2/access_token', [
            'appid' => $this->configs['app_id'],
            'secret' => $this->configs['secret'],
            'code' => $code,
            'grant_type' => 'authorization_code',
        ]);

        $body = is_array($token['body']) ? $token['body'] : [];

        if (! $token['ok'] || empty($body['access_token']) || empty($body['openid'])) {
            return ResponsePayload::error(Code::LOGIC_ERROR, '获取用户授权失败', $body);
        }

        $user = HttpClient::get('https://api.weixin.qq.com/sns/userinfo', [
            'access_token' => $body['access_token'],
            'openid' => $body['openid'],
            'lang' => 'zh_CN',
        ]);

        $userBody = is_array($user['body']) ? $user['body'] : [];

        return ResponsePayload::success([
            'open_id' => $userBody['openid'] ?? $body['openid'],
            'union_id' => $userBody['unionid'] ?? null,
            'nickname' => $userBody['nickname'] ?? '',
            'avatar' => $userBody['headimgurl'] ?? '',
            'row' => $userBody,
        ], '获取用户信息成功');
    }

    protected function postWithToken(string $path, array $body): array
    {
        $result = $this->requestWithToken($path, $body);

        if (in_array((int) data_get($result, 'body.errcode', 0), [40001, 42001], true)) {
            $this->forgetToken($this->tokenKey());
            $result = $this->requestWithToken($path, $body);
        }

        return $result;
    }

    protected function requestWithToken(string $path, array $body): array
    {
        $token = $this->fetchAccessToken($this->tokenKey());

        if (! $token['ok']) {
            return ['ok' => false, 'body' => null, 'error' => $token['error']];
        }

        $response = HttpClient::post('https://api.weixin.qq.com'.$path.'?access_token='.$token['token'], $body);
        $body = is_array($response['body']) ? $response['body'] : [];
        $success = $response['ok'] && (int) ($body['errcode'] ?? 0) === 0;

        return [
            'ok' => $success,
            'body' => $body,
            'error' => $success ? null : (string) ($body['errmsg'] ?? $response['error'] ?? 'wechat request failed'),
        ];
    }

    protected function tokenKey(): string
    {
        return 'wechat:official_account:access_token:'.$this->configs['app_id'];
    }
}