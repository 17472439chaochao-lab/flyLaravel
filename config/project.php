<?php

// __DEFAULT_USE_USAGE__

return [

    'domains' => [

        // 'apis' => env('DOMAIN_OF_API', 'https://apis.domain.com'),
        // 'www' => env('DOMAIN_OF_WWW', 'https://www.domain.com'),
        // 'h5' => env('DOMAIN_OF_H5', 'https://m.domain.com'),

    ],

    'signature' => [
        'app_key' => '__APP_KEY__',
        'app_secret' => '__APP_SECRET__',
    ],

    'aes' => [
        'iv' => '__AES_IV__',
        'encrypt_key' => '__AES_ENCRYPT_KEY__',
    ],

    '3rd_passports' => [
        'qiniu' => [
            'default' => [
                'domain' => '',
                'access_key' => '',
                'access_secret' => '',
                'bucket' => '',
                'visibility' => 'public',
            ],
        ],
        'aliyun' => [
            'sms' => [
                'access_key_id' => '',
                'access_key_secret' => '',
                'sign_name' => '',
                'templates' => [
                    'authenticate' => [
                        'content' => '您的验证码为：${code}，请勿泄露于他人！',
                        'template_id' => 'SMS_XXXXXXXXXX',
                    ],
                ],
            ],
            'oss' => [
                'access_key_id' => '',
                'access_key_secret' => '',
                'bucket' => '',
                'endpoint' => 'oss-cn-hangzhou.aliyuncs.com',
                'domain' => '',
                'is_cname' => false,
                'visibility' => 'public',
            ],
        ],
        'tencent' => [
            'wechat' => [
                'official_account' => [
                    'app_id' => '',
                    'secret' => '',
                    'token' => '',
                    'aes_key' => '',
                    'templates' => [],
                ],
                'mini_program' => [
                    'app_id' => '',
                    'secret' => '',
                    'token' => '',
                    'aes_key' => '',
                ],
                'open_platform' => [
                    'app_id' => '',
                    'secret' => '',
                    'token' => '',
                    'aes_key' => '',
                ],
                'payment' => [
                    'mch_id' => '',
                    'private_key' => __DIR__.'/certs/apiclient_key.pem',
                    'certificate' => __DIR__.'/certs/apiclient_cert.pem',
                    'secret_key' => '',
                    'v2_secret_key' => '',
                    'platform_certs' => [],
                ],
            ],
        ],
        'bytedance' => [
            'douyin' => [
                'app_id' => '',
                'secret' => '',
                'token' => '',
                'aes_key' => '',
            ],
        ],
        'kd100' => [
            'secret_key' => '',
            'secret_code' => '',
            'secret_sign' => '',
        ],
        'amap' => [
            'web_server_api_key' => '',
        ],
    ],

    'others' => [

    ],

];