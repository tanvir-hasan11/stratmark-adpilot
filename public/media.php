<?php
declare(strict_types=1);
require dirname(__DIR__) . '/src/bootstrap.php';
use App\Core\Security;
Security::requireAuth();
$state = (new App\Core\LocalState())->all();
$logo = $state['brand']['logo'] ?? null;
if (!is_string($logo) || !preg_match('/^data:image\/(png|jpeg|webp);base64,/', $logo, $match)) { http_response_code(404); exit; }
$raw = base64_decode(substr($logo, strpos($logo, ',') + 1), true);
if ($raw === false || strlen($raw) > 2 * 1024 * 1024) { http_response_code(404); exit; }
$mime = $match[1] === 'jpeg' ? 'image/jpeg' : 'image/' . $match[1];
header('Content-Type: ' . $mime); header('Cache-Control: private, max-age=300'); header('X-Content-Type-Options: nosniff'); echo $raw;
