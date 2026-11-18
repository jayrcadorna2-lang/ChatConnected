
<?php

require_once __DIR__ . '/config/database.php';

$pdo = db();

$columns = $pdo->query("PRAGMA table_info(users)")->fetchAll(PDO::FETCH_COLUMN, 1);

if (!in_array('last_seen', $columns, true)) {
    $pdo->exec("ALTER TABLE users ADD COLUMN last_seen DATETIME DEFAULT NULL");
    echo "Added last_seen column successfully!\n";
} else {
    echo "last_seen column already exists.\n";
}

$pdo->exec("
    UPDATE users
    SET last_seen = CURRENT_TIMESTAMP
    WHERE last_seen IS NULL
");

echo "Database migration completed successfully!\n";
