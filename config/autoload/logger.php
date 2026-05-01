<?php
declare(strict_types=1);

use Monolog\Formatter\JsonFormatter;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;

return [
    'default' => [
        'handler' => [
            'class'       => StreamHandler::class,
            'constructor' => [
                'stream' => 'php://stdout',
                'level'  => Logger::DEBUG,
            ],
        ],
        'formatter' => [
            'class' => JsonFormatter::class,
        ],
    ],
];
