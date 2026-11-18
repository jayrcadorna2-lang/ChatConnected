
<?php
require_once __DIR__ . '/config/database.php';

$pdo = db();

$columns = $pdo->query("PRAGMA table_info(users)")
                ->fetchAll(PDO::FETCH_COLUMN, 1);

if (!in_array('bio', $columns, true)) {
    $pdo->exec("ALTER TABLE users ADD COLUMN bio TEXT DEFAULT NULL");
    echo "Bio column added successfully!\n";
} else {
    echo "Bio column already exists!\n";
}

echo "Migration completed!\n";
