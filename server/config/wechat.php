<?php

declare(strict_types=1);

return [
    'appid'  => (string)env('WECHAT_APPID', ''),
    'secret' => (string)env('WECHAT_SECRET', ''),

    'pay' => [
        'mch_id'     => (string)env('WECHAT_PAY_MCH_ID', ''),
        'serial_no'  => (string)env('WECHAT_PAY_SERIAL_NO', ''),
        'api_v3_key' => (string)env('WECHAT_PAY_API_V3_KEY', ''),
        'cert_path'  => (string)env('WECHAT_PAY_CERT_PATH', ''),
        'key_path'   => (string)env('WECHAT_PAY_KEY_PATH', ''),
        'notify_url' => (string)env('WECHAT_PAY_NOTIFY_URL', ''),
    ],
];
