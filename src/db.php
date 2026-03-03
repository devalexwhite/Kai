<?php
declare(strict_types=1);

/**
 * Return a singleton PDO instance connected to the SQLite database.
 * Bootstraps the schema on first connection.
 */
function get_db(): PDO
{
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }

    $dbDir  = __DIR__ . '/../database';
    $dbPath = $dbDir . '/kai.sqlite';

    if (!is_dir($dbDir)) {
        mkdir($dbDir, 0755, true);
    }

    $pdo = new PDO('sqlite:' . $dbPath, null, null, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);

    $pdo->exec('PRAGMA journal_mode = WAL');
    $pdo->exec('PRAGMA foreign_keys = ON');

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS users (
            id         INTEGER PRIMARY KEY AUTOINCREMENT,
            name       TEXT    NOT NULL,
            email      TEXT    NOT NULL UNIQUE COLLATE NOCASE,
            password   TEXT    NOT NULL,
            created_at TEXT    NOT NULL DEFAULT (datetime('now'))
        );

        CREATE TABLE IF NOT EXISTS remember_tokens (
            id         INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id    INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
            selector   TEXT    NOT NULL UNIQUE,
            token_hash TEXT    NOT NULL,
            expires_at TEXT    NOT NULL,
            created_at TEXT    NOT NULL DEFAULT (datetime('now'))
        );

        CREATE INDEX IF NOT EXISTS idx_remember_selector ON remember_tokens(selector);
        CREATE INDEX IF NOT EXISTS idx_users_email       ON users(email);
    ");

    return $pdo;
}
