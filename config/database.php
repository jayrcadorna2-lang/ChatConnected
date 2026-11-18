<?php


function db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $path = getenv('DB_PATH')
        ?: __DIR__ . '/../database/chatconnect.sqlite';

    $directory = dirname($path);

    if (!is_dir($directory)) {
        mkdir($directory, 0775, true);
    }

    $pdo = new PDO('sqlite:' . $path, null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    $pdo->exec('PRAGMA foreign_keys = ON');
    $pdo->exec('PRAGMA journal_mode = WAL');

    $schemaFile = __DIR__ . '/../database/chatconnect.sql';

    if (!file_exists($schemaFile)) {
        throw new RuntimeException('Database schema not found.');
    }

    $schema = file_get_contents($schemaFile);

    if ($schema === false || trim($schema) === '') {
        throw new RuntimeException('Database schema is empty.');
    }

    $pdo->exec($schema);

    return $pdo;
}
