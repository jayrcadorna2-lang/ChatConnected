<?php
require_once __DIR__ . '/config/bootstrap.php';
if (current_user()) {
    header('Location: app.php');
} else {
    header('Location: login.php');
}
exit;
