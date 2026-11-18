
<?php

require_once __DIR__ . '/config/database.php';

$pdo = db();

$columns = $pdo->query("PRAGMA table_info(messages)")
               ->fetchAll(PDO::FETCH_COLUMN, 1);

if (!in_array('body', $columns, true)) {
    $pdo->exec("ALTER TABLE messages ADD COLUMN body TEXT");
    echo "Successfully added body column!\n";
} else {
    echo "Body column already exists!\n";
}

echo "Message database update completed!\n";
