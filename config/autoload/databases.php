<?php
declare(strict_types=1);

return [
    'default' => [
        'driver'    => env('DB_DRIVER', 'mysql'),
        'host'      => env('DB_HOST', 'mysql'),
        'database'  => env('DB_DATABASE', 'saque_pix'),
        'port'      => (int) env('DB_PORT', 3306),
        'username'  => env('DB_USERNAME', 'app'),
        'password'  => env('DB_PASSWORD', ''),
        'charset'   => 'utf8mb4',
        'collation' => 'utf8mb4_unicode_ci',
        'prefix'    => '',
        'pool'      => [
            'min_connections' => 1,
            'max_connections' => 10,
            'connect_timeout' => 10.0,
            'wait_timeout'    => 3.0,
            'heartbeat'       => -1,
            'max_idle_time'   => 60.0,
        ],
        'migrations' => 'migrations',
        'timezone'   => 'UTC',
    ],
];
