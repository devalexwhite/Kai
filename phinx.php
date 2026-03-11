<?php
declare(strict_types=1);

require_once __DIR__ . "/vendor/autoload.php";

$dotenv = Dotenv\Dotenv::createMutable(__DIR__);
$dotenv->safeLoad();

return [
    "paths" => [
        "migrations" => "%%PHINX_CONFIG_DIR%%/database/migrations",
        "seeds" => "%%PHINX_CONFIG_DIR%%/database/seeds",
    ],
    "environments" => [
        "default_migration_table" => "phinxlog",
        "default_environment" => "development",
        "development" => [
            "adapter" => "mysql",
            "host" => $_ENV["DB_HOST"] ?? getenv("DB_HOST") ?: "127.0.0.1",
            "name" => $_ENV["DB_NAME"] ?? getenv("DB_NAME") ?: "kai",
            "user" => $_ENV["DB_USER"] ?? getenv("DB_USER") ?: "root",
            "pass" => $_ENV["DB_PASSWORD"] ?? getenv("DB_PASSWORD") ?: "",
            "port" => $_ENV["DB_PORT"] ?? getenv("DB_PORT") ?: "3306",
            "charset" => "utf8mb4",
        ],
    ],
    "version_order" => "creation",
];
