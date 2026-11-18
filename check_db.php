
<?php

require_once __DIR__ . '/config/database.php';

$pdo = db();

echo "<h2>ChatConnect Database Check</h2>";

echo "<h3>Actual Database Path</h3>";

$database = $pdo->query("PRAGMA database_list")->fetchAll();

echo "<pre>";
print_r($database);
echo "</pre>";

echo "<h3>Existing Tables</h3>";

$tables = $pdo->query("
    SELECT name
    FROM sqlite_master
    WHERE type = 'table'
    ORDER BY name
")->fetchAll(PDO::FETCH_COLUMN);

echo "<pre>";
print_r($tables);
echo "</pre>";
