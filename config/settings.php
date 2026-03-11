<?php
declare(strict_types=1);

return [
    'displayErrors' => true,
    'db' => [
        'host'     => getenv('DB_HOST')     ?: '127.0.0.1',
        'port'     => getenv('DB_PORT')     ?: '3306',
        'name'     => getenv('DB_NAME')     ?: 'kai',
        'user'     => getenv('DB_USER')     ?: 'root',
        'password' => getenv('DB_PASSWORD') ?: '',
    ],
    'twig' => [
        'templatePath' => __DIR__ . '/../templates',
        'cache'        => false,
    ],
];
