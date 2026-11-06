<?php

declare(strict_types=1);

namespace Readfly\Laravel\Providers\Aliyun;

use Overtrue\EasySms\EasySms;
use Overtrue\EasySms\Exceptions\NoGatewayAvailableException;
use Overtrue\EasySms\Strategies\OrderStrategy;
use Readfly\Laravel\Contracts\SmsSender;
use Readfly\Laravel\Exceptions\ToolkitException;
use Readfly\Laravel\Support\Logger;
use Readfly\Laravel\Support\Path;
use Throwable;

class SmsProvider implements SmsSender
{
    protected array $configs;

    public function __construct(array $configs = [])
    {
        $configured = (array) config('project.3rd_passports.aliyun.sms', []);
        $this->configs = $configs !== [] ? $configs : $configured;
    }

    public static function make(string $access_key_id, string $access_key_secret, string $sign_name): static
    {
        return new static(compact('access_key_id', 'access_key_secret', 'sign_name'));
    }

    public static function makeByConfigs(array $configs): static
    {
        return new static($configs);
    }

    public function send(string $mobile, array $data = [], string $template = '', string $content = ''): bool
    {
        if (! class_exists(EasySms::class)) {
            throw new ToolkitException('缺少依赖 overtrue/easy-sms，请执行 composer require overtrue/easy-sms');
        }

        $handler = new EasySms([
            'timeout' => 5.0,
            'default' => [
                'strategy' => OrderStrategy::class,
                'gateways' => ['aliyun'],
            ],
            'gateways' => [
                'errlog' => Path::logs('easy-sms/'.date('Y-m-d').'.log'),
                'aliyun' => $this->configs,
            ],
        ]);

        try {
            $handler->send($mobile, [
                'content' => $content,
                'template' => $template,
                'data' => $data,
            ]);

            return true;
        } catch (NoGatewayAvailableException $exception) {
            Logger::record('sms:aliyun:no_gateway', ['exception' => $exception, 'mobile' => $mobile, 'template' => $template]);
        } catch (Throwable $exception) {
            Logger::record('sms:aliyun:failed', ['exception' => $exception, 'mobile' => $mobile, 'template' => $template]);
        }

        return false;
    }
}