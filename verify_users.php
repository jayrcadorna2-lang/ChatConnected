
<?php

require_once __DIR__ . '/config/database.php';

$users = db()->query("
    SELECT id, fullname, username, email, password
    FROM users
")->fetchAll();

foreach ($users as $user) {
    echo "ID: " . $user['id'] . "\n";
    echo "Name: " . $user['fullname'] . "\n";
    echo "Username: " . $user['username'] . "\n";
    echo "Email: " . $user['email'] . "\n";
    echo "Password hash: " .
         (password_get_info($user['password'])['algo'] !== null
         ? 'Valid hash format' : 'Not recognized') . "\n";
    echo "-------------------------\n";
}
