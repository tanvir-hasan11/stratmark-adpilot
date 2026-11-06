<?php
declare(strict_types=1);

namespace App\Connectors;

use App\Contracts\ApiConnectorInterface;

final class MetaConnector implements ApiConnectorInterface
{
    public function __construct(private string $accessToken, private string $apiVersion = 'v21.0') {}
    public function provider(): string { return 'meta'; }
    public function listAccounts(): array { return $this->request('/me/adaccounts', ['fields' => 'id,name,account_status,currency']); }

    public function fetchInsights(string $accountId, string $from, string $to): array
    {
        $normalized = preg_replace('/^act_/', '', trim($accountId));
        if ($normalized === '' || !ctype_digit($normalized)) throw new \InvalidArgumentException('Invalid Meta account ID.');
        $params = [
            'level' => 'ad',
            'time_range' => json_encode(['since' => $from, 'until' => $to], JSON_THROW_ON_ERROR),
            'fields' => 'account_id,campaign_id,campaign_name,adset_id,adset_name,ad_id,ad_name,spend,impressions,clicks,ctr,frequency,actions,action_values',
            'limit' => 500,
        ];
        $result = $this->request('/act_' . $normalized . '/insights', $params);
        $items = $result['data'] ?? [];
        while (!empty($result['paging']['next'])) {
            $result = $this->requestUrl((string)$result['paging']['next']);
            if (!empty($result['data']) && is_array($result['data'])) $items = array_merge($items, $result['data']);
        }
        return ['data' => $items];
    }

    private function request(string $path, array $params): array
    {
        $params['access_token'] = $this->accessToken;
        return $this->requestUrl('https://graph.facebook.com/' . $this->apiVersion . $path . '?' . http_build_query($params));
    }

    private function requestUrl(string $url): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 60, CURLOPT_CONNECTTIMEOUT => 10, CURLOPT_HTTPHEADER => ['Accept: application/json']]);
        $body = curl_exec($ch); if ($body === false) throw new \RuntimeException(curl_error($ch));
        $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
        $data = json_decode($body, true);
        if ($status >= 400 || !is_array($data)) throw new \RuntimeException('Meta API request failed: HTTP ' . $status);
        return $data;
    }
}
