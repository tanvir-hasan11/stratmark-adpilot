<?php
declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class InsightRepository
{
    public function __construct(private PDO $pdo) {}

    public function upsert(int $clientId, int $accountId, array $row): void
    {
        $sql = 'INSERT INTO daily_insights
            (client_id, provider_account_id, entity_type, entity_id, entity_name, insight_date, spend, impressions, clicks, conversions, revenue, frequency, ctr, raw_payload)
            VALUES (:client_id,:account_id,:type,:entity_id,:name,:date,:spend,:impressions,:clicks,:conversions,:revenue,:frequency,:ctr,:raw)
            ON DUPLICATE KEY UPDATE entity_name=VALUES(entity_name), spend=VALUES(spend), impressions=VALUES(impressions), clicks=VALUES(clicks), conversions=VALUES(conversions), revenue=VALUES(revenue), frequency=VALUES(frequency), ctr=VALUES(ctr), raw_payload=VALUES(raw_payload)';
        $this->pdo->prepare($sql)->execute([
            ':client_id' => $clientId, ':account_id' => $accountId, ':type' => $row['entity_type'], ':entity_id' => $row['entity_id'],
            ':name' => $row['entity_name'] ?? null, ':date' => $row['date'], ':spend' => $row['spend'], ':impressions' => $row['impressions'],
            ':clicks' => $row['clicks'], ':conversions' => $row['conversions'], ':revenue' => $row['revenue'], ':frequency' => $row['frequency'],
            ':ctr' => $row['ctr'], ':raw' => json_encode($row['raw'], JSON_THROW_ON_ERROR),
        ]);
    }

    public function summary(string $from, string $to, ?int $clientId = null): array
    {
        $sql = 'SELECT COALESCE(SUM(spend),0) spend, COALESCE(SUM(conversions),0) conversions, COALESCE(SUM(revenue),0) revenue, COALESCE(SUM(impressions),0) impressions, COALESCE(SUM(clicks),0) clicks FROM daily_insights WHERE insight_date BETWEEN ? AND ?';
        $params = [$from, $to];
        if ($clientId !== null) { $sql .= ' AND client_id = ?'; $params[] = $clientId; }
        $stmt = $this->pdo->prepare($sql); $stmt->execute($params);
        $row = $stmt->fetch() ?: ['spend' => 0, 'conversions' => 0, 'revenue' => 0, 'impressions' => 0, 'clicks' => 0];
        $row['cpa'] = (float)$row['conversions'] > 0 ? (float)$row['spend'] / (float)$row['conversions'] : 0.0;
        $row['roas'] = (float)$row['spend'] > 0 ? (float)$row['revenue'] / (float)$row['spend'] : 0.0;
        $row['ctr'] = (float)$row['impressions'] > 0 ? ((float)$row['clicks'] / (float)$row['impressions']) * 100 : 0.0;
        return $row;
    }

    public function topCampaigns(string $from, string $to, ?int $clientId = null, int $limit = 10): array
    {
        $limit = max(1, min(50, $limit));
        $sql = 'SELECT pa.provider, di.entity_name, SUM(di.spend) spend, SUM(di.conversions) conversions, SUM(di.revenue) revenue
                FROM daily_insights di JOIN provider_accounts pa ON pa.id = di.provider_account_id
                WHERE di.insight_date BETWEEN ? AND ? AND di.entity_type IN (\'campaign\', \'ad\')';
        $params = [$from, $to];
        if ($clientId !== null) { $sql .= ' AND di.client_id = ?'; $params[] = $clientId; }
        $sql .= ' GROUP BY pa.provider, di.entity_type, di.entity_id, di.entity_name ORDER BY revenue DESC, spend DESC LIMIT ' . $limit;
        $stmt = $this->pdo->prepare($sql); $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function dailyTotals(string $from, string $to, ?int $clientId = null): array
    {
        $sql = 'SELECT insight_date, SUM(spend) spend, SUM(conversions) conversions FROM daily_insights WHERE insight_date BETWEEN ? AND ?';
        $params = [$from, $to];
        if ($clientId !== null) { $sql .= ' AND client_id = ?'; $params[] = $clientId; }
        $sql .= ' GROUP BY insight_date ORDER BY insight_date ASC';
        $stmt = $this->pdo->prepare($sql); $stmt->execute($params);
        return $stmt->fetchAll();
    }
}
