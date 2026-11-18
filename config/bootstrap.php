
<?php

if (session_status() !== PHP_SESSION_ACTIVE) {
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');

    session_set_cookie_params([
        'httponly' => true,
        'secure' => $secure,
        'samesite' => 'Lax',
    ]);

    session_start();
}

require_once __DIR__ . '/database.php';

function e(?string $value): string {
    return htmlspecialchars(
        $value ?? '',
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}


function current_user(): ?array {
    if (empty($_SESSION['user_id'])) {
        return null;
    }

    $stmt = db()->prepare(
        'SELECT
            id,
            fullname,
            username,
            email,
            avatar,
            bio,
            status,
            role
         FROM users
         WHERE id = ?'
    );

    $stmt->execute([$_SESSION['user_id']]);

    $user = $stmt->fetch();

    return $user ?: null;
}


function require_login(): array {
    $user = current_user();

    if (!$user) {
        header('Location: login.php');
        exit;
    }

    return $user;
}

function csrf_token(): string {
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf'];
}

function verify_csrf(): void {
    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['csrf'] ?? '');

    if (!$token || !hash_equals(csrf_token(), $token)) {
        http_response_code(419);
        exit('Invalid security token. Refresh the page and try again.');
    }
}

function json_response(array $data, int $status = 200): never {
    http_response_code($status);

    header('Content-Type: application/json; charset=utf-8');

    echo json_encode(
        $data,
        JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
    );

    exit;
}

function is_member(int $conversationId, int $userId): bool {
    $stmt = db()->prepare(
        'SELECT 1
         FROM conversation_members
         WHERE conversation_id = ? AND user_id = ?'
    );

    $stmt->execute([$conversationId, $userId]);

    return (bool)$stmt->fetchColumn();
}
