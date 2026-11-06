<?php
declare(strict_types=1);
require dirname(__DIR__) . '/src/bootstrap.php';
\App\Core\Security::logout();
header('Location: /login.php', true, 302);
exit;
