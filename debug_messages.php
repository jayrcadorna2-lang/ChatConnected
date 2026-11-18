
<?php
require_once __DIR__ . '/config/database.php';

try {
    $pdo = db();

    echo "<h2>Messages Table Columns</h2>";

    $columns = $pdo->query("PRAGMA table_info(messages)")->fetchAll(PDO::FETCH_ASSOC);

    foreach ($columns as $column) {
        echo htmlspecialchars($column['name']) . "<br>";
    }

    echo "<h2>Latest Messages</h2>";

    $stmt = $pdo->query("
        SELECT id, conversation_id, sender_id, message, created_at
        FROM messages
        ORDER BY id DESC
        LIMIT 20
    ");

    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!$messages) {
        echo "No messages found in the database.";
    } else {
        echo "<pre>";
        print_r($messages);
        echo "</pre>";
    }

} catch (Throwable $e) {
    echo "ERROR: " . htmlspecialchars($e->getMessage());
}
