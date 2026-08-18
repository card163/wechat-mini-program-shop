<?php

declare(strict_types=1);

return [
    'default' => env('DB_CONNECTION', 'mysql'),

    'connections' => [
        'mysql' => [
            'driver'    => 'mysql',
            'host'      => env('DB_HOST', '127.0.0.1'),
            'port'      => (int)env('DB_PORT', 3306),
            'database'  => env('DB_DATABASE', 'nf-shop'),
            'username'  => env('DB_USERNAME', 'root'),
            'password'  => (string)env('DB_PASSWORD', ''),
            'unix_socket' => '',
            'charset'   => 'utf8mb4',
            'collation' => 'utf8mb4_general_ci',
            'prefix'    => (string)env('DB_PREFIX', ''),
            'strict'    => true,
            'engine'    => null,
            'options'   => [
                PDO::ATTR_TIMEOUT => 3,
            ],
        ],
    ],
];
