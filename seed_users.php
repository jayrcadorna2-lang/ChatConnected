
<?php

require_once __DIR__ . '/config/database.php';

$pdo = db();

$users = [
    ['John Smith', 'johnsmith', 'john@gmail.com'],
    ['Maria Santos', 'mariasantos', 'maria@gmail.com'],
    ['James Wilson', 'jameswilson', 'james@gmail.com'],
    ['Angela Cruz', 'angelacruz', 'angela@gmail.com'],
    ['Michael Garcia', 'michaelgarcia', 'michael@gmail.com'],
];

$password = password_hash('Test@12345', PASSWORD_DEFAULT);

$stmt = $pdo->prepare("
    INSERT OR IGNORE INTO users
    (fullname, username, email, password)
    VALUES (?, ?, ?, ?)
");

foreach ($users as $user) {
    $stmt->execute([
        $user[0],
        $user[1],
        $user[2],
        $password
    ]);

    if ($stmt->rowCount() > 0) {
        echo "Created: {$user[1]}\n";
    } else {
        echo "Skipped (already exists): {$user[1]}\n";
    }
}

echo "\nTest account seeding completed!\n";
