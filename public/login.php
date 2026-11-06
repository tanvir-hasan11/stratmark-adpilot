<?php
declare(strict_types=1);
require dirname(__DIR__) . '/src/bootstrap.php';
use App\Core\Env; use App\Core\Security;
$env = new Env(dirname(__DIR__) . '/.env'); Security::startSession(); $error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim((string)($_POST['username'] ?? '')); $key = hash('sha256', ($_SERVER['REMOTE_ADDR'] ?? 'unknown') . '|' . strtolower($username));
    if (!Security::verifyCsrf($_POST['_csrf'] ?? null)) $error = 'Your session expired. Refresh and try again.';
    elseif (!Security::loginAllowed($key)) { http_response_code(429); $error = 'Too many sign-in attempts. Try again later.'; }
    else {
        $hash = (string)$env->get('ADMIN_PASSWORD_HASH', '');
        $valid = hash_equals((string)$env->get('ADMIN_USERNAME', ''), $username) && $hash !== '' && password_verify((string)($_POST['password'] ?? ''), $hash);
        if (!$valid) { Security::recordLoginFailure($key); $error = 'The username or password is incorrect.'; }
        else { Security::login($username, null, 'ADMIN'); header('Location: /', true, 302); exit; }
    }
}
?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Sign in · Stratmark Adpilot</title><style>body{font:15px system-ui;margin:0;background:#f6f8fb;display:grid;place-items:center;min-height:100vh;color:#172231}.box{width:min(380px,calc(100% - 40px));background:#fff;padding:32px;border-radius:12px;box-shadow:0 10px 35px #12243a12}h1{margin:0 0 8px;font-size:25px}p{color:#718096;font-size:13px}label{display:block;font-size:12px;font-weight:700;margin:16px 0 6px}input{width:100%;box-sizing:border-box;padding:12px;border:1px solid #dbe3ec;border-radius:7px;font:inherit}button{width:100%;padding:12px;border:0;border-radius:7px;background:#2864e8;color:#fff;font-weight:700;margin-top:20px}.error{background:#fff0f0;color:#b23c48;padding:10px;border-radius:7px;font-size:12px}</style></head><body><main class="box"><h1>Sign in to Adpilot</h1><p>Access the protected marketing workspace.</p><?php if($error):?><div class="error"><?=htmlspecialchars($error,ENT_QUOTES,'UTF-8')?></div><?php endif;?><form method="post"><input type="hidden" name="_csrf" value="<?=htmlspecialchars(Security::csrf(),ENT_QUOTES,'UTF-8')?>"><label for="username">Username</label><input id="username" name="username" required autocomplete="username"><label for="password">Password</label><input id="password" type="password" name="password" required autocomplete="current-password"><button type="submit">Sign in securely</button></form></main></body></html>
