<?php
declare(strict_types=1);

return [
    'default' => [
        'transport' => 'smtp',
        'host'       => env('MAIL_HOST', 'mailhog'),
        'port'       => (int) env('MAIL_PORT', 1025),
        'encryption' => env('MAIL_ENCRYPTION', ''),
        'username'   => env('MAIL_USERNAME', ''),
        'password'   => env('MAIL_PASSWORD', ''),
        'from'       => [
            'address' => env('MAIL_FROM_ADDRESS', 'noreply@casepix.com'),
            'name'    => env('MAIL_FROM_NAME', 'CasePix'),
        ],
    ],
];
