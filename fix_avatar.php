
<?php

require_once __DIR__ . '/config/database.php';

$pdo = db();

$columns = $pdo->query("PRAGMA table_info(users)")
               ->fetchAll(PDO::FETCH_COLUMN, 1);

if (!in_array('avatar', $columns, true)) {
    $pdo->exec("ALTER TABLE users ADD COLUMN avatar TEXT DEFAULT NULL");
    echo "Avatar column added successfully!\n";
} else {
    echo "Avatar column already exists.\n";
}

echo "Database update completed!\n";
