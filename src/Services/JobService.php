<?php
declare(strict_types=1);

namespace App\Services;

use PDO;

final class JobService
{
    public function __construct(private PDO $pdo) {}

    public function start(string $name, ?int $clientId = null): int
    {
        $this->pdo->prepare("UPDATE job_runs SET status='FAILED', error_message='Superseded by a new run', finished_at=NOW() WHERE job_name=? AND status='RUNNING'")->execute([$name]);
        $stmt = $this->pdo->prepare("INSERT INTO job_runs (job_name, client_id, status, started_at) VALUES (?, ?, 'RUNNING', NOW())");
        $stmt->execute([$name, $clientId]);
        return (int)$this->pdo->lastInsertId();
    }

    public function finish(int $id, string $status, int $records = 0, ?string $error = null): void
    {
        $this->pdo->prepare('UPDATE job_runs SET status=?, records_processed=?, error_message=?, finished_at=NOW() WHERE id=?')->execute([$status, $records, $error, $id]);
    }
}
