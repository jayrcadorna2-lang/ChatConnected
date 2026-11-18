
<?php

require_once __DIR__ . '/config/database.php';

$pdo = db();

echo "MESSAGES TABLE COLUMNS:\n\n";

$columns = $pdo->query("PRAGMA table_info(messages)")->fetchAll();

foreach ($columns as $column) {
    echo $column['name'] . " (" . $column['type'] . ")\n";
}
