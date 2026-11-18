<?php
require_once __DIR__ . '/config/bootstrap.php';
if (current_user()) { header('Location: app.php'); exit; }

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $fullname = trim($_POST['fullname'] ?? '');
    $username = strtolower(trim($_POST['username'] ?? ''));
    $email = strtolower(trim($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';

    if ($fullname === '' || !preg_match('/^[a-z0-9_]{3,24}$/', $username) ||
        !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 8) {
        $error = 'Please complete all fields. Username must be 3–24 letters, numbers or underscores; password must be at least 8 characters.';
    } else {
        try {
            $stmt = db()->prepare('INSERT INTO users(fullname,username,email,password,last_seen) VALUES(?,?,?,?,CURRENT_TIMESTAMP)');
            $stmt->execute([$fullname, $username, $email, password_hash($password, PASSWORD_DEFAULT)]);
            session_regenerate_id(true);
            $_SESSION['user_id'] = (int)db()->lastInsertId();
            header('Location: app.php'); exit;
        } catch (PDOException $ex) {
    error_log('CHATCONNECT REGISTER ERROR: ' . $ex->getMessage());

    $error = 'Registration failed: ' . $ex->getMessage();
}
    }
}
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Create account · ChatConnect</title><link rel="stylesheet" href="assets/css/style.css"></head>
<body class="auth-page"><main class="auth-card"><a class="brand brand-center" href="index.php"><span class="brand-mark">C</span> ChatConnect</a><p class="eyebrow">YOUR PEOPLE, ONE PLACE</p><h1>Create your account</h1><p class="muted">Start conversations that matter.</p>
<?php if ($error): ?><div class="alert"><?=e($error)?></div><?php endif; ?>
<form method="post" class="auth-form"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><label>Full name<input name="fullname" required maxlength="100" autocomplete="name" value="<?=e($_POST['fullname'] ?? '')?>"></label><label>Username<input name="username" required minlength="3" maxlength="24" pattern="[A-Za-z0-9_]+" autocomplete="username" value="<?=e($_POST['username'] ?? '')?>"></label><label>Email address<input type="email" name="email" required autocomplete="email" value="<?=e($_POST['email'] ?? '')?>"></label><label>Password<input type="password" name="password" required minlength="8" autocomplete="new-password"></label><button class="btn btn-primary btn-full">Create account</button></form><p class="auth-switch">Already have an account? <a href="login.php">Sign in</a></p><p class="tiny muted">By creating an account, you agree to use ChatConnect respectfully.</p></main></body></html>
