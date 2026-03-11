<?php
declare(strict_types=1);

return [
    'displayErrors' => true,
    'db' => [
        'host'     => $_ENV['DB_HOST']     ?? getenv('DB_HOST')     ?: '127.0.0.1',
        'port'     => $_ENV['DB_PORT']     ?? getenv('DB_PORT')     ?: '3306',
        'name'     => $_ENV['DB_NAME']     ?? getenv('DB_NAME')     ?: 'kai',
        'user'     => $_ENV['DB_USER']     ?? getenv('DB_USER')     ?: 'root',
        'password' => $_ENV['DB_PASSWORD'] ?? getenv('DB_PASSWORD') ?: '',
    ],
    'twig' => [
        'templatePath' => __DIR__ . '/../templates',
        'cache'        => false,
    ],
];
