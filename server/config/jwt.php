<?php

declare(strict_types=1);

return [
    // 会员端（小程序）
    'member' => [
        'secret' => (string)env('JWT_MEMBER_SECRET', ''),
        'ttl'    => (int)env('JWT_MEMBER_TTL', 604800),
        'alg'    => 'HS256',
    ],
    // 管理端（后台 / 店员）
    'admin' => [
        'secret' => (string)env('JWT_ADMIN_SECRET', ''),
        'ttl'    => (int)env('JWT_ADMIN_TTL', 43200),
        'alg'    => 'HS256',
    ],
];
