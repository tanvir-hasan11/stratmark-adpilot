<?php
declare(strict_types=1);

namespace App\Services;

final class InsightNormalizer
{
    public static function meta(array $row, string $date): array
    {
        $actions = self::actions($row['actions'] ?? []);
        $values = self::actions($row['action_values'] ?? []);
        return [
            'entity_type' => 'ad', 'entity_id' => (string)($row['ad_id'] ?? $row['campaign_id'] ?? ''),
            'entity_name' => (string)($row['ad_name'] ?? $row['campaign_name'] ?? ''), 'date' => $date,
            'spend' => (float)($row['spend'] ?? 0), 'impressions' => (int)($row['impressions'] ?? 0),
            'clicks' => (int)($row['clicks'] ?? 0), 'conversions' => (float)($actions['purchase'] ?? $actions['lead'] ?? $actions['complete_registration'] ?? 0),
            'revenue' => (float)($values['purchase'] ?? 0), 'frequency' => isset($row['frequency']) ? (float)$row['frequency'] : null,
            'ctr' => isset($row['ctr']) ? (float)$row['ctr'] : null, 'raw' => $row,
        ];
    }

    public static function google(array $row, string $date): array
    {
        $campaign = $row['campaign'] ?? []; $metrics = $row['metrics'] ?? [];
        return [
            'entity_type' => 'campaign', 'entity_id' => (string)($campaign['id'] ?? ''), 'entity_name' => (string)($campaign['name'] ?? ''), 'date' => $date,
            'spend' => ((float)($metrics['costMicros'] ?? 0)) / 1000000, 'impressions' => (int)($metrics['impressions'] ?? 0),
            'clicks' => (int)($metrics['clicks'] ?? 0), 'conversions' => (float)($metrics['conversions'] ?? 0), 'revenue' => (float)($metrics['conversionsValue'] ?? 0),
            'frequency' => null, 'ctr' => isset($metrics['ctr']) ? (float)$metrics['ctr'] * 100 : null, 'raw' => $row,
        ];
    }

    private static function actions(array $items): array
    {
        $result = [];
        foreach ($items as $item) { if (isset($item['action_type'])) $result[(string)$item['action_type']] = (float)($item['value'] ?? 0); }
        return $result;
    }
}
