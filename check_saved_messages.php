
<?php

require_once __DIR__ . '/config/database.php';

$pdo = db();

echo "TOTAL MESSAGES: ";
echo $pdo->query("SELECT COUNT(*) FROM messages")->fetchColumn();
echo "\n\n";

$columns = $pdo->query("PRAGMA table_info(messages)")
               ->fetchAll(PDO::FETCH_COLUMN, 1);

echo "MESSAGE COLUMNS:\n";
print_r($columns);

echo "\nSAVED MESSAGES:\n";

$messages = $pdo->query("SELECT * FROM messages ORDER BY id DESC LIMIT 10")
                ->fetchAll();

print_r($messages);
