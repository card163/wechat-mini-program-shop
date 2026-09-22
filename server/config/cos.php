<?php

declare(strict_types=1);

return [
    'secret_id'  => (string)env('COS_SECRET_ID', ''),
    'secret_key' => (string)env('COS_SECRET_KEY', ''),
    'region'     => (string)env('COS_REGION', 'ap-guangzhou'),
    'bucket'     => (string)env('COS_BUCKET', ''),
    // 对外访问域名（自定义CDN加速域名或COS默认域名），不含末尾斜杠，如 https://cos1.dwj.la
    'domain'     => rtrim((string)env('COS_DOMAIN', ''), '/'),
];
