<?php
declare(strict_types=1);

require dirname(__DIR__) . '/src/bootstrap.php';
use App\Core\Database;
use App\Core\Env;

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
$root = dirname(__DIR__); $env = new Env($root . '/.env');
$missing = array_values(array_filter(['pdo', 'pdo_mysql', 'json', 'curl'], static fn(string $ext): bool => !extension_loaded($ext)));
$checks = ['php' => PHP_VERSION_ID >= 80200, 'extensions' => $missing === [], 'storage' => is_writable($root . '/storage')];
if ($checks['extensions'] && $checks['storage'] && $env->get('DB_HOST') && $env->get('DB_NAME') && $env->get('DB_USER') !== null) {
    try { (new Database($env))->pdo()->query('SELECT 1'); $checks['database'] = true; } catch (Throwable) { $checks['database'] = false; }
} else { $checks['database'] = false; }
$ok = !in_array(false, $checks, true);
http_response_code($ok ? 200 : 503);
echo json_encode(['ok' => $ok, 'service' => 'stratmark-adpilot', 'checks' => $checks, 'missing_extensions' => $missing], JSON_THROW_ON_ERROR);
