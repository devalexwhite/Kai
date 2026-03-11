<?php
declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->safeLoad();

return [
    'paths' => [
        'migrations' => '%%PHINX_CONFIG_DIR%%/database/migrations',
        'seeds'      => '%%PHINX_CONFIG_DIR%%/database/seeds',
    ],
    'environments' => [
        'default_migration_table' => 'phinxlog',
        'default_environment'     => 'development',
        'development' => [
            'adapter' => 'mysql',
            'host'    => getenv('DB_HOST')     ?: '127.0.0.1',
            'name'    => getenv('DB_NAME')     ?: 'kai',
            'user'    => getenv('DB_USER')     ?: 'root',
            'pass'    => getenv('DB_PASSWORD') ?: '',
            'port'    => getenv('DB_PORT')     ?: '3306',
            'charset' => 'utf8mb4',
        ],
    ],
    'version_order' => 'creation',
];
