<?php
declare(strict_types=1);

return [
    'displayErrors' => true,
    'db' => [
        'path' => __DIR__ . '/../database/kai.sqlite3',
    ],
    'twig' => [
        'templatePath' => __DIR__ . '/../templates',
        'cache'        => false,
    ],
];
