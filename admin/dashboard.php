<?php
require_once __DIR__ . '/../config/bootstrap.php';
$user = require_login();
if ($user['role'] !== 'admin') { http_response_code(403); exit('Administrator access required.'); }
$users = db()->query('SELECT id,fullname,username,email,role,status,created_at FROM users ORDER BY id DESC')->fetchAll();
?><!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Admin · ChatConnect</title><link rel="stylesheet" href="../assets/css/style.css"></head><body style="padding:30px"><a class="brand" href="../app.php"><span class="brand-mark">C</span> ChatConnect</a><h1>Administration</h1><p class="muted">Manage registered accounts.</p><div style="overflow:auto;background:white;border-radius:14px;padding:18px"><table style="width:100%;border-collapse:collapse;text-align:left"><tr><th>ID</th><th>Name</th><th>Username</th><th>Email</th><th>Role</th><th>Status</th></tr><?php foreach($users as $u): ?><tr><?php foreach(['id','fullname','username','email','role','status'] as $k): ?><td style="padding:12px;border-top:1px solid #eee"><?=e((string)$u[$k])?></td><?php endforeach ?></tr><?php endforeach ?></table></div></body></html>
