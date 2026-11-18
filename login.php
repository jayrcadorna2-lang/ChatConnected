<?php
require_once __DIR__ . '/config/bootstrap.php';
if (current_user()) { header('Location: app.php'); exit; }

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $identity = trim($_POST['identity'] ?? '');
    $password = $_POST['password'] ?? '';
    $stmt = db()->prepare('SELECT id,password FROM users WHERE email = ? OR username = ? LIMIT 1');
    $stmt->execute([strtolower($identity), strtolower($identity)]);
    $account = $stmt->fetch();

    if ($account && password_verify($password, $account['password'])) {
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int)$account['id'];
        db()->prepare("UPDATE users SET status='online',last_seen=CURRENT_TIMESTAMP WHERE id=?")->execute([$account['id']]);
        header('Location: app.php'); exit;
    }
    $error = 'Incorrect email/username or password.';
}
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Sign in · ChatConnect</title><link rel="stylesheet" href="assets/css/style.css"></head>
<body class="auth-page"><main class="auth-card"><a class="brand brand-center" href="index.php"><span class="brand-mark">C</span> ChatConnect</a><p class="eyebrow">WELCOME BACK</p><h1>Good to see you.</h1><p class="muted">Sign in to pick up where you left off.</p>
<?php if ($error): ?><div class="alert"><?=e($error)?></div><?php endif; ?>
<form method="post" class="auth-form"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><label>Email or username<input name="identity" required autocomplete="username" value="<?=e($_POST['identity'] ?? '')?>"></label><label>Password<input type="password" name="password" required autocomplete="current-password"></label><button class="btn btn-primary btn-full">Sign in</button></form><p class="auth-switch">New to ChatConnect? <a href="register.php">Create an account</a></p><p class="tiny muted">Secure sign-in · Your conversations stay yours</p></main></body></html>
