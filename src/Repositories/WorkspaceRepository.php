<?php
declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class WorkspaceRepository
{
    public function __construct(private PDO $pdo) {}

    public function ensureDefaultClient(string $name, string $timezone, string $currency): int
    {
        $stmt = $this->pdo->prepare('SELECT id FROM clients WHERE name = ? ORDER BY id ASC LIMIT 1');
        $stmt->execute([$name]);
        $id = $stmt->fetchColumn();
        if ($id !== false) return (int)$id;
        $stmt = $this->pdo->prepare('INSERT INTO clients (name, timezone, currency) VALUES (?, ?, ?)');
        $stmt->execute([$name, $timezone, $currency]);
        return (int)$this->pdo->lastInsertId();
    }

    public function upsertProviderAccount(int $clientId, string $provider, string $externalId, string $displayName = ''): int
    {
        $sql = 'INSERT INTO provider_accounts (client_id, provider, external_account_id, display_name, is_active)
                VALUES (?, ?, ?, ?, 1)
                ON DUPLICATE KEY UPDATE display_name = VALUES(display_name), is_active = 1';
        $this->pdo->prepare($sql)->execute([$clientId, $provider, $externalId, $displayName !== '' ? $displayName : null]);
        $stmt = $this->pdo->prepare('SELECT id FROM provider_accounts WHERE client_id = ? AND provider = ? AND external_account_id = ?');
        $stmt->execute([$clientId, $provider, $externalId]);
        $id = $stmt->fetchColumn();
        if ($id === false) throw new \RuntimeException('Provider account could not be saved.');
        return (int)$id;
    }
}
