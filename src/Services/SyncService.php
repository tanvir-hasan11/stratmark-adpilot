<?php
declare(strict_types=1);

namespace App\Services;

use App\Repositories\InsightRepository;
use App\Contracts\ApiConnectorInterface;
use PDO;

final class SyncService
{
    public function __construct(private PDO $pdo, private InsightRepository $repository) {}

    public function syncAccount(int $providerAccountId, int $clientId, string $provider, ApiConnectorInterface $connector, string $from, string $to): int
    {
        if (!in_array($provider, ['meta', 'google_ads'], true) || $connector->provider() !== $provider) throw new \InvalidArgumentException('Provider mismatch.');
        self::date($from); self::date($to);
        $stmt = $this->pdo->prepare('SELECT external_account_id, client_id FROM provider_accounts WHERE id = ? AND is_active = 1'); $stmt->execute([$providerAccountId]);
        $account = $stmt->fetch();
        if (!$account || (int)$account['client_id'] !== $clientId) throw new \RuntimeException('Provider account not found or not owned by client.');
        $rows = $connector->fetchInsights((string)$account['external_account_id'], $from, $to);
        $items = $rows['data'] ?? $rows;
        if (!is_array($items)) throw new \RuntimeException('Provider returned an invalid insights payload.');
        $count = 0; $this->pdo->beginTransaction();
        try {
            foreach ($items as $item) {
                if (!is_array($item)) continue;
                $date = (string)($item['date_start'] ?? $item['segments']['date'] ?? $from); self::date($date);
                $normalized = $provider === 'meta' ? InsightNormalizer::meta($item, $date) : InsightNormalizer::google($item, $date);
                if ($normalized['entity_id'] === '') continue;
                $this->repository->upsert($clientId, $providerAccountId, $normalized); $count++;
            }
            $this->pdo->commit();
        } catch (Throwable $e) { $this->pdo->rollBack(); throw $e; }
        return $count;
    }

    private static function date(string $value): void
    {
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        if (!$date || $date->format('Y-m-d') !== $value) throw new \InvalidArgumentException('Invalid date range.');
    }
}
