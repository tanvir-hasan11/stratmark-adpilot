<?php
declare(strict_types=1);

namespace App\Core;

use PDO;

final class Database
{
    private PDO $pdo;

    public function __construct(Env $env)
    {
        $host = $env->required('DB_HOST');
        $port = $env->get('DB_PORT', '3306');
        $name = $env->required('DB_NAME');
        $this->pdo = new PDO(
            sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $host, $port, $name),
            $env->required('DB_USER'),
            $env->required('DB_PASS'),
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false]
        );
    }

    public function pdo(): PDO { return $this->pdo; }
}
