<?php
require_once __DIR__ . '/config/bootstrap.php';
if (!empty($_SESSION['user_id'])) {
    db()->prepare("UPDATE users SET status='offline',last_seen=CURRENT_TIMESTAMP WHERE id=?")->execute([$_SESSION['user_id']]);
}
$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
}
session_destroy();
header('Location: login.php');
exit;
